<?php
include 'db.php'; // Memasukkan koneksi database dan memulai sesi

// Arahkan ke login jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Konfigurasi API Keys
$openweather_api_key = 'd9c47d89a3ce02eba2dfd861f14ce302'; // Daftar di openweathermap.org
$mapbox_token = 'YOUR_MAPBOX_TOKEN'; // Daftar di mapbox.com - Pastikan ini juga diisi jika ingin menggunakan Mapbox tiles

// Fungsi untuk mengambil data cuaca
function getWeatherData($lat, $lon, $api_key) {
    $url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&units=metric&appid={$api_key}";
    $response = @file_get_contents($url); // Menambahkan @ untuk menekan error
    if ($response === FALSE) {
        // Tangani kesalahan di sini
        return ['error' => 'Gagal mengambil data cuaca.'];
    }
    return json_decode($response, true);
}


// Fungsi untuk menghitung tingkat kerawanan
function calculateRiskLevel($temp, $humidity, $rainfall, $population_density) {
    $risk_score = 0;
    
    // Faktor suhu (optimal 25-30°C)
    if ($temp >= 25 && $temp <= 30) {
        $risk_score += 3; // Highest risk contribution for optimal temperature
    } elseif ($temp >= 20 && $temp <= 35) {
        $risk_score += 2; // Medium risk contribution for slightly outside optimal
    } else {
        $risk_score += 1; // Lowest risk contribution for temperatures far from optimal
    }
    
    // Faktor kelembaban (optimal 70-90%)
    if ($humidity >= 70 && $humidity <= 90) {
        $risk_score += 3; // Highest risk contribution for optimal humidity
    } elseif ($humidity > 90 || ($humidity >= 60 && $humidity < 70)) { // Above 90% or between 60% and 70%
        $risk_score += 2; // Medium risk contribution
    } else {
        $risk_score += 1; // Lowest risk contribution (below 60%)
    }
    
    // Faktor curah hujan (optimal 100-300mm)
    if ($rainfall >= 100 && $rainfall <= 300) {
        $risk_score += 3; // Highest risk contribution for optimal rainfall
    } elseif ($rainfall > 300 || ($rainfall > 50 && $rainfall < 100)) { // Above 300mm or between 50mm and 100mm
        $risk_score += 2; // Medium risk contribution
    } else {
        $risk_score += 1; // Lowest risk contribution (below 50mm)
    }
    
    // Rentang kepadatan penduduk yang diobservasi di Kota Pontianak: 3.041 - 9.268 jiwa/km²
    if ($population_density > 7500) {
        $risk_score += 3; // Kontribusi risiko TERTINGGI (untuk kepadatan sangat tinggi)
    } elseif ($population_density >= 4000 && $population_density <= 7500) {
        $risk_score += 2; // Kontribusi risiko SEDANG (untuk kepadatan menengah)
    } else { // $population_density < 4000
        $risk_score += 1; // Kontribusi risiko RENDAH (untuk kepadatan rendah)
    }

    
    // Kategorisasi risiko
    if ($risk_score >= 10) return 'Tinggi';
    elseif ($risk_score >= 7) return 'Sedang';
    else return 'Rendah';
}

// Mengambil data kecamatan dari database
$regions_query = "SELECT id, name, latitude, longitude, population_density, rainfall_avg FROM kecamatan";
$regions_result = $conn->query($regions_query);
$regions = [];
if ($regions_result->num_rows > 0) {
    while($row = $regions_result->fetch_assoc()) {
        $regions[] = $row;
    }
}

$today = date("Y-m-d");

// Mengambil data cuaca untuk setiap wilayah dan menyimpan ke database
foreach ($regions as &$region) {
    $weather = getWeatherData($region['latitude'], $region['longitude'], $openweather_api_key);
    
    // Inisialisasi curah hujan dari database sebagai nilai fallback
    $current_rainfall = $region['rainfall_avg']; 

    if (isset($weather['main'])) {
        $region['temp'] = $weather['main']['temp'];
        $region['humidity'] = $weather['main']['humidity'];
        
        // --- AWAL KODE TAMBAHAN/MODIFIKASI UNTUK CURAH HUJAN DARI API ---
        // Cek jika data hujan per jam ('1h') tersedia dari API
        if (isset($weather['rain']['1h'])) {
            $current_rainfall = $weather['rain']['1h']; // Gunakan data API jika ada
        } 
        // --- AKHIR KODE TAMBAHAN/MODIFIKASI ---

    } else {
        // Jika panggilan API gagal seluruhnya, gunakan data simulasi untuk suhu/kelembaban
        // dan curah hujan akan tetap dari database (sesuai inisialisasi $current_rainfall di awal loop)
        $region['temp'] = rand(27, 33);
        $region['humidity'] = rand(75, 90);
    }
    
    // Baris ini akan menggunakan nilai curah hujan yang sudah ditentukan ($current_rainfall)
    $region['risk_level'] = calculateRiskLevel(
        $region['temp'], 
        $region['humidity'], 
        $current_rainfall, // GUNAKAN VARIABEL INI
        $region['population_density']
    );

    // Simpan data harian ke database (hanya sekali per hari per kecamatan)
    $check_daily_data_query = $conn->prepare("SELECT id FROM region_daily_data WHERE kecamatan_id = ? AND record_date = ?");
    $check_daily_data_query->bind_param("is", $region['id'], $today);
    $check_daily_data_query->execute();
    $daily_data_result = $check_daily_data_query->get_result();

    if ($daily_data_result->num_rows == 0) {
        $insert_daily_data_stmt = $conn->prepare(
            "INSERT INTO region_daily_data (kecamatan_id, record_date, temperature, humidity, risk_level) VALUES (?, ?, ?, ?, ?)"
        );
        $insert_daily_data_stmt->bind_param(
            "isdss", 
            $region['id'], 
            $today, 
            $region['temp'], 
            $region['humidity'], 
            $region['risk_level']
        );
        $insert_daily_data_stmt->execute();
    }
}

// Mengambil data pasien dari database (sesuai saran pengembangan)
$patients_query = "SELECT * FROM pasien ORDER BY id DESC";
$patients_result = $conn->query($patients_query);
$patients = [];
if ($patients_result->num_rows > 0) {
    while($row = $patients_result->fetch_assoc()) {
        $patients[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Kerawanan Demam Berdarah - Pontianak</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c5530 0%, #1a7037 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
        }
        
        #sidebar {
            width: 250px;
            background: linear-gradient(180deg, #1a7037 0%, #2c5530 100%);
            padding: 20px;
            color: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            flex-shrink: 0;
        }

        #sidebar .sidebar-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        #sidebar .sidebar-header h2 {
            font-size: 1.8rem;
            margin: 0;
            font-weight: bold;
        }

        #sidebar ul.components {
            padding: 0;
            list-style: none;
            flex-grow: 1;
        }

        #sidebar ul li {
            margin-bottom: 10px;
        }

        #sidebar ul li a {
            display: flex;
            align-items: center;
            gap: 10px; /* jarak antara ikon dan teks */
            white-space: nowrap; /* jangan pindah baris */
            width: 100%; /* biar link penuh */
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }

        #sidebar ul li a:hover,
        #sidebar ul li a.active {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        #sidebar ul li a i {
            margin-right: 10px;
        }

        #content {
            flex-grow: 1;
            padding: 20px 15px;
            background: linear-gradient(135deg, #f0f0f0 0%, #ffffff 100%);
            overflow-y: auto;
            min-height: 100vh;
        }
        
        .main-content-area {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            border-radius: 10px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .header p {
            margin: 10px 0 0 0;
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .city-info {
            background: linear-gradient(45deg, #2c5530, #1a7037);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        #map {
            height: 600px;
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            margin-bottom: 30px;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-left: 5px solid;
        }
        
        .stats-card.tinggi { border-left-color: #D32F2F; } /* Warna baru untuk Tinggi */
        .stats-card.sedang { border-left-color: #FFB300; } /* Warna baru untuk Sedang */
        .stats-card.rendah { border-left-color: #66BB6A; } /* Warna baru untuk Rendah */
        
        .legend {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            border-radius: 3px;
        }
        
        .control-panel {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .btn-custom {
            background: linear-gradient(45deg, #2c5530, #1a7037);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .scroll-indicator {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(44, 85, 48, 0.9);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 11px;
            animation: fadeInOut 3s infinite;
            pointer-events: none;
            z-index: 10;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        .scrollable-stats {
            max-height: 320px; /* kira-kira cukup untuk 2 kartu */
            overflow-y: auto;
            padding: 15px;
            background: #ffffff;
            border: 2px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }


        .scrollable-stats::-webkit-scrollbar {
            width: 8px;
        }
        .scrollable-stats::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .scrollable-stats::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 10px;
        }
        .scrollable-stats::-webkit-scrollbar-thumb:hover {
            background: #999;
        }

        
        @keyframes fadeInOut {
            0%, 100% { opacity: 0.6; transform: translateY(0); }
            50% { opacity: 1; transform: translateY(-2px); }
        }
        /* Stats card modifications for better scrolling */
        .scrollable-stats .stats-card {
            margin-bottom: 15px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .scrollable-stats .stats-card:hover {
            transform: translateX(3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        
        .scrollable-stats .stats-card:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-chart-bar"></i> User Panel</h2>
            <small>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></small>
        </div>
        <ul class="components">
            <li>
                <a href="index.php" class="sidebar-link active" data-target="dashboard-overview">
                    <i class="fas fa-tachometer-alt"></i> Dashboard Overview
                </a>
            </li>
            <li>
                <a href="statistics.php" class="sidebar-link">
                    <i class="fas fa-chart-bar"></i> Statistik Historis
                </a>
            </li>
            <li>
                <a href="profile.php" class="sidebar-link">
                    <i class="fas fa-user-edit"></i> Kelola Profil
                </a>
            </li>
        </ul>
        <ul class="components">
            <li>
                <a href="logout.php" class="sidebar-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>
    <div id="content">
        <div class="main-content-area">
            <div class="header">
                <h1><i class="fas fa-bug"></i> Monitoring Kerawanan Demam Berdarah</h1>
                <p>Sistem Pemantauan Real-time Kota Pontianak</p>
                <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
            </div>
            
            <div class="city-info">
                <h5><i class="fas fa-map-marker-alt"></i> Kota Pontianak, Kalimantan Barat</h5>
                <p>Monitoring berbasis data cuaca tropis dan kepadatan penduduk</p>
            </div>
            
            <div class="row">
                <div class="col-lg-8">
                    <div id="map"></div>
                </div>
                
                <div class="col-lg-4">
                    <div class="legend">
                        <h5>**Legenda Tingkat Risiko**</h5>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #D32F2F;"></div> <span>Risiko Tinggi</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #FFB300;"></div> <span>Risiko Sedang</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #66BB6A;"></div> <span>Risiko Rendah</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #007bff;"></div>
                            <span>Lokasi Pasien</span>
                        </div>
                    </div>
                    
                    <div class="control-panel">
                        <h5>**Panel Kontrol**</h5>
                        <button class="btn btn-custom btn-sm mb-2" onclick="togglePatients()">
                            <i class="fas fa-user-injured"></i> Toggle Pasien
                        </button>
                        <button class="btn btn-custom btn-sm mb-2" onclick="toggleChoropleth()">
                            <i class="fas fa-map"></i> Toggle Choropleth
                        </button>
                        <button class="btn btn-custom btn-sm mb-2" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i> Refresh Data
                        </button>
                        <button class="btn btn-custom btn-sm mb-2" onclick="fitToPontianak()">
                            <i class="fas fa-crosshairs"></i> Fokus Pontianak
                        </button>
                        <a href="statistics.php" class="btn btn-custom btn-sm mb-2 w-100">
                            <i class="fas fa-chart-bar"></i> Lihat Statistik
                        </a>
                    </div>
        <div class="scrollable-stats" id="statsContainer">
        <?php
        $seen = [];
        foreach ($regions as $region):
            if (in_array(strtolower($region['name']), $seen)) continue;
            $seen[] = strtolower($region['name']);
        ?>
        <div class="stats-card <?php echo strtolower($region['risk_level']); ?>">
            <h6><strong><?php echo $region['name']; ?></strong></h6>
            <p><small>
                <strong>Suhu:</strong> <?php echo $region['temp']; ?>°C<br>
                <strong>Kelembaban:</strong> <?php echo $region['humidity']; ?>%<br>
                <strong>Curah Hujan:</strong> <?php echo $region['rainfall_avg']; ?>mm<br>
                <strong>Kepadatan:</strong> <?php echo number_format($region['population_density']); ?> jiwa/km²<br>
                <strong>Tingkat Risiko:</strong>
                <span class="badge bg-<?php echo $region['risk_level'] == 'Tinggi' ? 'danger' : ($region['risk_level'] == 'Sedang' ? 'warning' : 'success'); ?>">
                    <?php echo $region['risk_level']; ?>
                </span>
            </small></p>
        </div>
        <?php endforeach; ?>
        </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="https://unpkg.com/leaflet-ajax/dist/leaflet.ajax.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    
    <script>
        // Inisialisasi peta dengan fokus ke Pontianak
        var map = L.map('map').setView([-0.0263, 109.3425], 12);
        
        // Base map
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Data dari PHP
        var regions = <?php echo json_encode($regions); ?>;
        var patients = <?php echo json_encode($patients); ?>;

        
        // Layer groups
        var choroplethLayer = L.layerGroup().addTo(map);
        var patientsLayer = L.layerGroup().addTo(map);
        
        // Fungsi untuk mendapatkan warna berdasarkan risiko
        function getRiskColor(risk) {
            switch(risk) {
                case 'Tinggi': return '#D32F2F'; // Merah Tua
                case 'Sedang': return '#FFB300'; // Oranye Kekuningan
                case 'Rendah': return '#66BB6A'; // Hijau Cerah
                default: return '#6c757d'; // Warna default jika tidak ada data
            }
        }
        
        // Fungsi untuk membuat choropleth map dari GeoJSON
        function createChoropleth() {
            choroplethLayer.clearLayers(); // Hapus layer yang ada sebelum menambahkan yang baru

            fetch("kecamatan_pontianak.geojson")
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Kesalahan HTTP! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(geojsonData => {
                    geojsonData.features.forEach(feature => {
                        // Normalisasi nama dari GeoJSON untuk perbandingan (lowercase)
                        const geoName = (feature.properties.name || '').toLowerCase();
                        const matchedRegion = regions.find(r => r.name.toLowerCase() === geoName);

                        if (matchedRegion) {
                            feature.properties.risk_level = matchedRegion.risk_level;
                            feature.properties.temp = matchedRegion.temp;
                            feature.properties.humidity = matchedRegion.humidity;
                            feature.properties.rainfall_avg = matchedRegion.rainfall_avg; // Use this for display only
                            feature.properties.population_density = matchedRegion.population_density;
                        } else {
                            feature.properties.risk_level = 'Tidak Ada Data';
                            feature.properties.temp = 'N/A';
                            feature.properties.humidity = 'N/A';
                            feature.properties.rainfall_avg = 'N/A';
                            feature.properties.population_density = 'N/A';
                        }
                    });

                    const geoLayer = L.geoJson(geojsonData, {
                        style: feature => ({
                            fillColor: getRiskColor(feature.properties.risk_level),
                            weight: 2,
                            color: 'white',
                            opacity: 1,
                            dashArray: '3',
                            fillOpacity: 0.7
                        }),
                        onEachFeature: (feature, layer) => {
                            const name = feature.properties.name || 'Tanpa Nama';
                            const risk = feature.properties.risk_level;
                            const temp = feature.properties.temp;
                            const humidity = feature.properties.humidity;
                            const rainfall_avg_display = feature.properties.rainfall_avg; // Use this for display only
                            const population_density = feature.properties.population_density;

                            layer.bindPopup(`
                                <strong>${name}</strong><br>
                                Tingkat Risiko: <span style="color:${getRiskColor(risk)}; font-weight:bold">${risk}</span><br>
                                Suhu: ${temp}°C<br>
                                Kelembaban: ${humidity}%<br>
                                Curah Hujan: ${rainfall_avg_display}mm<br> 
                                Kepadatan Penduduk: ${population_density.toLocaleString()} jiwa/km²
                            `);
                        }
                    });

                    geoLayer.addTo(choroplethLayer);
                })
                .catch(error => console.error("Gagal memuat GeoJSON:", error));
        }
        
        // Membuat point markers untuk pasien
        function createPatientMarkers() {
            patientsLayer.clearLayers();
            
            patients.forEach(function(patient, index) {
                var marker = L.marker([parseFloat(patient.latitude), parseFloat(patient.longitude)], {
                    icon: L.divIcon({
                        className: 'patient-marker',
                        html: '<div style="background-color: #007bff; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">' + (index + 1) + '</div>',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    })
                }).addTo(patientsLayer);
                
                marker.bindPopup(`
                    <div style="min-width: 150px;">
                        <h6>**<i class="fas fa-user-injured"></i> ${patient.nama}**</h6>
                        <hr style="margin: 10px 0;">
                        <p style="margin: 5px 0;">**Tanggal:** ${new Date(patient.tanggal_lapor).toLocaleDateString('id-ID')}</p>
                        <p style="margin: 5px 0;">**Koordinat:** ${parseFloat(patient.latitude).toFixed(4)}, ${parseFloat(patient.longitude).toFixed(4)}</p>
                    </div>
                `);
            });
        }
        
        // Fungsi toggle
        function togglePatients() {
            if (map.hasLayer(patientsLayer)) {
                map.removeLayer(patientsLayer);
            } else {
                map.addLayer(patientsLayer);
            }
        }
        
        function toggleChoropleth() {
            if (map.hasLayer(choroplethLayer)) {
                map.removeLayer(choroplethLayer);
            } else {
                map.addLayer(choroplethLayer);
            }
        }
        
        function refreshData() {
            location.reload();
        }
        
        function fitToPontianak() {
            map.setView([-0.0263, 109.3425], 12);
        }
        
        // Inisialisasi layers
        createChoropleth();
        createPatientMarkers();
        
        // JavaScript for sidebar navigation
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarLinks = document.querySelectorAll('.sidebar-link');
            const sections = document.querySelectorAll('.section-content');

            function showSection(targetId) {
                sections.forEach(section => {
                    section.classList.remove('active');
                });
                const targetSection = document.getElementById(targetId);
                if (targetSection) {
                    targetSection.classList.add('active');
                    if (targetId === 'risk-map' || targetId === 'dashboard-overview') {
                        setTimeout(() => {
                            map.invalidateSize();
                        }, 0); 
                    }
                }
            }

            sidebarLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const targetId = this.getAttribute('data-target');
                    if (targetId) {
                        e.preventDefault();
                        
                        sidebarLinks.forEach(item => item.classList.remove('active'));
                        this.classList.add('active');
                        
                        showSection(targetId);
                    }
                });
            });

            // Handle URL hash for direct access (e.g., dasboard-admin.php#patient-data)
            const initialHash = window.location.hash.substring(1);
            if (initialHash) {
                const initialTarget = document.querySelector(`.sidebar-link[data-target="${initialHash}"]`);
                if (initialTarget) {
                    sidebarLinks.forEach(item => item.classList.remove('active'));
                    initialTarget.classList.add('active');
                    showSection(initialHash);
                } else {
                    showSection('dashboard-overview');
                }
            } else {
                showSection('dashboard-overview');
            }
        });

        // Auto refresh setiap 5 menit
        setInterval(function() {
            refreshData();
        }, 300000);
    </script>
</body>
</html>
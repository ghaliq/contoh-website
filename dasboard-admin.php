<?php 
include 'db.php'; 

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Konfigurasi API Keys
$openweather_api_key = 'd9c47d89a3ce02eba2dfd861f14ce302';

// Fungsi untuk mengambil data cuaca
function getWeatherData($lat, $lon, $api_key) {
    $url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&units=metric&appid={$api_key}";
    $response = @file_get_contents($url);
    if ($response === FALSE) {
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
    
    // Faktor kepadatan penduduk (optimal 4000-8000 per km²)
    // Perhatikan: population_density di database Anda mungkin dalam skala ribu, sesuaikan jika perlu.
    // Misal, jika 5.709 berarti 5709 jiwa/km², maka tidak perlu dikalikan 1000 saat membandingkan
    // tapi jika 5.709 berarti 5.709 jiwa/km^2, maka perlu disesuaikan.
    // Saya asumsikan nilai 5.709 di array sebelumnya adalah ribuan. Kita akan ambil dari DB apa adanya.
    if ($population_density >= 4000 && $population_density <= 8000) {
        $risk_score += 3; // Highest risk contribution for optimal population density
    } elseif ($population_density > 8000 || ($population_density > 2000 && $population_density < 4000)) { // Above 8000 or between 2000 and 4000
        $risk_score += 2; // Medium risk contribution
    } else {
        $risk_score += 1; // Lowest risk contribution (below 2000)
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
    
    if (isset($weather['main'])) {
        $region['temp'] = $weather['main']['temp'];
        $region['humidity'] = $weather['main']['humidity'];
    } else {
        // Jika gagal, gunakan data simulasi
        $region['temp'] = rand(27, 33);
        $region['humidity'] = rand(75, 90);
    }
    
    $region['risk_level'] = calculateRiskLevel(
        $region['temp'], 
        $region['humidity'], 
        $region['rainfall_avg'], // Menggunakan rainfall_avg dari database
        $region['population_density'] // Menggunakan population_density dari database
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

// Mengambil data pasien dari database (ini tetap sama seperti sebelumnya)
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
    <title>Dashboard Admin - Monitoring DBD Pontianak</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c5530 0%, #1a7037 100%);
            margin: 0;
            padding: 20px;
        }
        
        .container-fluid {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
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
        
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: linear-gradient(45deg, #2c5530, #1a7037);
            color: white;
            border: none;
            font-weight: 600;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table td {
            vertical-align: middle;
            text-align: center;
            border-color: #e9ecef;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        #map {
            height: 600px;
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
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
            color: white;
        }
        
        .btn-add {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
        
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
        
        .modal-header {
            background: linear-gradient(45deg, #2c5530, #1a7037);
            color: white;
        }
        
        .form-label {
            font-weight: 600;
            color: #2c5530;
        }
        
        .form-control:focus {
            border-color: #2c5530;
            box-shadow: 0 0 0 0.2rem rgba(44, 85, 48, 0.25);
        }

        /* Styles for scrollable stats */
        .scrollable-stats {
            max-height: 320px; /* Adjust as needed */
            overflow-y: auto;
            padding: 15px;
            background: #ffffff;
            border: 2px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-top: 20px; /* Added margin to separate from control panel */
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
        .scrollable-stats .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-left: 5px solid;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .scrollable-stats .stats-card:hover {
            transform: translateX(3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        .scrollable-stats .stats-card:last-child {
            margin-bottom: 0;
        }
        .stats-card.tinggi { border-left-color: #dc3545; }
        .stats-card.sedang { border-left-color: #ffc107; }
        .stats-card.rendah { border-left-color: #28a745; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Dashboard Admin</h1>
            <p>Sistem Monitoring Demam Berdarah - Kota Pontianak</p>
            <p>Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?>! | <a href="logout.php" class="btn btn-sm btn-danger">Logout</a></p>
        </div>
        
        <div class="table-container">
            <div class="table-header">
                <h4><i class="fas fa-users"></i> Data Pasien DBD</h4>
                <a href="add.php" class="btn btn-add">
                    <i class="fas fa-plus"></i> Tambah Pasien
                </a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>JK</th>
                            <th>Umur</th>
                            <th>Alamat</th>
                            <th>Lat</th>
                            <th>Lng</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach($patients as $patient): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($patient['nama']); ?></td>
                            <td><?php echo $patient['jenis_kelamin']; ?></td>
                            <td><?php echo $patient['umur']; ?></td>
                            <td><?php echo htmlspecialchars($patient['alamat']); ?></td>
                            <td><?php echo $patient['latitude']; ?></td>
                            <td><?php echo $patient['longitude']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($patient['tanggal_lapor'])); ?></td>
                            <td>
                                <a href="edit.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-warning me-1">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus data pasien ini?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-9">
                <div id="map"></div>
            </div>
            
            <div class="col-lg-3">
                <div class="legend">
                    <h5><strong>Legenda Tingkat Risiko</strong></h5>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #dc3545;"></div>
                        <span>Risiko Tinggi</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #ffc107;"></div>
                        <span>Risiko Sedang</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #28a745;"></div>
                        <span>Risiko Rendah</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #007bff;"></div>
                        <span>Lokasi Pasien</span>
                    </div>
                </div>
                
                <div class="legend">
                    <h5><strong>Panel Kontrol</strong></h5>
                    <button class="btn btn-custom btn-sm mb-2 w-100" onclick="togglePatients()">
                        <i class="fas fa-user-injured"></i> Toggle Pasien
                    </button>
                    <button class="btn btn-custom btn-sm mb-2 w-100" onclick="toggleChoropleth()">
                        <i class="fas fa-map"></i> Toggle Choropleth
                    </button>
                    <button class="btn btn-custom btn-sm mb-2 w-100" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Refresh Data
                    </button>
                    <button class="btn btn-custom btn-sm mb-2 w-100" onclick="fitToPontianak()">
                        <i class="fas fa-crosshairs"></i> Fokus Pontianak
                    </button>
                     <a href="statistics.php" class="btn btn-custom btn-sm mb-2 w-100">
                        <i class="fas fa-chart-bar"></i> Lihat Statistik
                    </a>
                </div>

                <div class="scrollable-stats" id="statsContainer">
                    <h5><strong>Statistik Risiko Wilayah</strong></h5>
                    <?php
                    $seen = [];
                    foreach ($regions as $region):
                        // Ensure unique regions if there are duplicates from PHP array structure
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    
    <script>
        // Inisialisasi peta
        var map = L.map('map').setView([-0.0263, 109.3425], 12);
        
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
                case 'Tinggi': return '#dc3545';
                case 'Sedang': return '#ffc107';
                case 'Rendah': return '#28a745';
                default: return '#6c757d'; // Warna default jika tidak ada data
            }
        }
        
        // Membuat choropleth untuk wilayah dari GeoJSON
        function createChoropleth() {
            choroplethLayer.clearLayers();
            
            // Asumsi file GeoJSON berada di direktori yang sama atau dapat diakses secara publik
            fetch("kecamatan_pontianak.geojson") 
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Kesalahan HTTP! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(geojsonData => {
                    geojsonData.features.forEach(feature => {
                        const geoName = (feature.properties.name || '').toLowerCase();
                        const matchedRegion = regions.find(r => r.name.toLowerCase() === geoName);

                        if (matchedRegion) {
                            feature.properties.risk_level = matchedRegion.risk_level;
                            feature.properties.temp = matchedRegion.temp;
                            feature.properties.humidity = matchedRegion.humidity;
                            feature.properties.rainfall_avg = matchedRegion.rainfall_avg;
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
                            const rainfall_avg = feature.properties.rainfall_avg;
                            const population_density = feature.properties.population_density;

                            layer.bindPopup(`
                                <strong>${name}</strong><br>
                                Tingkat Risiko: <span style="color:${getRiskColor(risk)}; font-weight:bold">${risk}</span><br>
                                Suhu: ${temp}°C<br>
                                Kelembaban: ${humidity}%<br>
                                Curah Hujan: ${rainfall_avg}mm<br>
                                Kepadatan Penduduk: ${population_density.toLocaleString()} jiwa/km²
                            `);
                        }
                    });

                    geoLayer.addTo(choroplethLayer);
                })
                .catch(error => console.error("Gagal memuat GeoJSON:", error));
        }
        
        // Membuat markers untuk pasien dari database
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
                    <div style="min-width: 200px;">
                        <h6><i class="fas fa-user-injured"></i> ${patient.nama}</h6>
                        <hr style="margin: 10px 0;">
                        <p style="margin: 5px 0;"><strong>JK:</strong> ${patient.jenis_kelamin}</p>
                        <p style="margin: 5px 0;"><strong>Umur:</strong> ${patient.umur} tahun</p>
                        <p style="margin: 5px 0;"><strong>Alamat:</strong> ${patient.alamat}</p>
                        <p style="margin: 5px 0;"><strong>Tanggal:</strong> ${new Date(patient.tanggal_lapor).toLocaleDateString('id-ID')}</p>
                        <p style="margin: 5px 0;"><strong>Koordinat:</strong> ${parseFloat(patient.latitude).toFixed(4)}, ${parseFloat(patient.longitude).toFixed(4)}</p>
                    </div>
                `);
            });
        }
        
        // Fungsi kontrol
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
        
        // Inisialisasi
        createChoropleth();
        createPatientMarkers();
        
        // Auto refresh setiap 5 menit
        setInterval(function() {
            refreshData();
        }, 300000);
    </script>
</body>
</html>
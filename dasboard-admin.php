<?php include 'db.php'; 


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
    
    if ($temp >= 25 && $temp <= 30) {
        $risk_score += 3;
    } elseif ($temp >= 20 && $temp <= 35) {
        $risk_score += 2;
    } else {
        $risk_score += 1;
    }
    
    if ($humidity > 70) {
        $risk_score += 3;
    } elseif ($humidity > 60) {
        $risk_score += 2;
    } else {
        $risk_score += 1;
    }
    
    if ($rainfall > 100) {
        $risk_score += 3;
    } elseif ($rainfall > 50) {
        $risk_score += 2;
    } else {
        $risk_score += 1;
    }
    
    if ($population_density > 5000) {
        $risk_score += 3;
    } elseif ($population_density > 2000) {
        $risk_score += 2;
    } else {
        $risk_score += 1;
    }
    
    if ($risk_score >= 10) return 'Tinggi';
    elseif ($risk_score >= 7) return 'Sedang';
    else return 'Rendah';
}

// Data kecamatan di Pontianak
$regions = [
    [
        'name' => 'Pontianak Kota',
        'lat' => -0.0263,
        'lon' => 109.3425,
        'population_density' => 8500,
        'rainfall' => 185
    ],
    [
        'name' => 'Pontianak Selatan',
        'lat' => -0.0505,
        'lon' => 109.3176,
        'population_density' => 6200,
        'rainfall' => 175
    ],
    [
        'name' => 'Pontianak Timur',
        'lat' => -0.0196,
        'lon' => 109.3677,
        'population_density' => 4800,
        'rainfall' => 170
    ],
    [
        'name' => 'Pontianak Barat',
        'lat' => -0.0424,
        'lon' => 109.3040,
        'population_density' => 3900,
        'rainfall' => 180
    ],
    [
        'name' => 'Pontianak Tenggara',
        'lat' => 0.06752399700124746,
        'lon' => 109.3490268761129,
        'population_density' => 3700,
        'rainfall' => 195
    ],
    [
        'name' => 'Pontianak Utara',
        'lat' => 0.0069,
        'lon' => 109.3176,
        'population_density' => 2800,
        'rainfall' => 165
    ],    
    [
        'name' => 'Pontianak Barat Daya',
        'lat' => -0.03,
        'lon' => 109.35,
        'population_density' => 3100,
        'rainfall' => 160
    ]
];

// Mengambil data cuaca untuk setiap wilayah
foreach ($regions as &$region) {
    $weather = getWeatherData($region['lat'], $region['lon'], $openweather_api_key);
    
    if (isset($weather['main'])) {
        $region['temp'] = $weather['main']['temp'];
        $region['humidity'] = $weather['main']['humidity'];
    } else {
        $region['temp'] = rand(27, 33);
        $region['humidity'] = rand(75, 90);
    }
    
    $region['risk_level'] = calculateRiskLevel(
        $region['temp'], 
        $region['humidity'], 
        $region['rainfall'], 
        $region['population_density']
    );
}

// Mengambil data pasien dari database
$patients_query = "SELECT * FROM pasien ORDER BY id DESC";
$patients_result = $conn->query($patients_query);
$patients = [];
if ($patients_result->num_rows > 0) {
    while($row = $patients_result->fetch_assoc()) {
        $patients[] = $row;
    }
}

// Handle form submission untuk tambah/edit/hapus pasien
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $nama = $conn->real_escape_string($_POST['nama']);
                $jk = $conn->real_escape_string($_POST['jenis_kelamin']);
                $umur = intval($_POST['umur']);
                $alamat = $conn->real_escape_string($_POST['alamat']);
                $lat = floatval($_POST['latitude']);
                $lng = floatval($_POST['lngtidue']);
                
                $sql = "INSERT INTO pasien (nama, jk, umur, alamat, lat, lng, tanggal) VALUES ('$nama', '$jk', $umur, '$alamat', $lat, $lng, NOW())";
                if ($conn->query($sql) === TRUE) {
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $nama = $conn->real_escape_string($_POST['nama']);
                $jk = $conn->real_escape_string($_POST['jk']);
                $umur = intval($_POST['umur']);
                $alamat = $conn->real_escape_string($_POST['alamat']);
                $lat = floatval($_POST['lat']);
                $lng = floatval($_POST['lng']);
                
                $sql = "UPDATE pasien SET nama='$nama', jk='$jk', umur=$umur, alamat='$alamat', lat=$lat, lng=$lng WHERE id=$id";
                if ($conn->query($sql) === TRUE) {
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                $sql = "DELETE FROM pasien WHERE id=$id";
                if ($conn->query($sql) === TRUE) {
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }
                break;
        }
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Dashboard Admin</h1>
            <p>Sistem Monitoring Demam Berdarah - Kota Pontianak</p>
        </div>
        
        <!-- Tabel Data Pasien -->
        <div class="table-container">
            <div class="table-header">
                <h4><i class="fas fa-users"></i> Data Pasien DBD</h4>
                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addPatientModal">
                    <i class="fas fa-plus"></i> Tambah Pasien
                </button>
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
                                <button class="btn btn-sm btn-warning me-1" onclick="editPatient(<?php echo htmlspecialchars(json_encode($patient)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deletePatient(<?php echo $patient['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Peta dan Kontrol -->
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
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Pasien -->
    <div class="modal fade" id="addPatientModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Pasien Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" name="nama" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenis Kelamin</label>
                            <select class="form-control" name="jk" required>
                                <option value="">Pilih...</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Umur</label>
                            <input type="number" class="form-control" name="umur" required min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="alamat" rows="2" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Latitude</label>
                                <input type="number" step="any" class="form-control" name="lat" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Longitude</label>
                                <input type="number" step="any" class="form-control" name="lng" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-add">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Pasien -->
    <div class="modal fade" id="editPatientModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Pasien</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" name="nama" id="edit_nama" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenis Kelamin</label>
                            <select class="form-control" name="jk" id="edit_jk" required>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Umur</label>
                            <input type="number" class="form-control" name="umur" id="edit_umur" required min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="alamat" id="edit_alamat" rows="2" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Latitude</label>
                                <input type="number" step="any" class="form-control" name="lat" id="edit_lat" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Longitude</label>
                                <input type="number" step="any" class="form-control" name="lng" id="edit_lng" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Form Delete (Hidden) -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
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
                default: return '#6c757d';
            }
        }
        
        // Membuat choropleth untuk wilayah
        function createChoropleth() {
            choroplethLayer.clearLayers();
            
            regions.forEach(function(region) {
                var circle = L.circle([region.lat, region.lon], {
                    color: getRiskColor(region.risk_level),
                    fillColor: getRiskColor(region.risk_level),
                    fillOpacity: 0.5,
                    radius: 2000
                }).addTo(choroplethLayer);
                
                circle.bindPopup(`
                    <strong>${region.name}</strong><br>
                    Tingkat Risiko: <span style="color:${getRiskColor(region.risk_level)}; font-weight:bold">${region.risk_level}</span><br>
                    Suhu: ${region.temp}°C<br>
                    Kelembaban: ${region.humidity}%<br>
                    Curah Hujan: ${region.rainfall}mm<br>
                    Kepadatan Penduduk: ${region.population_density.toLocaleString()} jiwa/km²
                `);
            });
        }
        
        // Membuat markers untuk pasien dari database
        function createPatientMarkers() {
            patientsLayer.clearLayers();
            
            patients.forEach(function(patient, index) {
                var marker = L.marker([parseFloat(patient.lat), parseFloat(patient.lng)], {
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
                        <p style="margin: 5px 0;"><strong>JK:</strong> ${patient.jk}</p>
                        <p style="margin: 5px 0;"><strong>Umur:</strong> ${patient.umur} tahun</p>
                        <p style="margin: 5px 0;"><strong>Alamat:</strong> ${patient.alamat}</p>
                        <p style="margin: 5px 0;"><strong>Tanggal:</strong> ${new Date(patient.tanggal).toLocaleDateString('id-ID')}</p>
                        <p style="margin: 5px 0;"><strong>Koordinat:</strong> ${parseFloat(patient.lat).toFixed(4)}, ${parseFloat(patient.lng).toFixed(4)}</p>
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
        
        // Fungsi untuk edit pasien
        function editPatient(patient) {
            document.getElementById('edit_id').value = patient.id;
            document.getElementById('edit_nama').value = patient.nama;
            document.getElementById('edit_jk').value = patient.jk;
            document.getElementById('edit_umur').value = patient.umur;
            document.getElementById('edit_alamat').value = patient.alamat;
            document.getElementById('edit_lat').value = patient.lat;
            document.getElementById('edit_lng').value = patient.lng;
            
            var modal = new bootstrap.Modal(document.getElementById('editPatientModal'));
            modal.show();
        }
        
        // Fungsi untuk hapus pasien
        function deletePatient(id) {
            if (confirm('Apakah Anda yakin ingin menghapus data pasien ini?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
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
<?php include 'db.php';
// Memastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'];
$result = $conn->query("SELECT * FROM pasien WHERE id=$id");
$data = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'];
    $jk = $_POST['jenis_kelamin'];
    $umur = $_POST['umur'];
    $alamat = $_POST['alamat'];
    $lat = $_POST['latitude'];
    $lng = $_POST['longitude'];

    $stmt = $conn->prepare("UPDATE pasien SET nama=?, jenis_kelamin=?, umur=?, alamat=?, latitude=?, longitude=? WHERE id=?");
    $stmt->bind_param("ssissdi", $nama, $jk, $umur, $alamat, $lat, $lng, $id);
    $stmt->execute();

    header("Location: dasboard-admin.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pasien</title>
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
            padding: 10px 15px;
            display: block;
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
            padding: 20px;
            background: linear-gradient(135deg, #f0f0f0 0%, #ffffff 100%);
            overflow-y: auto;
        }
        
        .main-content-area {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        h3 {
            text-align: center;
            margin-bottom: 30px;
            color: #2c5530;
            font-weight: bold;
        }
        #map {
            height: 500px;
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .form-control {
            border-radius: 8px;
            border-color: #ced4da;
        }
        .form-control:focus {
            border-color: #1a7037;
            box-shadow: 0 0 0 0.25rem rgba(26, 112, 55, 0.25);
        }
        .btn-primary {
            background: linear-gradient(45deg, #2c5530, #1a7037);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            background: linear-gradient(45deg, #1a7037, #2c5530);
        }
        .btn-secondary {
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
        }
        .search-container {
            display: flex;
            margin-bottom: 15px;
        }
        .search-container .form-control {
            flex-grow: 1;
            margin-right: 10px;
        }
        .search-container .btn {
            background-color: #007bff;
            border-color: #007bff;
        }
        .search-container .btn:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
    </style>
</head>
<body>
    <div id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-user-shield"></i> Admin Panel</h2>
            <small>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></small>
        </div>
        <ul class="components">
            <li>
                <a href="dasboard-admin.php" class="sidebar-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard Overview
                </a>
            </li>
            <li>
                <a href="dasboard-admin.php#patient-data" class="sidebar-link active">
                    <i class="fas fa-users"></i> Data Pasien
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
            <div class="container-fluid">
                <h3><i class="fas fa-edit"></i> Edit Data Pasien</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="search-container">
                            <input type="text" id="addressSearch" class="form-control" placeholder="Cari alamat...">
                            <button class="btn btn-primary" onclick="searchAddress()"><i class="fas fa-search"></i> Cari</button>
                        </div>
                        <div id="map"></div>
                    </div>
                    <div class="col-md-6">
                        <form method="post">
                            <div class="mb-3">
                                <label for="nama" class="form-label">Nama Lengkap</label>
                                <input type="text" name="nama" id="nama" class="form-control" value="<?= htmlspecialchars($data['nama']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                                <select name="jenis_kelamin" id="jenis_kelamin" class="form-control" required>
                                    <option value="Laki-laki" <?= ($data['jenis_kelamin'] == 'Laki-laki') ? 'selected' : '' ?>>Laki-laki</option>
                                    <option value="Perempuan" <?= ($data['jenis_kelamin'] == 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="umur" class="form-label">Umur</label>
                                <input type="number" name="umur" id="umur" class="form-control" value="<?= htmlspecialchars($data['umur']) ?>" required min="0">
                            </div>
                            <div class="mb-3">
                                <label for="alamat" class="form-label">Alamat</label>
                                <textarea name="alamat" id="alamat" class="form-control" rows="3" required><?= htmlspecialchars($data['alamat']) ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="latitude" class="form-label">Latitude</label>
                                    <input type="number" step="any" name="latitude" id="latitude" class="form-control" value="<?= htmlspecialchars($data['latitude']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="longitude" class="form-label">Longitude</label>
                                    <input type="number" step="any" name="longitude" id="longitude" class="form-control" value="<?= htmlspecialchars($data['longitude']) ?>" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Update</button>
                            <a href="dasboard-admin.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    
    <script>
        // Initialize map with initial patient coordinates, or default to Pontianak
        var initialLat = parseFloat(document.getElementById('latitude').value);
        var initialLon = parseFloat(document.getElementById('longitude').value);
        
        var map = L.map('map').setView([initialLat || -0.0263, initialLon || 109.3425], 12); 
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        var marker;

        // Function to update marker and form fields
        function updateMarker(lat, lon) {
            if (marker) {
                map.removeLayer(marker);
            }
            marker = L.marker([lat, lon]).addTo(map);
            document.getElementById('latitude').value = lat.toFixed(6);
            document.getElementById('longitude').value = lon.toFixed(6);
            map.setView([lat, lon], map.getZoom());
        }

        // Click event on map to get coordinates
        map.on('click', function(e) {
            updateMarker(e.latlng.lat, e.latlng.lng);
        });

        // Function to search address using Nominatim
        function searchAddress() {
            var address = document.getElementById('addressSearch').value;
            if (address) {
                fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.length > 0) {
                            var lat = parseFloat(data[0].lat);
                            var lon = parseFloat(data[0].lon);
                            updateMarker(lat, lon);
                        } else {
                            alert('Alamat tidak ditemukan.');
                        }
                    })
                    .catch(error => {
                        console.error('Error searching address:', error);
                        alert('Terjadi kesalahan saat mencari alamat.');
                    });
            } else {
                alert('Silakan masukkan alamat untuk mencari.');
            }
        }

        // Set initial marker based on existing data
        if (!isNaN(initialLat) && !isNaN(initialLon)) {
            updateMarker(initialLat, initialLon);
        } else {
            // Fallback if data is somehow missing or invalid
            updateMarker(map.getCenter().lat, map.getCenter().lng);
        }
    </script>
</body>
</html>
<?php include 'db.php'; ?>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama   = $_POST['nama'];
    $jk     = $_POST['jenis_kelamin'];
    $umur   = $_POST['umur'];
    $alamat = $_POST['alamat'];
    $lat    = $_POST['latitude'];
    $lng    = $_POST['longitude'];

    $stmt = $conn->prepare("INSERT INTO pasien (nama, jenis_kelamin, umur, alamat, latitude, longitude, tanggal_lapor) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssissd", $nama, $jk, $umur, $alamat, $lat, $lng);
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
    <title>Tambah Pasien Baru</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c5530 0%, #1a7037 100%);
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container-fluid {
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
            height: 500px; /* Set a fixed height for the map */
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
    <div class="container-fluid mt-5">
        <h3><i class="fas fa-user-plus"></i> Tambah Pasien Baru</h3>
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
                        <input type="text" name="nama" id="nama" class="form-control" placeholder="Nama Lengkap" required>
                    </div>
                    <div class="mb-3">
                        <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                        <select name="jenis_kelamin" id="jenis_kelamin" class="form-control" required>
                            <option value="">Pilih Jenis Kelamin</option>
                            <option value="Laki-laki">Laki-laki</option>
                            <option value="Perempuan">Perempuan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="umur" class="form-label">Umur</label>
                        <input type="number" name="umur" id="umur" class="form-control" placeholder="Umur" required min="0">
                    </div>
                    <div class="mb-3">
                        <label for="alamat" class="form-label">Alamat</label>
                        <textarea name="alamat" id="alamat" class="form-control" placeholder="Alamat Lengkap" rows="3" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="latitude" class="form-label">Latitude</label>
                            <input type="number" step="any" name="latitude" id="latitude" class="form-control" placeholder="Latitude" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="longitude" class="form-label">Longitude</label>
                            <input type="number" step="any" name="longitude" id="longitude" class="form-control" placeholder="Longitude" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    <a href="dasboard-admin.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    
    <script>
        var map = L.map('map').setView([-0.0263, 109.3425], 12); // Default to Pontianak
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
                // Using Nominatim for geocoding
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

        // Initialize marker if latitude and longitude are already set (e.g., if editing)
        // For add.php, these fields will initially be empty, but this pattern is useful for edit.php
        var initialLat = parseFloat(document.getElementById('latitude').value);
        var initialLon = parseFloat(document.getElementById('longitude').value);
        if (!isNaN(initialLat) && !isNaN(initialLon)) {
            updateMarker(initialLat, initialLon);
        } else {
            // If no initial values, place a marker at the default map center
            updateMarker(map.getCenter().lat, map.getCenter().lng);
        }
    </script>
</body>
</html>
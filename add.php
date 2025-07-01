<?php include 'db.php'; ?>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama   = $_POST['nama'];
    $jk     = $_POST['jenis_kelamin'];
    $umur   = $_POST['umur'];
    $alamat = $_POST['alamat'];
    $lat    = $_POST['latitude'];
    $lng    = $_POST['longitude'];

    $stmt = $conn->prepare("INSERT INTO pasien (nama, jenis_kelamin, umur, alamat, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssissd", $nama, $jk, $umur, $alamat, $lat, $lng);
    $stmt->execute();

    header("Location: dasboard-admin.php");
    exit;
}
?>
<form method="post" class="container mt-5">
    <h3>Tambah Pasien</h3>
    <div class="mb-2">
        <input name="nama" class="form-control" placeholder="Nama" required>
    </div>
    <div class="mb-2">
        <select name="jenis_kelamin" class="form-control" required>
            <option value="">Pilih Jenis Kelamin</option>
            <option>Laki-laki</option>
            <option>Perempuan</option>
        </select>
    </div>
    <div class="mb-2">
        <input type="number" name="umur" class="form-control" placeholder="Umur" required>
    </div>
    <div class="mb-2">
        <input name="alamat" class="form-control" placeholder="Alamat" required>
    </div>
    <div class="mb-2">
        <input name="latitude" step="any" class="form-control" placeholder="Latitude" required>
    </div>
    <div class="mb-2">
        <input name="longitude" step="any" class="form-control" placeholder="Longitude" required>
    </div>
    <button class="btn btn-primary">Simpan</button>
    <a href="dasboard-admin.php" class="btn btn-secondary">Batal</a>
</form>

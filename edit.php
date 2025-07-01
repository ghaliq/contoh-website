<?php include 'db.php';
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

    header("Location: index.php");
    exit;
}
?>
<form method="post" class="container mt-5">
    <h3>Edit Pasien</h3>
    <input name="nama" class="form-control mb-2" value="<?= $data['nama'] ?>" required>
    <select name="jenis_kelamin" class="form-control mb-2">
        <option <?= $data['jenis_kelamin'] == 'Laki-laki' ? 'selected' : '' ?>>Laki-laki</option>
        <option <?= $data['jenis_kelamin'] == 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
    </select>
    <input type="number" name="umur" class="form-control mb-2" value="<?= $data['umur'] ?>" required>
    <input name="alamat" class="form-control mb-2" value="<?= $data['alamat'] ?>" required>
    <input name="latitude" class="form-control mb-2" value="<?= $data['latitude'] ?>" required>
    <input name="longitude" class="form-control mb-2" value="<?= $data['longitude'] ?>" required>
    <button class="btn btn-primary">Update</button>
    <a href="dasboard-admin.php" class="btn btn-secondary">Batal</a>
</form>

<?php
// Koneksi database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'bumdess';  // Sesuaikan nama database Anda
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Menangani Upload Gambar
if (isset($_POST['submit'])) {
    $nama = $_POST['nama'];
    $jabatan = $_POST['jabatan'];
    
    // Handle upload foto
    $foto = $_FILES['foto']['name'];
    $foto_tmp = $_FILES['foto']['tmp_name'];
    $foto_path = 'uploads/' . $foto;
    
    move_uploaded_file($foto_tmp, $foto_path);

    // Query untuk menambahkan data ke database
    $query = "INSERT INTO struktur_organisasi (nama, jabatan, foto) VALUES ('$nama', '$jabatan', '$foto_path')";
    if ($conn->query($query) === TRUE) {
        echo "Data berhasil ditambahkan";
    } else {
        echo "Error: " . $query . "<br>" . $conn->error;
    }
}

// Menghapus Data
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $query = "DELETE FROM struktur_organisasi WHERE id = $id";
    if ($conn->query($query) === TRUE) {
        echo "Data berhasil dihapus";
    } else {
        echo "Error: " . $conn->error;
    }
}

// Mengedit Data
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $nama = $_POST['nama'];
    $jabatan = $_POST['jabatan'];

    // Jika foto baru diupload
    if ($_FILES['foto']['name'] != '') {
        $foto = $_FILES['foto']['name'];
        $foto_tmp = $_FILES['foto']['tmp_name'];
        $foto_path = 'uploads/' . $foto;
        move_uploaded_file($foto_tmp, $foto_path);

        // Update dengan foto
        $query = "UPDATE struktur_organisasi SET nama = '$nama', jabatan = '$jabatan', foto = '$foto_path' WHERE id = $id";
    } else {
        // Update tanpa foto
        $query = "UPDATE struktur_organisasi SET nama = '$nama', jabatan = '$jabatan' WHERE id = $id";
    }
    
    if ($conn->query($query) === TRUE) {
        echo "Data berhasil diupdate";
    } else {
        echo "Error: " . $conn->error;
    }
}

// Ambil semua data lalu simpan dalam array
$result = $conn->query("SELECT * FROM struktur_organisasi");
$data_anggota = [];
while ($row = $result->fetch_assoc()) {
    $data_anggota[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Struktur Organisasi BUMDes</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
</head>
<body>

<div class="container mt-4">
    <h2>Struktur Organisasi BUMDes</h2>

    <!-- Form Tambah -->
    <form action="admin/struktur.php" method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="nama" class="form-label">Nama</label>
            <input type="text" class="form-control" id="nama" name="nama" required>
        </div>
        <div class="mb-3">
            <label for="jabatan" class="form-label">Jabatan</label>
            <input type="text" class="form-control" id="jabatan" name="jabatan" required>
        </div>
        <div class="mb-3">
            <label for="foto" class="form-label">Foto</label>
            <input type="file" class="form-control" id="foto" name="foto" required>
        </div>
        <button type="submit" name="submit" class="btn btn-success">Tambah</button>
    </form>

    <hr>

    <!-- Tabel Anggota -->
    <h3>Daftar Anggota</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Jabatan</th>
                <th>Foto</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data_anggota as $row): ?>
                <tr>
                    <td><?php echo $row['nama']; ?></td>
                    <td><?php echo $row['jabatan']; ?></td>
                    <td><img src="<?php echo $row['foto']; ?>" alt="Foto <?php echo $row['nama']; ?>" width="100"></td>
                    <td>
                        <a href="admin/struktur.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">Edit</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Edit -->
<?php foreach ($data_anggota as $row): ?>
<div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">Edit Anggota</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <form action="admin/struktur.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
            <div class="mb-3">
                <label for="nama" class="form-label">Nama</label>
                <input type="text" class="form-control" name="nama" value="<?php echo $row['nama']; ?>" required>
            </div>
            <div class="mb-3">
                <label for="jabatan" class="form-label">Jabatan</label>
                <input type="text" class="form-control" name="jabatan" value="<?php echo $row['jabatan']; ?>" required>
            </div>
            <div class="mb-3">
                <label for="foto" class="form-label">Foto (Opsional)</label>
                <input type="file" class="form-control" name="foto">
            </div>
            <button type="submit" name="update" class="btn btn-primary">Update</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

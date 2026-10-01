<?php
session_start();
include('../../../koneksi/koneksi.php');

if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php');
    exit;
}
$user_id = $_SESSION['pengguna_id'];
// Ambil data pengguna (untuk mendapatkan nama lengkap)
$query_pengguna = "SELECT nama FROM pengguna WHERE id = ?";
$stmt_pengguna = mysqli_prepare($conn, $query_pengguna);
mysqli_stmt_bind_param($stmt_pengguna, 'i', $user_id);
mysqli_stmt_execute($stmt_pengguna);
$result_pengguna = mysqli_stmt_get_result($stmt_pengguna);
$pengguna_data = mysqli_fetch_assoc($result_pengguna);

if (!$pengguna_data) {
    echo "<script>alert('Data pengguna tidak ditemukan.'); window.location.href='../../../logout.php';</script>";
    exit;
}
// Ambil data penjual termasuk foto
$query_penjual = "SELECT nama_toko, nama_pemilik, email, username, foto, alamat, nomor_telepon, tanggal_lahir, jenis_kelamin FROM penjual WHERE pengguna_id = ?";
$stmt_penjual = mysqli_prepare($conn, $query_penjual);
mysqli_stmt_bind_param($stmt_penjual, 'i', $user_id);
mysqli_stmt_execute($stmt_penjual);
$result_penjual = mysqli_stmt_get_result($stmt_penjual);
$penjual = mysqli_fetch_assoc($result_penjual);
if (!$penjual) {
    echo "<script>alert('Data penjual tidak ditemukan.'); window.location.href='../../../logout.php';</script>";
    exit;
}
// Proses update profil
if (isset($_POST['simpan'])) {
    $nama_lengkap = $_POST['nama_lengkap']; // Ambil nilai nama lengkap dari form
    $nama_toko = $_POST['nama_toko'];
    $nama_pemilik = $_POST['nama_pemilik'];
    $email_penjual = $_POST['email_penjual'];
    $username_penjual = $_POST['username_penjual'];
    $alamat = $_POST['alamat'];
    $nomor_telepon = $_POST['nomor_telepon'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $foto_lama = $penjual['foto'];
    $foto_baru = $foto_lama; // Default jika tidak ada upload baru
    $upload_dir = '../../../img/foto/';
    // Proses upload foto
    if ($_FILES['foto']['name']) {
        $foto_name = basename($_FILES['foto']['name']);
        $target = $upload_dir . $foto_name;
        $ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
                if ($foto_lama && $foto_lama != 'default.png' && file_exists($upload_dir . $foto_lama)) {
                    unlink($upload_dir . $foto_lama);
                }
                $foto_baru = $foto_name;
            } else {
                echo "<script>alert('Gagal mengupload foto profil.');</script>";
            }
        } else {
            echo "<script>alert('Ekstensi file foto profil tidak diizinkan.');</script>";
        }
    }
    // Update data di tabel penjual
    $update_penjual = "UPDATE penjual SET nama_toko=?, nama_pemilik=?, email=?, username=?, foto=?, alamat=?, nomor_telepon=?, tanggal_lahir=?, jenis_kelamin=?, updated_at=NOW() WHERE pengguna_id=?";
    $stmt_update_penjual = mysqli_prepare($conn, $update_penjual);
    mysqli_stmt_bind_param($stmt_update_penjual, 'sssssssssi', $nama_toko, $nama_pemilik, $email_penjual, $username_penjual, $foto_baru, $alamat, $nomor_telepon, $tanggal_lahir, $jenis_kelamin, $user_id);
    mysqli_stmt_execute($stmt_update_penjual);
    // Update data nama di tabel pengguna
    $update_pengguna = "UPDATE pengguna SET nama=? WHERE id=?";
    $stmt_update_pengguna = mysqli_prepare($conn, $update_pengguna);
    mysqli_stmt_bind_param($stmt_update_pengguna, 'si', $nama_lengkap, $user_id);
    mysqli_stmt_execute($stmt_update_pengguna);
    if (mysqli_stmt_affected_rows($stmt_update_penjual) > 0 || mysqli_stmt_affected_rows($stmt_update_pengguna) > 0) {
        echo "<script>alert('Profil berhasil diperbarui.'); window.location.href='../../dashboard_penjual.php';</script>";
    } else {
        echo "<script>alert('Tidak ada perubahan pada profil.'); window.location.href='../../dashboard_penjual.php';</script>";
    }
    mysqli_stmt_close($stmt_update_penjual);
    mysqli_stmt_close($stmt_update_pengguna);
}
// Fungsi untuk mendapatkan semua warna
function getWarna($conn) {
    $result = mysqli_query($conn, "SELECT * FROM warna ORDER BY nama_warna ASC");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
// Fungsi untuk mendapatkan semua ukuran
function getUkuran($conn) {
    $result = mysqli_query($conn, "SELECT * FROM ukuran ORDER BY nama_ukuran ASC");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
// Fungsi untuk mendapatkan semua rasa
function getRasa($conn) {
    $result = mysqli_query($conn, "SELECT * FROM rasa ORDER BY nama_rasa ASC");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
// Fungsi untuk mendapatkan semua produk (sederhana)
function getProduk($conn) {
    $result = mysqli_query($conn, "SELECT id, nama FROM produk ORDER BY nama ASC");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
// Fungsi untuk mendapatkan varian produk berdasarkan ID varian
function getVarianProdukById($conn, $id_varian) {
    $query = "SELECT pv.id, pv.produk_id, pv.warna_id, pv.ukuran_id, pv.rasa_id, pv.stok, pv.harga, pv.gambar
              FROM produk_variasi pv
              WHERE pv.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $id_varian);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}
// Fungsi untuk mendapatkan varian produk berdasarkan produk ID (dengan nama warna dan ukuran)
function getVarianProduk($conn, $produk_id) {
    $query = "SELECT pv.id, w.nama_warna, u.nama_ukuran, r.nama_rasa, pv.stok, pv.harga, pv.gambar, pv.warna_id, pv.ukuran_id, pv.rasa_id
              FROM produk_variasi pv
              LEFT JOIN warna w ON pv.warna_id = w.id
              LEFT JOIN ukuran u ON pv.ukuran_id = u.id
              LEFT JOIN rasa r ON pv.rasa_id = r.id
              WHERE pv.produk_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $produk_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
$daftar_warna = getWarna($conn);
$daftar_ukuran = getUkuran($conn);
$daftar_rasa = getRasa($conn);
$daftar_produk = getProduk($conn);
$pesan = "";
// Proses Tambah Warna (tetap sama)
if (isset($_POST['tambah_warna'])) {
    $nama_warna = trim($_POST['nama_warna']);
    if (!empty($nama_warna)) {
        $query_cek = "SELECT id FROM warna WHERE nama_warna = ?";
        $stmt_cek = mysqli_prepare($conn, $query_cek);
        mysqli_stmt_bind_param($stmt_cek, 's', $nama_warna);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);
        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $pesan = "<div class='alert alert-danger'>Warna sudah ada.</div>";
        } else {
            $query_tambah = "INSERT INTO warna (nama_warna) VALUES (?)";
            $stmt_tambah = mysqli_prepare($conn, $query_tambah);
            mysqli_stmt_bind_param($stmt_tambah, 's', $nama_warna);
            if (mysqli_stmt_execute($stmt_tambah)) {
                $pesan = "<div class='alert alert-success'>Warna berhasil ditambahkan.</div>";
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal menambahkan warna.</div>";
            }
            mysqli_stmt_close($stmt_tambah);
        }
        mysqli_stmt_close($stmt_cek);
    } else {
        $pesan = "<div class='alert alert-warning'>Nama warna tidak boleh kosong.</div>";
    }
}

// Proses Edit Warna
if (isset($_POST['edit_warna'])) {
    $id_warna = $_POST['id_warna_edit'];
    $nama_warna_baru = trim($_POST['nama_warna_edit']);
    if (!empty($nama_warna_baru)) {
        $query_cek = "SELECT id FROM warna WHERE nama_warna = ? AND id != ?";
        $stmt_cek = mysqli_prepare($conn, $query_cek);
        mysqli_stmt_bind_param($stmt_cek, 'si', $nama_warna_baru, $id_warna);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);
        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $pesan = "<div class='alert alert-danger'>Warna sudah ada.</div>";
        } else {
            $query_edit = "UPDATE warna SET nama_warna = ? WHERE id = ?";
            $stmt_edit = mysqli_prepare($conn, $query_edit);
            mysqli_stmt_bind_param($stmt_edit, 'si', $nama_warna_baru, $id_warna);
            if (mysqli_stmt_execute($stmt_edit)) {
                $pesan = "<div class='alert alert-success'>Warna berhasil diubah.</div>";
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal mengubah warna.</div>";
            }
            mysqli_stmt_close($stmt_edit);
        }
        mysqli_stmt_close($stmt_cek);
    } else {
        $pesan = "<div class='alert alert-warning'>Nama warna tidak boleh kosong.</div>";
    }
}

// Proses Hapus Warna
if (isset($_GET['hapus_warna'])) {
    $id_warna_hapus = $_GET['hapus_warna'];
    $query_cek_penggunaan = "SELECT id FROM produk_variasi WHERE warna_id = ?";
    $stmt_cek_penggunaan = mysqli_prepare($conn, $query_cek_penggunaan);
    mysqli_stmt_bind_param($stmt_cek_penggunaan, 'i', $id_warna_hapus);
    mysqli_stmt_execute($stmt_cek_penggunaan);
    mysqli_stmt_store_result($stmt_cek_penggunaan);
    if (mysqli_stmt_num_rows($stmt_cek_penggunaan) > 0) {
        $pesan = "<div class='alert alert-danger'>Warna tidak dapat dihapus karena masih digunakan oleh variasi produk.</div>";
    } else {
        $query_hapus = "DELETE FROM warna WHERE id = ?";
        $stmt_hapus = mysqli_prepare($conn, $query_hapus);
        mysqli_stmt_bind_param($stmt_hapus, 'i', $id_warna_hapus);
        if (mysqli_stmt_execute($stmt_hapus)) {
            $pesan = "<div class='alert alert-success'>Warna berhasil dihapus.</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menghapus warna.</div>";
        }
        mysqli_stmt_close($stmt_hapus);
    }
    mysqli_stmt_close($stmt_cek_penggunaan);
}

// Proses Tambah Ukuran
if (isset($_POST['tambah_ukuran'])) {
    $nama_ukuran = trim($_POST['nama_ukuran']);
    $singkatan_ukuran = trim($_POST['singkatan_ukuran']);
    if (!empty($nama_ukuran)) {
        $query_cek = "SELECT id FROM ukuran WHERE nama_ukuran = ?";
        $stmt_cek = mysqli_prepare($conn, $query_cek);
        mysqli_stmt_bind_param($stmt_cek, 's', $nama_ukuran);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);
        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $pesan = "<div class='alert alert-danger'>Ukuran sudah ada.</div>";
        } else {
            $query_tambah = "INSERT INTO ukuran (nama_ukuran, singkatan) VALUES (?, ?)";
            $stmt_tambah = mysqli_prepare($conn, $query_tambah);
            mysqli_stmt_bind_param($stmt_tambah, 'ss', $nama_ukuran, $singkatan_ukuran);
            if (mysqli_stmt_execute($stmt_tambah)) {
                $pesan = "<div class='alert alert-success'>Ukuran berhasil ditambahkan.</div>";
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal menambahkan ukuran.</div>";
            }
            mysqli_stmt_close($stmt_tambah);
        }
        mysqli_stmt_close($stmt_cek);
    } else {
        $pesan = "<div class='alert alert-warning'>Nama ukuran tidak boleh kosong.</div>";
    }
}
// Proses Edit Ukuran
if (isset($_POST['edit_ukuran'])) {
    $id_ukuran = $_POST['id_ukuran_edit'];
    $nama_ukuran_baru = trim($_POST['nama_ukuran_edit']);
    $singkatan_ukuran_baru = trim($_POST['singkatan_ukuran_edit']);
    if (!empty($nama_ukuran_baru)) {
        $query_cek = "SELECT id FROM ukuran WHERE nama_ukuran = ? AND id != ?";
        $stmt_cek = mysqli_prepare($conn, $query_cek);
        mysqli_stmt_bind_param($stmt_cek, 'si', $nama_ukuran_baru, $id_ukuran);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);
        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $pesan = "<div class='alert alert-danger'>Ukuran sudah ada.</div>";
        } else {
            $query_edit = "UPDATE ukuran SET nama_ukuran = ?, singkatan = ? WHERE id = ?";
            $stmt_edit = mysqli_prepare($conn, $query_edit);
            mysqli_stmt_bind_param($stmt_edit, 'ssi', $nama_ukuran_baru, $singkatan_ukuran_baru, $id_ukuran);
            if (mysqli_stmt_execute($stmt_edit)) {
                $pesan = "<div class='alert alert-success'>Ukuran berhasil diubah.</div>";
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal mengubah ukuran.</div>";
            }
            mysqli_stmt_close($stmt_edit);
        }
        mysqli_stmt_close($stmt_cek);
    } else {
        $pesan = "<div class='alert alert-warning'>Nama ukuran tidak boleh kosong.</div>";
    }
}

// Proses Hapus Ukuran
if (isset($_GET['hapus_ukuran'])) {
    $id_ukuran_hapus = $_GET['hapus_ukuran'];
    $query_cek_penggunaan = "SELECT id FROM produk_variasi WHERE ukuran_id = ?";
    $stmt_cek_penggunaan = mysqli_prepare($conn, $query_cek_penggunaan);
    mysqli_stmt_bind_param($stmt_cek_penggunaan, 'i', $id_ukuran_hapus);
    mysqli_stmt_execute($stmt_cek_penggunaan);
    mysqli_stmt_store_result($stmt_cek_penggunaan);
    if (mysqli_stmt_num_rows($stmt_cek_penggunaan) > 0) {
        $pesan = "<div class='alert alert-danger'>Ukuran tidak dapat dihapus karena masih digunakan oleh variasi produk.</div>";
    } else {
        $query_hapus = "DELETE FROM ukuran WHERE id = ?";
        $stmt_hapus = mysqli_prepare($conn, $query_hapus);
        mysqli_stmt_bind_param($stmt_hapus, 'i', $id_ukuran_hapus);
        if (mysqli_stmt_execute($stmt_hapus)) {
            $pesan = "<div class='alert alert-success'>Ukuran berhasil dihapus.</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menghapus ukuran.</div>";
        }
        mysqli_stmt_close($stmt_hapus);
    }
    mysqli_stmt_close($stmt_cek_penggunaan);
}

// Proses Tambah Rasa
if (isset($_POST['tambah_rasa'])) {
    $nama_rasa = trim($_POST['nama_rasa']);
    if (!empty($nama_rasa)) {
        $query_cek = "SELECT id FROM rasa WHERE nama_rasa = ?";
        $stmt_cek = mysqli_prepare($conn, $query_cek);
        mysqli_stmt_bind_param($stmt_cek, 's', $nama_rasa);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);
        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $pesan = "<div class='alert alert-danger'>Rasa sudah ada.</div>";
        } else {
            $query_tambah = "INSERT INTO rasa (nama_rasa) VALUES (?)";
            $stmt_tambah = mysqli_prepare($conn, $query_tambah);
            mysqli_stmt_bind_param($stmt_tambah, 's', $nama_rasa);
            if (mysqli_stmt_execute($stmt_tambah)) {
                $pesan = "<div class='alert alert-success'>Rasa berhasil ditambahkan.</div>";
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal menambahkan rasa.</div>";
            }
            mysqli_stmt_close($stmt_tambah);
        }
        mysqli_stmt_close($stmt_cek);
    } else {
        $pesan = "<div class='alert alert-warning'>Nama rasa tidak boleh kosong.</div>";
    }
}

// Proses Edit Rasa
if (isset($_POST['edit_rasa'])) {
    $id_rasa = $_POST['id_rasa_edit'];
    $nama_rasa_baru = trim($_POST['nama_rasa_edit']);
    if (!empty($nama_rasa_baru)) {
        $query_cek = "SELECT id FROM rasa WHERE nama_rasa = ? AND id != ?";
        $stmt_cek = mysqli_prepare($conn, $query_cek);
        mysqli_stmt_bind_param($stmt_cek, 'si', $nama_rasa_baru, $id_rasa);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);
        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $pesan = "<div class='alert alert-danger'>Rasa sudah ada.</div>";
        } else {
            $query_edit = "UPDATE rasa SET nama_rasa = ? WHERE id = ?";
            $stmt_edit = mysqli_prepare($conn, $query_edit);
            mysqli_stmt_bind_param($stmt_edit, 'si', $nama_rasa_baru, $id_rasa);
            if (mysqli_stmt_execute($stmt_edit)) {
                $pesan = "<div class='alert alert-success'>Rasa berhasil diubah.</div>";
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal mengubah rasa.</div>";
            }
            mysqli_stmt_close($stmt_edit);
        }
        mysqli_stmt_close($stmt_cek);
    } else {
        $pesan = "<div class='alert alert-warning'>Nama rasa tidak boleh kosong.</div>";
    }
}

// Proses Hapus Rasa
if (isset($_GET['hapus_rasa'])) {
    $id_rasa_hapus = $_GET['hapus_rasa'];
    $query_cek_penggunaan = "SELECT id FROM produk_variasi WHERE rasa_id = ?";
    $stmt_cek_penggunaan = mysqli_prepare($conn, $query_cek_penggunaan);
    mysqli_stmt_bind_param($stmt_cek_penggunaan, 'i', $id_rasa_hapus);
    mysqli_stmt_execute($stmt_cek_penggunaan);
    mysqli_stmt_store_result($stmt_cek_penggunaan);
    if (mysqli_stmt_num_rows($stmt_cek_penggunaan) > 0) {
        $pesan = "<div class='alert alert-danger'>Rasa tidak dapat dihapus karena masih digunakan oleh variasi produk.</div>";
    } else {
        $query_hapus = "DELETE FROM rasa WHERE id = ?";
        $stmt_hapus = mysqli_prepare($conn, $query_hapus);
        mysqli_stmt_bind_param($stmt_hapus, 'i', $id_rasa_hapus);
        if (mysqli_stmt_execute($stmt_hapus)) {
            $pesan = "<div class='alert alert-success'>Rasa berhasil dihapus.</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menghapus rasa.</div>";
        }
        mysqli_stmt_close($stmt_hapus);
    }
    mysqli_stmt_close($stmt_cek_penggunaan);
}

// Proses Tambah Varian Produk
if (isset($_POST['tambah_varian_produk'])) {
    $produk_id_varian = $_POST['produk_id_varian'];
    $warna_id_varian = $_POST['warna_id_varian'] == 0 ? null : $_POST['warna_id_varian'];
    $ukuran_id_varian = $_POST['ukuran_id_varian'] == 0 ? null : $_POST['ukuran_id_varian'];
    $rasa_id_varian = $_POST['rasa_id_varian'] == 0 ? null : $_POST['rasa_id_varian'];
    $stok_varian = $_POST['stok_varian'];
    $harga_varian = $_POST['harga_varian'];
    $gambar_varian = "";

    // Upload gambar varian jika ada
    $upload_dir = '../../../img/produk/';
    if ($_FILES['gambar_varian']['name']) {
        $gambar_name = uniqid() . '_' . basename($_FILES['gambar_varian']['name']); // Tambahkan uniqid
        $target = $upload_dir . $gambar_name;
        $ext = strtolower(pathinfo($gambar_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'avif'];

        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($_FILES['gambar_varian']['tmp_name'], $target)) {
                $gambar_varian = $gambar_name;
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal mengupload gambar varian.</div>";
            }
        } else {
            $pesan = "<div class='alert alert-warning'>Ekstensi file gambar varian tidak diizinkan.</div>";
        }
    }

    // Cek apakah kombinasi varian sudah ada untuk produk ini
    $query_cek_varian = "SELECT id FROM produk_variasi WHERE produk_id = ? AND warna_id <=> ? AND ukuran_id <=> ? AND rasa_id <=> ?";
    $stmt_cek_varian = mysqli_prepare($conn, $query_cek_varian);
    mysqli_stmt_bind_param($stmt_cek_varian, 'iiii', $produk_id_varian, $warna_id_varian, $ukuran_id_varian, $rasa_id_varian);
    mysqli_stmt_execute($stmt_cek_varian);
    mysqli_stmt_store_result($stmt_cek_varian);

    if (mysqli_stmt_num_rows($stmt_cek_varian) > 0) {
        $pesan = "<div class='alert alert-danger'>Kombinasi varian untuk produk ini sudah ada.</div>";
    } else {
        $query_tambah_varian = "INSERT INTO produk_variasi (produk_id, warna_id, ukuran_id, rasa_id, stok, harga, gambar) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_tambah_varian = mysqli_prepare($conn, $query_tambah_varian);
        $tipe_stok = (is_int($stok_varian)) ? 'i' : 'd';
        $format_string = 'iiii' . $tipe_stok . 'ds';
        mysqli_stmt_bind_param($stmt_tambah_varian, $format_string, $produk_id_varian, $warna_id_varian, $ukuran_id_varian, $rasa_id_varian, $stok_varian, $harga_varian, $gambar_varian);

        if (mysqli_stmt_execute($stmt_tambah_varian)) {
            $pesan = "<div class='alert alert-success'>Varian produk berhasil ditambahkan.</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menambahkan varian produk.</div>";
        }
        mysqli_stmt_close($stmt_tambah_varian);
    }
    mysqli_stmt_close($stmt_cek_varian);
}
// Proses Edit Varian Produk
if (isset($_POST['edit_varian_produk'])) {
    $id_varian_edit = $_POST['id_varian_edit'];
    $produk_id_edit = $_POST['produk_id_edit'];
    $warna_id_edit = $_POST['warna_id_edit'] == 0 ? null : $_POST['warna_id_edit'];
    $ukuran_id_edit = $_POST['ukuran_id_edit'] == 0 ? null : $_POST['ukuran_id_edit'];
    $rasa_id_edit = $_POST['rasa_id_edit'] == 0 ? null : $_POST ['rasa_id_edit'];
    $stok_edit = $_POST['stok_edit'];
    $harga_edit = $_POST['harga_edit'];
    $gambar_lama_edit = isset($_POST['gambar_lama_edit']) ? $_POST['gambar_lama_edit'] : ''; // Periksa apakah index ada
    $gambar_baru_edit = "";
    $upload_error = false;

    // Proses upload gambar baru jika ada
    $upload_dir = '../../../img/produk/';
    if ($_FILES['gambar_edit']['name']) {
        $gambar_name = uniqid() . '_' . basename($_FILES['gambar_edit']['name']); // Tambahkan uniqid
        $target = $upload_dir . $gambar_name;
        $ext = strtolower(pathinfo($gambar_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif']; // Tambahkan 'avif' sesuai kode Anda

        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($_FILES['gambar_edit']['tmp_name'], $target)) {
                $gambar_baru_edit = $gambar_name;
                // Hapus gambar lama jika ada dan berbeda
                if (!empty($gambar_lama_edit) && $gambar_lama_edit != $gambar_baru_edit && file_exists($upload_dir . $gambar_lama_edit)) {
                    unlink($upload_dir . $gambar_lama_edit);
                }
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal mengupload gambar baru varian.</div>";
                $upload_error = true;
            }
        } else {
            $pesan = "<div class='alert alert-warning'>Ekstensi file gambar baru varian tidak diizinkan.</div>";
            $upload_error = true;
        }
    } else {
        $gambar_baru_edit = $gambar_lama_edit; // Gunakan gambar lama jika tidak ada yang baru diupload
    }

    if (!$upload_error) {
        $query_update_varian = "UPDATE produk_variasi SET produk_id = ?, warna_id = ?, ukuran_id = ?, rasa_id = ?, stok = ?, harga = ?, gambar = ? WHERE id = ?";
        $stmt_update_varian = mysqli_prepare($conn, $query_update_varian);
        $tipe_stok_edit = (is_int($stok_edit)) ? 'i' : 'd';
        $format_string_edit = 'iiii' . $tipe_stok_edit . 'ssi';
        mysqli_stmt_bind_param($stmt_update_varian, $format_string_edit, $produk_id_edit, $warna_id_edit, $ukuran_id_edit, $rasa_id_edit, $stok_edit, $harga_edit, $gambar_baru_edit, $id_varian_edit);

        if (mysqli_stmt_execute($stmt_update_varian)) {
            $pesan = "<div class='alert alert-success'>Varian produk berhasil diubah.</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal mengubah varian produk.</div>";
        }
        mysqli_stmt_close($stmt_update_varian);
    }
}
// Proses Hapus Varian Produk
if (isset($_GET['hapus_varian'])) {
    $id_varian_hapus = $_GET['hapus_varian'];
    $query_hapus_varian = "DELETE FROM produk_variasi WHERE id = ?";
    $stmt_hapus_varian = mysqli_prepare($conn, $query_hapus_varian);
    mysqli_stmt_bind_param($stmt_hapus_varian, 'i', $id_varian_hapus);
    if (mysqli_stmt_execute($stmt_hapus_varian)) {
        $pesan = "<div class='alert alert-success'>Varian produk berhasil dihapus.</div>";
    } else {
        $pesan = "<div class='alert alert-danger'>Gagal menghapus varian produk.</div>";
    }
    mysqli_stmt_close($stmt_hapus_varian);
}
// --- Konfigurasi Pagination ---
$batas = 10; // Jumlah item per halaman

// --- Pagination Warna ---
$halaman_warna = isset($_GET['halaman_warna']) && is_numeric($_GET['halaman_warna']) ? intval($_GET['halaman_warna']) : 1;
$offset_warna = ($halaman_warna - 1) * $batas;

$total_warna_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM warna");
$total_warna_data = mysqli_fetch_assoc($total_warna_result);
$total_warna = $total_warna_data['total'];
$jumlah_halaman_warna = ceil($total_warna / $batas);

$query_warna = "SELECT * FROM warna ORDER BY nama_warna ASC LIMIT $batas OFFSET $offset_warna";
$result_warna = mysqli_query($conn, $query_warna);
$daftar_warna = mysqli_fetch_all($result_warna, MYSQLI_ASSOC);

// --- Pagination Ukuran ---
$halaman_ukuran = isset($_GET['halaman_ukuran']) && is_numeric($_GET['halaman_ukuran']) ? intval($_GET['halaman_ukuran']) : 1;
$offset_ukuran = ($halaman_ukuran - 1) * $batas;

$total_ukuran_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM ukuran");
$total_ukuran_data = mysqli_fetch_assoc($total_ukuran_result);
$total_ukuran = $total_ukuran_data['total'];
$jumlah_halaman_ukuran = ceil($total_ukuran / $batas);

$query_ukuran = "SELECT * FROM ukuran ORDER BY nama_ukuran ASC LIMIT $batas OFFSET $offset_ukuran";
$result_ukuran = mysqli_query($conn, $query_ukuran);
$daftar_ukuran = mysqli_fetch_all($result_ukuran, MYSQLI_ASSOC);

// --- Pagination Rasa ---
$halaman_rasa = isset($_GET['halaman_rasa']) && is_numeric($_GET['halaman_rasa']) ? intval($_GET['halaman_rasa']) : 1;
$offset_rasa = ($halaman_rasa - 1) * $batas;

$total_rasa_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM rasa");
$total_rasa_data = mysqli_fetch_assoc($total_rasa_result);
$total_rasa = $total_rasa_data['total'];
$jumlah_halaman_rasa = ceil($total_rasa / $batas);

$query_rasa = "SELECT * FROM rasa ORDER BY nama_rasa ASC LIMIT $batas OFFSET $offset_rasa";
$result_rasa = mysqli_query($conn, $query_rasa);
$daftar_rasa = mysqli_fetch_all($result_rasa, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Penjual</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background-color: #f2f6fc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .wrapper {
            display: flex;
            flex: 1;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #1f2937;
            min-height: 100vh;
            padding: 20px 0;
            color: white;
            transition: width 0.3s ease;
        }
        .sidebar.collapsed {
            width: 80px;
        }
        .sidebar h4 {
            text-align: center;
            color: #ffffff;
            margin-bottom: 30px;
        }
        .sidebar a {
            color: #dcdcdc;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .sidebar a:hover,
        .sidebar .nav-link:hover {
            background-color: #495057;
            color: #ffffff;
            text-decoration: none;
        }
        .sidebar .nav-item {
            list-style: none;
        }
        .sidebar .submenu {
            font-size: 0.9rem;
            padding-left: 40px;
            color: #cfcfcf;
        }
        .sidebar .submenu:hover {
            color: #ffffff;
        }
        .sidebar .menu-text {
            margin-left: 10px;
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        .sidebar.collapsed .submenu {
            display: none;
        }
        .sidebar.collapsed a span {
            display: none;
        }
        .content {
            flex-grow: 1;
            padding: 30px;
            transition: margin-left 0.3s;
        }
        .toggle-btn {
            background: none;
            border: none;
            color: white;
            margin-left: 20px;
            font-size: 20px;
        }

        .card-dashboard {
            border-radius: 10px;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
            }
            .sidebar.collapsed {
                width: 100%;
            }
            .sidebar.collapsed a span {
                display: inline;
            }
        }
        .profile-section {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .profile-icon img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
        }
        .profile-menu {
            position: absolute;
            top: 60px;
            right: 0;
            background: white;
            padding: 15px;
            width: 250px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
        }
        .profile-menu h5 {
            margin-top: 0;
        }
        .profile-menu p {
            margin: 0;
        }
        .profile-menu a {
            display: block;
            margin-top: 10px;
            color: #007bff;
            text-decoration: none;
        }
        .profile-menu a:hover {
            text-decoration: underline;
        }
        .form-edit-profil {
            margin-top: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
        }
        .navbar-nav img {
            width: 45px;
            height: 45px;
            object-fit: cover;
        }
        .custom-card {
        height: 80px;        /* tinggi card */
        width: 59%;          /* lebar penuh kolom */
        padding: 10px 5px;    /* atas-bawah 15px, kiri-kanan 10px */
        margin: 3px 5px;      /* jarak luar card */
        }
        #salesChart {
            width: 100% !important;
            max-width: 600px !important;
            height: 300px !important;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand ml-3" href="#">BUMDes Sinar Petir</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img src="../../../img/foto/<?= $penjual['foto'] ?: 'default.png' ?>" alt="Foto Profil Penjual" class="rounded-circle mr-2" width="40" height="40">
                <span class="d-none d-md-inline text-white">Profil</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-3 text-center" aria-labelledby="navbarDropdown">
                <div class="profile-icon mb-2">
                    <img src="../../../img/foto/<?= $penjual['foto'] ?: 'default.png' ?>" alt="Profil Penjual" width="80" height="80" style="object-fit: cover;">
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($penjual['nama_pemilik']); ?></h5>
                <p class="mb-0 small">Username Toko: <?= htmlspecialchars($penjual['username']); ?></p>
                <p class="mb-0 small">Email Toko: <?= htmlspecialchars($penjual['email']); ?></p>
                <p class="mb-0 small">Nama Toko: <?= htmlspecialchars($penjual['nama_toko']); ?></p>
                <p class="mb-0 small">Nomor Telepon: <?= htmlspecialchars($penjual['nomor_telepon']); ?></p>
                <div class="dropdown-divider my-2"></div>
                <a class="btn btn-link text-primary p-0 d-block mb-1" href="#" data-toggle="modal" data-target="#editProfilModal">Edit Profil</a>
                <a class="btn btn-link text-danger p-0 d-block" href="../../../logout.php">Logout</a>
            </div>
        </li>
    </ul>
</nav>
<div class="modal fade" id="editProfilModal" tabindex="-1" aria-labelledby="editProfilModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfilModalLabel">Edit Profil</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($pengguna_data['nama']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Toko</label>
                        <input type="text" name="nama_toko" class="form-control" value="<?= htmlspecialchars($penjual['nama_toko']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Pemilik</label>
                        <input type="text" name="nama_pemilik" class="form-control" value="<?= htmlspecialchars($penjual['nama_pemilik']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Toko</label>
                        <input type="email" name="email_penjual" class="form-control" value="<?= htmlspecialchars($penjual['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username Toko</label>
                        <input type="text" name="username_penjual" class="form-control" value="<?= htmlspecialchars($penjual['username']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Alamat Toko</label>
                        <textarea name="alamat" class="form-control"><?= htmlspecialchars($penjual['alamat']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Nomor Telepon</label>
                        <input type="text" name="nomor_telepon" class="form-control" value="<?= htmlspecialchars($penjual['nomor_telepon']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="form-control" value="<?= htmlspecialchars($penjual['tanggal_lahir']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="form-control">
                            <option value="">Pilih Jenis Kelamin</option>
                            <option value="pria" <?= ($penjual['jenis_kelamin'] == 'pria') ? 'selected' : '' ?>>Pria</option>
                            <option value="wanita" <?= ($penjual['jenis_kelamin'] == 'wanita') ? 'selected' : '' ?>>Wanita</option>
                            <option value="lainnya" <?= ($penjual['jenis_kelamin'] == 'lainnya') ? 'selected' : '' ?>>Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Foto Profil</label><br>
                        <?php if ($penjual['foto']) : ?>
                            <img src="../../../img/foto/<?= $penjual['foto'] ?>" width="80" class="mb-2 rounded"><br>
                        <?php endif; ?>
                        <input type="file" name="foto" class="form-control-file">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan" class="btn btn-primary">Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="wrapper">
    <div class="sidebar" id="sidebar">
        <h4>&nbsp;</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="../../dashboard_penjual.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="ml-2">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#produkMenu" role="button" aria-expanded="false" aria-controls="produkMenu">
                    <i class="fas fa-box"></i>
                    <span class="ml-2">Produk</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse show" id="produkMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../daftar/daftar_produk.php">Daftar Produk</a>
                        </li>
                        <li class="nav-item">
                            <!-- <a class="nav-link submenu" href="tambah_produk.php">Tambah Produk</a> -->
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../stok/stok.php">Stok / Inventaris</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../varian/varian_produk.php">Varian Produk</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../kategori/kategori_produk.php">Kategori Produk</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#pesananMenu" role="button" aria-expanded="false" aria-controls="pesananMenu">
                    <i class="fas fa-list-check"></i>
                    <span class="ml-2">Pesanan</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="pesananMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pesanan/daftar/pesanan.php">Daftar Pesanan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pesanan/pengiriman/pengiriman.php">Pengiriman</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pesanan/pengembalian/pengembalian_barang.php">Pengembalian Barang</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#pembayaranMenu" role="button" aria-expanded="false" aria-controls="pembayaranMenu">
                    <i class="fas fa-wallet"></i>
                    <span class="ml-2">Pembayaran</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="pembayaranMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pembayaran/riwayat/pembayaran.php">Riwayat Pembayaran</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pembayaran/penarikan/penarikan.php">Penarikan Dana</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pembayaran/catatan/transaksi_lain.php">Catatan Transaksi Lain</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../pembayaran/metode/metode_pembayaran.php">Metode Pembayaran</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#laporanMenu" role="button" aria-expanded="false" aria-controls="laporanMenu">
                    <i class="fas fa-chart-line"></i>
                    <span class="ml-2">Laporan</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="laporanMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../laporan/penjualan/laporan_penjualan.php">Laporan Penjualan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../laporan/stok/laporan_stok.php">Laporan Stok</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../laporan/keuangan/laporan_keuangan.php">Laporan Keuangan</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#promosiMenu" role="button" aria-expanded="false" aria-controls="promosiMenu">
                    <i class="fas fa-bullhorn"></i>
                    <span class="ml-2">Promosi</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="promosiMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../promosi/diskon.php">Diskon</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#tokoMenu" role="button" aria-expanded="false" aria-controls="tokoMenu">
                    <i class="fas fa-store"></i>
                    <span class="ml-2">Toko Saya</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="tokoMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/profil/profil_toko.php">Profil Toko</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/pengaturan/pengaturan_toko.php">Pengaturan Toko</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/pengiriman/pengaturan_pengiriman.php">Pengaturan Pengiriman</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/pembayaran/pengaturan_pembayaran.php">Pengaturan Pembayaran</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../toko/unit/unit_usaha_saya.php">Unit Usaha Saya</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#komunikasiMenu" role="button" aria-expanded="false" aria-controls="komunikasiMenu">
                    <i class="fas fa-envelope"></i>
                    <span class="ml-2">Komunikasi</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="komunikasiMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../komunikasi/pesan/pesan_masuk.php">Pesan Masuk</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../komunikasi/ulasan/ulasan.php">Ulasan</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#artikelMenu" role="button" aria-expanded="false" aria-controls="artikelMenu">
                    <i class="fas fa-newspaper"></i>
                    <span class="ml-2">Artikel</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="artikelMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../artikel/artikel_saya.php">Artikel Saya</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link collapsed" data-toggle="collapse" href="#akunMenu" role="button" aria-expanded="false" aria-controls="akunMenu">
                    <i class="fas fa-user"></i>
                    <span class="ml-2">Akun</span>
                    <i class="fas fa-caret-down float-right"></i>
                </a>
                <div class="collapse" id="akunMenu">
                    <ul class="nav flex-column pl-4">
                        <li class="nav-item">
                            <a class="nav-link submenu" href="../../akun/profil.php">Profil</a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
        <a class="nav-link text-danger" href="../../../logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span class="ml-2">Logout</span>
        </a>
    </div>
<div class="content">
    <h2>Manajemen Varian Produk</h2>
    <?= $pesan; ?>
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-palette mr-1"></i> Warna
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Warna</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($daftar_warna)) : ?>
                            <?php foreach ($daftar_warna as $warna) : ?>
                                <tr>
                                    <td><?= $warna['id']; ?></td>
                                    <td><?= $warna['nama_warna']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editWarnaModal<?= $warna['id']; ?>">
                                            <i class="fas fa-edit text-secondary"></i> Edit
                                        </button>
                                        <a href="manajemen_varian.php?hapus_warna=<?= $warna['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus warna ini?')"><i class="fas fa-trash"></i> Hapus</a>
                                    </td>
                                </tr>
                                <div class="modal fade" id="editWarnaModal<?= $warna['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editWarnaModalLabel<?= $warna['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editWarnaModalLabel<?= $warna['id']; ?>">Edit Warna</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <div class="form-group">
                                                        <label for="nama_warna_edit">Nama Warna:</label>
                                                        <input type="text" class="form-control" id="nama_warna_edit" name="nama_warna_edit" value="<?= $warna['nama_warna']; ?>" required>
                                                    </div>
                                                    <input type="hidden" name="id_warna_edit" value="<?= $warna['id']; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary" name="edit_warna">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="3">Tidak ada data warna.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <nav aria-label="Page navigation Warna">
                <ul class="pagination justify-content-center">
                    <?php if ($halaman_warna > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?halaman_warna=<?= $halaman_warna - 1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <a class="page-link" href="#" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $jumlah_halaman_warna; $i++): ?>
                        <li class="page-item <?= ($i == $halaman_warna) ? 'active' : ''; ?>">
                            <a class="page-link" href="?halaman_warna=<?= $i; ?>"><?= $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($halaman_warna < $jumlah_halaman_warna): ?>
                        <li class="page-item">
                            <a class="page-link" href="?halaman_warna=<?= $halaman_warna + 1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <a class="page-link" href="#" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#tambahWarnaModal">
                <i class="fas fa-plus"></i>  Tambah Warna
            </button>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-ruler mr-1"></i> Ukuran
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Ukuran</th>
                            <th>Singkatan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($daftar_ukuran)) : ?>
                            <?php foreach ($daftar_ukuran as $ukuran) : ?>
                                <tr>
                                    <td><?= $ukuran['id']; ?></td>
                                    <td><?= $ukuran['nama_ukuran']; ?></td>
                                    <td><?= $ukuran['singkatan']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editUkuranModal<?= $ukuran['id']; ?>">
                                            <i class="fas fa-edit text-secondary"></i> Edit
                                        </button>
                                        <a href="manajemen_varian.php?hapus_ukuran=<?= $ukuran['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus ukuran ini?')"><i class="fas fa-trash"></i> Hapus</a>
                                    </td>
                                </tr>
                                <div class="modal fade" id="editUkuranModal<?= $ukuran['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editUkuranModalLabel<?= $ukuran['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editUkuranModalLabel<?= $ukuran['id']; ?>">Edit Ukuran</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <div class="form-group">
                                                        <label for="nama_ukuran_edit">Nama Ukuran:</label>
                                                        <input type="text" class="form-control" id="nama_ukuran_edit" name="nama_ukuran_edit" value="<?= $ukuran['nama_ukuran']; ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="singkatan_ukuran_edit">Singkatan:</label>
                                                        <input type="text" class="form-control" id="singkatan_ukuran_edit" name="singkatan_ukuran_edit" value="<?= $ukuran['singkatan']; ?>">
                                                    </div>
                                                    <input type="hidden" name="id_ukuran_edit" value="<?= $ukuran['id']; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary" name="edit_ukuran">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="4">Tidak ada data ukuran.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <nav aria-label="Page navigation Ukuran">
                <ul class="pagination justify-content-center">
                    <?php if ($halaman_ukuran > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?halaman_ukuran=<?= $halaman_ukuran - 1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <a class="page-link" href="#" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $jumlah_halaman_ukuran; $i++): ?>
                        <li class="page-item <?= ($i == $halaman_ukuran) ? 'active' : ''; ?>">
                            <a class="page-link" href="?halaman_ukuran=<?= $i; ?>"><?= $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($halaman_ukuran < $jumlah_halaman_ukuran): ?>
                        <li class="page-item">
                            <a class="page-link" href="?halaman_ukuran=<?= $halaman_ukuran + 1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <a class="page-link" href="#" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#tambahUkuranModal">
                <i class="fas fa-plus"></i>  Tambah Ukuran
            </button>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-utensil-spoon mr-1"></i> Rasa
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Rasa</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($daftar_rasa)) : ?>
                            <?php foreach ($daftar_rasa as $rasa) : ?>
                                <tr>
                                    <td><?= $rasa['id']; ?></td>
                                    <td><?= $rasa['nama_rasa']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editRasaModal<?= $rasa['id']; ?>">
                                            <i class="fas fa-edit text-secondary"></i> Edit
                                        </button>
                                        <a href="manajemen_varian.php?hapus_rasa=<?= $rasa['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus rasa ini?')"><i class="fas fa-trash"></i> Hapus</a>
                                    </td>
                                </tr>
                                <div class="modal fade" id="editRasaModal<?= $rasa['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editRasaModalLabel<?= $rasa['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editRasaModalLabel<?= $rasa['id']; ?>">Edit Rasa</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <div class="form-group">
                                                        <label for="nama_rasa_edit">Nama Rasa:</label>
                                                        <input type="text" class="form-control" id="nama_rasa_edit" name="nama_rasa_edit" value="<?= $rasa['nama_rasa']; ?>" required>
                                                    </div>
                                                    <input type="hidden" name="id_rasa_edit" value="<?= $rasa['id']; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary" name="edit_rasa">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="3">Tidak ada data rasa.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <nav aria-label="Page navigation Rasa">
                <ul class="pagination justify-content-center">
                    <?php if ($halaman_rasa > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?halaman_rasa=<?= $halaman_rasa - 1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <a class="page-link" href="#" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $jumlah_halaman_rasa; $i++): ?>
                        <li class="page-item <?= ($i == $halaman_rasa) ? 'active' : ''; ?>">
                            <a class="page-link" href="?halaman_rasa=<?= $i; ?>"><?= $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($halaman_rasa < $jumlah_halaman_rasa): ?>
                        <li class="page-item">
                            <a class="page-link" href="?halaman_rasa=<?= $halaman_rasa + 1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <a class="page-link" href="#" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#tambahRasaModal">
                <i class="fas fa-plus"></i>  Tambah Rasa
            </button>
        </div>
    </div>

    <div class="modal fade" id="tambahWarnaModal" tabindex="-1" role="dialog" aria-labelledby="tambahWarnaModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahWarnaModalLabel">Tambah Warna Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="nama_warna">Nama Warna:</label>
                            <input type="text" class="form-control" id="nama_warna" name="nama_warna" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit"class="btn btn-primary" name="tambah_warna">Tambah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tambahUkuranModal" tabindex="-1" role="dialog" aria-labelledby="tambahUkuranModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahUkuranModalLabel">Tambah Ukuran Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="nama_ukuran">Nama Ukuran:</label>
                            <input type="text" class="form-control" id="nama_ukuran" name="nama_ukuran" required>
                        </div>
                        <div class="form-group">
                            <label for="singkatan_ukuran">Singkatan (Opsional):</label>
                            <input type="text" class="form-control" id="singkatan_ukuran" name="singkatan_ukuran">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" name="tambah_ukuran">Tambah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tambahRasaModal" tabindex="-1" role="dialog" aria-labelledby="tambahRasaModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahRasaModalLabel">Tambah Rasa Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="nama_rasa">Nama Rasa:</label>
                            <input type="text" class="form-control" id="nama_rasa" name="nama_rasa" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" name="tambah_rasa">Tambah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>
<footer class="bg-dark text-white text-center py-3 mt-auto" style="position: relative; bottom: 0; width: 100%;">
    <div class="container">
        <small>&copy; <?= date('Y'); ?> BUMDes Indonesia. Seluruh hak cipta dilindungi. |
        <a href="https://www.bumdes.id" class="text-white">www.bumdes.id</a></small>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle('collapsed');
        document.querySelector(".content").classList.toggle('ml-collapsed');
    }
    $(document).ready(function() {
        $('#sidebarCollapse').on('click', function() {
            $('#sidebar').toggleClass('active');
            $('#content').toggleClass('active');
        });

        $('.nav-link.has-submenu').click(function() {
            $(this).toggleClass('open').next('.submenu').slideToggle();
        });
    });
    $(document).ready(function() {
    // Fungsi umum untuk mengirim permintaan AJAX
    function kirimPermintaan(url, data, onSuccess, onError) {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data).toString(),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                onSuccess(data);
            } else {
                onError(data.message || 'Terjadi kesalahan.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            onError('Terjadi kesalahan jaringan.');
        });
    }

    // Warna
    $('#tambahWarnaForm').on('submit', function(e) {
        e.preventDefault();
        const namaWarna = $('#nama_warna').val();
        kirimPermintaan('proses_varian.php', { tambah_warna: true, nama_warna: namaWarna }, data => {
            $('#daftarWarna tbody').append(`
                <tr>
                    <td>${data.warna_baru.id}</td>
                    <td>${data.warna_baru.nama_warna}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-warning edit-warna" data-id="${data.warna_baru.id}" data-nama="${data.warna_baru.nama_warna}" data-toggle="modal" data-target="#editWarnaModal${data.warna_baru.id}">Edit</button>
                        <button type="button" class="btn btn-sm btn-danger hapus-warna" data-id="${data.warna_baru.id}">Hapus</button>
                    </td>
                </tr>
            `);
            $('#tambahWarnaModal').modal('hide');
            $('#nama_warna').val('');
            // Perlu inisialisasi modal edit setelah ditambahkan ke DOM jika belum ada
            if ($(`#editWarnaModal${data.warna_baru.id}`).length === 0) {
                $('body').append(`
                    <div class="modal fade" id="editWarnaModal${data.warna_baru.id}" tabindex="-1" role="dialog" aria-labelledby="editWarnaModalLabel${data.warna_baru.id}" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editWarnaModalLabel${data.warna_baru.id}">Edit Warna</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <form id="editWarnaForm${data.warna_baru.id}">
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="nama_warna_edit${data.warna_baru.id}">Nama Warna:</label>
                                            <input type="text" class="form-control nama_warna_edit" id="nama_warna_edit${data.warna_baru.id}" name="nama_warna_edit" value="${data.warna_baru.nama_warna}" required>
                                        </div>
                                        <input type="hidden" name="id_warna_edit" value="${data.warna_baru.id}">
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary simpan-warna" data-id="${data.warna_baru.id}">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                `);
            }
        }, error => alert('Gagal menambahkan warna: ' + error));
    });

    $(document).on('submit', '[id^="editWarnaForm"]', function(e) {
        e.preventDefault();
        const idWarna = $(this).find('[name="id_warna_edit"]').val();
        const namaWarnaEdit = $(this).find('[name="nama_warna_edit"]').val();
        kirimPermintaan('proses_varian.php', { edit_warna: true, id_warna_edit: idWarna, nama_warna_edit: namaWarnaEdit }, data => {
            $(`#daftarWarna tbody tr:has(td:first-child:contains(${idWarna})) td:nth-child(2)`).text(namaWarnaEdit);
            $(`#editWarnaModal${idWarna}`).modal('hide');
        }, error => alert('Gagal menyimpan perubahan warna: ' + error));
    });

    $(document).on('click', '.hapus-warna', function() {
        const idWarna = $(this).data('id');
        if (confirm('Apakah Anda yakin ingin menghapus warna ini?')) {
            kirimPermintaan('proses_varian.php', { hapus_warna: true, id_warna: idWarna }, data => {
                $(`#daftarWarna tbody tr:has(td:first-child:contains(${idWarna}))`).remove();
            }, error => alert('Gagal menghapus warna: ' + error));
        }
    });

    // Ukuran
    $('#tambahUkuranForm').on('submit', function(e) {
        e.preventDefault();
        const namaUkuran = $('#nama_ukuran').val();
        const singkatanUkuran = $('#singkatan_ukuran').val();
        kirimPermintaan('proses_varian.php', { tambah_ukuran: true, nama_ukuran: namaUkuran, singkatan_ukuran: singkatanUkuran }, data => {
            $('#daftarUkuran tbody').append(`
                <tr>
                    <td>${data.ukuran_baru.id}</td>
                    <td>${data.ukuran_baru.nama_ukuran}</td>
                    <td>${data.ukuran_baru.singkatan || ''}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-warning edit-ukuran" data-id="${data.ukuran_baru.id}" data-nama="${data.ukuran_baru.nama_ukuran}" data-singkatan="${data.ukuran_baru.singkatan || ''}" data-toggle="modal" data-target="#editUkuranModal${data.ukuran_baru.id}">Edit</button>
                        <button type="button" class="btn btn-sm btn-danger hapus-ukuran" data-id="${data.ukuran_baru.id}">Hapus</button>
                    </td>
                </tr>
            `);
            $('#tambahUkuranModal').modal('hide');
            $('#nama_ukuran').val('');
            $('#singkatan_ukuran').val('');
            if ($(`#editUkuranModal${data.ukuran_baru.id}`).length === 0) {
                $('body').append(`
                    <div class="modal fade" id="editUkuranModal${data.ukuran_baru.id}" tabindex="-1" role="dialog" aria-labelledby="editUkuranModalLabel${data.ukuran_baru.id}" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editUkuranModalLabel${data.ukuran_baru.id}">Edit Ukuran</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <form id="editUkuranForm${data.ukuran_baru.id}">
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="nama_ukuran_edit${data.ukuran_baru.id}">Nama Ukuran:</label>
                                            <input type="text" class="form-control nama_ukuran_edit" id="nama_ukuran_edit${data.ukuran_baru.id}" name="nama_ukuran_edit" value="${data.ukuran_baru.nama_ukuran}" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="singkatan_ukuran_edit${data.ukuran_baru.id}">Singkatan:</label>
                                            <input type="text" class="form-control singkatan_ukuran_edit" id="singkatan_ukuran_edit${data.ukuran_baru.id}" name="singkatan_ukuran_edit" value="${data.ukuran_baru.singkatan || ''}">
                                        </div>
                                        <input type="hidden" name="id_ukuran_edit" value="${data.ukuran_baru.id}">
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary simpan-ukuran" data-id="${data.ukuran_baru.id}">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                `);
            }
        }, error => alert('Gagal menambahkan ukuran: ' + error));
    });

    $(document).on('submit', '[id^="editUkuranForm"]', function(e) {
        e.preventDefault();
        const idUkuran = $(this).find('[name="id_ukuran_edit"]').val();
        const namaUkuranEdit = $(this).find('[name="nama_ukuran_edit"]').val();
        const singkatanUkuranEdit = $(this).find('[name="singkatan_ukuran_edit"]').val();
        kirimPermintaan('proses_varian.php', { edit_ukuran: true, id_ukuran_edit: idUkuran, nama_ukuran_edit: namaUkuranEdit, singkatan_ukuran_edit: singkatanUkuranEdit }, data => {
            $(`#daftarUkuran tbody tr:has(td:first-child:contains(${idUkuran})) td:nth-child(2)`).text(namaUkuranEdit);
            $(`#daftarUkuran tbody tr:has(td:first-child:contains(${idUkuran})) td:nth-child(3)`).text(singkatanUkuranEdit || '');
            $(`#editUkuranModal${idUkuran}`).modal('hide');
        }, error => alert('Gagal menyimpan perubahan ukuran: ' + error));
    });

    $(document).on('click', '.hapus-ukuran', function() {
        const idUkuran = $(this).data('id');
        if (confirm('Apakah Anda yakin ingin menghapus ukuran ini?')) {
            kirimPermintaan('proses_varian.php', { hapus_ukuran: true, id_ukuran: idUkuran }, data => {
                $(`#daftarUkuran tbody tr:has(td:first-child:contains(${idUkuran}))`).remove();
            }, error => alert('Gagal menghapus ukuran: ' + error));
        }
    });

    // Rasa
    $('#tambahRasaForm').on('submit', function(e) {
        e.preventDefault();
        const namaRasa = $('#nama_rasa').val();
        kirimPermintaan('proses_varian.php', { tambah_rasa: true, nama_rasa: namaRasa }, data => {
            $('#daftarRasa tbody').append(`
                <tr>
                    <td>${data.rasa_baru.id}</td>
                    <td>${data.rasa_baru.nama_rasa}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-warning edit-rasa" data-id="${data.rasa_baru.id}" data-nama="${data.rasa_baru.nama_rasa}" data-toggle="modal" data-target="#editRasaModal${data.rasa_baru.id}">Edit</button>
                        <button type="button" class="btn btn-sm btn-danger hapus-rasa" data-id="${data.rasa_baru.id}">Hapus</button>
                    </td>
                </tr>
            `);
            $('#tambahRasaModal').modal('hide');
            $('#nama_rasa').val('');
            if ($(`#editRasaModal${data.rasa_baru.id}`).length === 0) {
                $('body').append(`
                    <div class="modal fade" id="editRasaModal${data.rasa_baru.id}" tabindex="-1" role="dialog" aria-labelledby="editRasaModalLabel${data.rasa_baru.id}" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editRasaModalLabel${data.rasa_baru.id}">Edit Rasa</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <form id="editRasaForm${data.rasa_baru.id}">
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="nama_rasa_edit${data.rasa_baru.id}">Nama Rasa:</label>
                                            <input type="text" class="form-control nama_rasa_edit" id="nama_rasa_edit${data.rasa_baru.id}" name="nama_rasa_edit" value="${data.rasa_baru.nama_rasa}" required>
                                        </div>
                                        <input type="hidden" name="id_rasa_edit" value="${data.rasa_baru.id}">
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary simpan-rasa" data-id="${data.rasa_baru.id}">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                `);
            }
        }, error => alert('Gagal menambahkan rasa: ' + error));
    });

    $(document).on('submit', '[id^="editRasaForm"]', function(e) {
        e.preventDefault();
        const idRasa = $(this).find('[name="id_rasa_edit"]').val();
        const namaRasaEdit = $(this).find('[name="nama_rasa_edit"]').val();
        kirimPermintaan('proses_varian.php', { edit_rasa: true, id_rasa_edit: idRasa, nama_rasa_edit: namaRasaEdit }, data => {
            $(`#daftarRasa tbody tr:has(td:first-child:contains(${idRasa})) td:nth-child(2)`).text(namaRasaEdit);
            $(`#editRasaModal${idRasa}`).modal('hide');
        }, error => alert('Gagal menyimpan perubahan rasa: ' + error));
    });

    $(document).on('click', '.hapus-rasa', function() {
        const idRasa = $(this).data('id');
        if (confirm('Apakah Anda yakin ingin menghapus rasa ini?')) {
            kirimPermintaan('proses_varian.php', { hapus_rasa: true, id_rasa: idRasa }, data => {
                $(`#daftarRasa tbody tr:has(td:first-child:contains(${idRasa}))`).remove();
            }, error => alert('Gagal menghapus rasa: ' + error));
        }
    });
});
</script>
</body>
</html>
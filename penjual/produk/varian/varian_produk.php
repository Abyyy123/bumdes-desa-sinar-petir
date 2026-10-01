<?php
session_start();
include('../../../koneksi/koneksi.php');

if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../../../login.php');
    exit;
}
$user_id = $_SESSION['pengguna_id'];

// PAGINATION
$batas = 10;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
$halaman_awal = ($halaman > 1) ? ($halaman * $batas) - $batas : 0;

$previous = $halaman - 1;
$next = $halaman + 1;

// Ambil kata kunci pencarian dari parameter GET
$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';

// Query untuk menghitung total data varian produk milik penjual (dengan filter keyword)
$query_total = "SELECT COUNT(pv.id) AS total
                FROM produk_variasi pv
                JOIN produk p ON pv.produk_id = p.id
                LEFT JOIN warna w ON pv.warna_id = w.id
                LEFT JOIN ukuran u ON pv.ukuran_id = u.id
                LEFT JOIN rasa r ON pv.rasa_id = r.id
                WHERE p.penjual_id = ?";

if (!empty($keyword)) {
    $query_total .= " AND (p.nama LIKE ? OR w.nama_warna LIKE ? OR u.nama_ukuran LIKE ? OR r.nama_rasa LIKE ?)";
}

$stmt_total = mysqli_prepare($conn, $query_total);
if (!empty($keyword)) {
    $keyword_param = "%" . $keyword . "%";
    mysqli_stmt_bind_param($stmt_total, 'issss', $user_id, $keyword_param, $keyword_param, $keyword_param, $keyword_param);
} else {
    mysqli_stmt_bind_param($stmt_total, 'i', $user_id);
}
mysqli_stmt_execute($stmt_total);
$result_total = mysqli_stmt_get_result($stmt_total);
$data_total = mysqli_fetch_assoc($result_total);
$total_data = $data_total['total'];
$total_halaman = ceil($total_data / $batas);
mysqli_stmt_close($stmt_total);

// Query untuk mengambil data varian produk dengan informasi terkait dan pagination (dengan filter keyword)
$sql = "SELECT
            pv.id AS id_varian,
            p.nama AS nama_produk,
            w.nama_warna AS warna,
            u.nama_ukuran AS ukuran,
            r.nama_rasa AS nama_rasa,
            pv.stok,
            pv.harga,
            pv.gambar
        FROM
            produk_variasi pv
        JOIN
            produk p ON pv.produk_id = p.id
        LEFT JOIN
            warna w ON pv.warna_id = w.id
        LEFT JOIN
            ukuran u ON pv.ukuran_id = u.id
        LEFT JOIN
            rasa r ON pv.rasa_id = r.id
        WHERE p.penjual_id = ?";

if (!empty($keyword)) {
    $sql .= " AND (p.nama LIKE ? OR w.nama_warna LIKE ? OR u.nama_ukuran LIKE ? OR r.nama_rasa LIKE ?)";
}

$sql .= " LIMIT $halaman_awal, $batas";

$stmt_varian_list = mysqli_prepare($conn, $sql);

if (!empty($keyword)) {
    $keyword_param = "%" . $keyword . "%";
    mysqli_stmt_bind_param($stmt_varian_list, 'issss', $user_id, $keyword_param, $keyword_param, $keyword_param, $keyword_param);
} else {
    mysqli_stmt_bind_param($stmt_varian_list, 'i', $user_id);
}

mysqli_stmt_execute($stmt_varian_list);
$result_varian_list = mysqli_stmt_get_result($stmt_varian_list);

$varian_produk = [];
if (mysqli_num_rows($result_varian_list) > 0) {
    while ($row = mysqli_fetch_assoc($result_varian_list)) {
        $varian_produk[] = $row;
    }
}
mysqli_stmt_close($stmt_varian_list);

// Inisialisasi hasil pencarian (untuk ditampilkan di atas pagination)
$hasil_pencarian = $varian_produk;

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

// Proses Tambah Warna
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
        } else {$pesan = "<div class='alert alert-danger'>Gagal menghapus warna.</div>";
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
        $tipe_stok_edit = (is_int($stok_edit)) ? 'i' :'d';
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
        /* Palet Warna Hijau Hutan & Emas:
        - #FBFBFB (Krem Pucat - Latar Belakang Umum)
        - #0A422F (Hijau Hutan Gelap - Navbar, Kartu Total Produk)
        - #1C6B4D (Hijau Forest Lebih Terang - Sidebar)
        - #C7A77C (Krem Keemasan - Sidebar Hover/Active)
        - #EFEAD8 (Krem Pudar - Submenu Sidebar)
        - #D4C29E (Krem Gelap - Kartu Menunggu Pembayaran)
        - #8B9B7A (Hijau Zaitun Pudar - Kartu Sedang Diproses)
        - #4F7942 (Hijau Gelap Medium - Kartu Sedang Dikirim)
        - #8B0000 (Merah Gelap/Marun - Kartu Stok Rendah)
        - #B8860B (Kuning Emas Tua - Kartu Total Pendapatan)
        */

        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background-color: #FBFBFB; /* Latar belakang Krem Pucat */
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
            background-color: #1C6B4D; /* Sidebar Hijau Forest Lebih Terang */
            min-height: 100vh;
            padding: 20px 0;
            color: white;
            transition: width 0.3s ease;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
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
            color: #ffffff; /* Teks di sidebar (putih) */
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar a:hover,
        .sidebar .nav-link:hover {
            background-color: #C7A77C; /* Krem Keemasan saat hover */
            color: #0A422F; /* Teks hijau hutan gelap saat hover */
            text-decoration: none;
        }
        .sidebar a.active,
        .sidebar .nav-link.active {
            background-color: #C7A77C; /* Warna aktif, Krem Keemasan */
            color: #0A422F; /* Teks hijau hutan gelap saat aktif */
            font-weight: bold;
        }

        /* --- Tambahan untuk menghilangkan warna biru pada focus/outline dan link default --- */
        .sidebar a:focus,
        .sidebar .nav-link:focus {
            outline: 2px solid #C7A77C; /* Outline Krem Keemasan saat focus */
            outline-offset: -2px; /* Untuk membuat outline tidak terlalu lebar */
        }
        .sidebar a,
        .sidebar .nav-link,
        .sidebar .submenu {
            color: #ffffff; /* Memastikan semua teks link di sidebar putih secara default */
        }
        .sidebar .submenu:hover {
            color: #0A422F; /* Teks submenu saat hover menjadi hijau gelap */
            background-color: #C7A77C; /* Background submenu saat hover */
        }
        .sidebar .submenu.active { /* Jika ada submenu yang aktif, beri warna khusus */
            color: #0A422F;
            background-color: #C7A77C;
        }

        .sidebar .nav-item {
            list-style: none;
        }
        .sidebar .submenu {
            font-size: 0.9rem;
            padding-left: 40px;
            color: #EFEAD8; /* Krem Pudar untuk submenu */
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

        /* Styling for the table and form elements */
        .table {
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .table thead {
            background-color: #0A422F; /* Match navbar color from dashboard */
            color: white;
        }
        .table th, .table td {
            padding: 12px 15px;
            vertical-align: middle;
        }
        .table-hover tbody tr:hover {
            background-color: #f5f5f5;
        }
        .btn-primary {
            background-color: #0A422F; /* Green from dashboard navbar */
            border-color: #0A422F;
            color: white;
        }
        .btn-primary:hover {
            background-color: #1C6B4D; /* Lighter green from dashboard sidebar */
            border-color: #1C6B4D;
            color: white;
        }
        .btn-outline-success {
            color: #1C6B4D; /* Green from dashboard sidebar */
            border-color: #1C6B4D;
        }
        .btn-outline-success:hover {
            background-color: #1C6B4D;
            color: white;
        }
        .form-control, .form-control-file, .custom-select {
            border-radius: 5px;
        }
        .pagination .page-item.active .page-link {
            background-color: #0A422F; /* Match navbar color */
            border-color: #0A422F;
        }
        .pagination .page-link {
            color: #0A422F; /* Match navbar color */
        }
        .pagination .page-link:hover {
            color: #1C6B4D; /* Lighter green from sidebar */
        }
        .btn-warning {
            background-color: #D4C29E; /* Krem Gelap - similar to waiting payment card */
            border-color: #D4C29E;
            color: #333333; /* Dark text for readability */
        }
        .btn-warning:hover {
            background-color: #C7A77C; /* Krem Keemasan - sidebar hover color */
            border-color: #C7A77C;
            color: #333333;
        }
        .btn-danger {
            background-color: #8B0000; /* Merah Gelap/Marun - Stok Rendah card */
            border-color: #8B0000;
            color: #ffffff;
        }
        .btn-danger:hover {
            background-color: #A52A2A; /* slightly lighter red */
            border-color: #A52A2A;
            color: #ffffff;
        }
        .btn-info {
            background-color: #8B9B7A; /* Hijau Zaitun Pudar - Sedang Diproses card */
            border-color: #8B9B7A;
            color: #333333; /* Dark text for readability */
        }
        .btn-info:hover {
            background-color: #6B7C5E; /* slightly darker olive green */
            border-color: #6B7C5E;
            color: #333333;
        }
        .table img {
            border-radius: 5px;
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
            .content {
                padding: 15px;
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
        height: 80px;
        width: 59%;
        padding: 10px 5px;
        margin: 3px 5px;
    }
    #salesChart {
        width: 100% !important;
        max-width: 600px !important;
        height: 300px !important;
    }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #0A422F;">
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
                            <a class="nav-link submenu active" href="varian_produk.php">Varian Produk</a>
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
        </ul>
        <a class="nav-link text-danger" href="../../../logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span class="ml-2">Logout</span>
        </a>
    </div>
<div class="content">
    <h2>Varian Produk</h2>
    <div class="mb-3">
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#tambahVarianModal">
            <i class="fas fa-plus"></i> Tambah Varian
        </button>
        <a href="manajemen_varian.php" class="btn btn-info ml-2">
            <i class="fas fa-cog"></i> Manajemen Varian
        </a>
    </div>

    <form class="form-inline mb-3" method="get">
        <input class="form-control mr-sm-2" type="search" placeholder="Cari Varian Produk" aria-label="Search" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>">
        <button class="btn btn-outline-success my-2 my-sm-0" type="submit">Cari</button>
    </form>

    <?php if (!empty($pesan)) : ?>
        <?= $pesan; ?>
    <?php endif; ?>

    <?php
    $data_ditampilkan = $hasil_pencarian;
    if (!empty($data_ditampilkan)) :
    ?>
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Produk</th>
                    <th>Warna</th>
                    <th>Ukuran</th>
                    <th>Rasa</th>
                    <th>Stok</th>
                    <th>Harga</th>
                    <th>Gambar</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data_ditampilkan as $varian) : ?>
                    <tr>
                        <td><?= htmlspecialchars($varian['id_varian']); ?></td>
                        <td><?= htmlspecialchars($varian['nama_produk']); ?></td>
                        <td><?= isset($varian['warna']) ? htmlspecialchars($varian['warna']) : '-'; ?></td>
                        <td><?= isset($varian['ukuran']) ? htmlspecialchars($varian['ukuran']) : '-'; ?></td>
                        <td><?= isset($varian['nama_rasa']) ? htmlspecialchars($varian['nama_rasa']) : '-'; ?></td>
                        <td><?= htmlspecialchars($varian['stok']); ?></td>
                        <td>Rp <?= number_format(htmlspecialchars($varian['harga'])); ?></td>
                        <td>
                            <?php if (isset($varian['gambar']) && !empty($varian['gambar'])) : ?>
                                <img src="../../../img/produk/<?= htmlspecialchars($varian['gambar']); ?>" alt="Gambar Varian" width="50">
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editVarianModal<?= htmlspecialchars($varian['id_varian']); ?>">
                                <i class="fas fa-edit text-secondary"></i> Edit
                            </button>
                            <a href="varian_produk.php?hapus_varian=<?= htmlspecialchars($varian['id_varian']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus varian ini?')"><i class="fas fa-trash"></i> Hapus</a>
                        </td>
                    </tr>
                    <?php
                    $varian_data_edit = getVarianProdukById($conn, $varian['id_varian']);
                    ?>
                    <div class="modal fade" id="editVarianModal<?= htmlspecialchars($varian['id_varian']); ?>" tabindex="-1" role="dialog" aria-labelledby="editVarianModalLabel<?= htmlspecialchars($varian['id_varian']); ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editVarianModalLabel<?= htmlspecialchars($varian['id_varian']); ?>">Edit Varian Produk</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="produk_id_edit">Produk:</label>
                                                    <select class="form-control" id="produk_id_edit" name="produk_id_edit" required>
                                                        <option value="">Pilih Produk</option>
                                                        <?php foreach ($daftar_produk as $produk) : ?>
                                                            <option value="<?= htmlspecialchars($produk['id']); ?>" <?= ($varian_data_edit['produk_id'] == $produk['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($produk['nama']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="warna_id_edit">Warna:</label>
                                                    <select class="form-control" id="warna_id_edit" name="warna_id_edit">
                                                        <option value="0">Pilih Warna (Opsional)</option>
                                                        <?php foreach ($daftar_warna as $warna) : ?>
                                                            <option value="<?= htmlspecialchars($warna['id']); ?>" <?= ($varian_data_edit['warna_id'] == $warna['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($warna['nama_warna']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="ukuran_id_edit">Ukuran:</label>
                                                    <select class="form-control" id="ukuran_id_edit" name="ukuran_id_edit">
                                                        <option value="0">Pilih Ukuran (Opsional)</option>
                                                        <?php foreach ($daftar_ukuran as $ukuran) : ?>
                                                            <option value="<?= htmlspecialchars($ukuran['id']); ?>" <?= ($varian_data_edit['ukuran_id'] == $ukuran['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($ukuran['nama_ukuran']); ?><?php if (!empty($ukuran['singkatan'])) : ?> (<?= htmlspecialchars($ukuran['singkatan']); ?>)<?php endif; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="rasa_id_edit">Rasa:</label>
                                                    <select class="form-control" id="rasa_id_edit" name="rasa_id_edit">
                                                        <option value="0">Pilih Rasa (Opsional)</option>
                                                        <?php foreach ($daftar_rasa as $rasa) : ?>
                                                            <option value="<?= htmlspecialchars($rasa['id']); ?>" <?= ($varian_data_edit['rasa_id'] == $rasa['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($rasa['nama_rasa']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="stok_edit">Stok:</label>
                                                    <input type="number" class="form-control" id="stok_edit" name="stok_edit" required min="0" value="<?= htmlspecialchars($varian_data_edit['stok']); ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label for="harga_edit">Harga:</label>
                                                    <input type="number" class="form-control" id="harga_edit" name="harga_edit" required min="0" step="any" value="<?= htmlspecialchars($varian_data_edit['harga']); ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label for="gambar_edit">Gambar Varian:</label><br>
                                                    <?php if (!empty($varian_data_edit['gambar'])) : ?>
                                                        <img src="../../../img/produk/<?= htmlspecialchars($varian_data_edit['gambar']); ?>" alt="Gambar Varian Lama" width="100" class="mb-2">
                                                        <input type="hidden" name="gambar_lama_edit" value="<?= htmlspecialchars($varian_data_edit['gambar']); ?>">
                                                    <?php endif; ?>
                                                    <input type="file" class="form-control-file" id="gambar_edit" name="gambar_edit">
                                                    <small class="form-text text-muted">Format yang diizinkan: jpg, jpeg, png, avif.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <input type="hidden" name="id_varian_edit" value="<?= htmlspecialchars($varian['id_varian']); ?>">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary" name="edit_varian_produk">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </tbody>
        </table>

        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($halaman > 1) : ?>
                    <li class="page-item">
                        <a class="page-link" href="?halaman=<?= $previous; ?><?php if (!empty($keyword)) echo '&keyword=' . htmlspecialchars($keyword); ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php else : ?>
                    <li class="page-item disabled">
                        <a class="page-link" href="#" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_halaman; $i++) : ?>
                    <li class="page-item <?= ($halaman == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?halaman=<?= $i; ?><?php if (!empty($keyword)) echo '&keyword=' . htmlspecialchars($keyword); ?>"><?= $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($halaman < $total_halaman) : ?>
                    <li class="page-item">
                        <a class="page-link" href="?halaman=<?= $next; ?><?php if (!empty($keyword)) echo '&keyword=' . htmlspecialchars($keyword); ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php else : ?>
                    <li class="page-item disabled">
                        <a class="page-link" href="#" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

    <?php else : ?>
        <div class="alert alert-warning">
            <?php if (!empty($keyword)) : ?>
                Tidak ada varian produk yang ditemukan dengan kata kunci "<?= htmlspecialchars($keyword); ?>".
            <?php else : ?>
                Belum ada data varian produk.
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="tambahVarianModal" tabindex="-1" role="dialog" aria-labelledby="tambahVarianModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tambahVarianModalLabel">Tambah Varian Produk Baru</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="produk_id_varian">Produk:</label>
                                <select class="form-control" id="produk_id_varian" name="produk_id_varian" required>
                                    <option value="">Pilih Produk</option>
                                    <?php foreach ($daftar_produk as $produk) : ?>
                                        <option value="<?= htmlspecialchars($produk['id']); ?>"><?= htmlspecialchars($produk['nama']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="warna_id_varian">Warna:</label>
                                <select class="form-control" id="warna_id_varian" name="warna_id_varian">
                                    <option value="0">Pilih Warna (Opsional)</option>
                                    <?php foreach ($daftar_warna as $warna) : ?>
                                        <option value="<?= htmlspecialchars($warna['id']); ?>"><?= htmlspecialchars($warna['nama_warna']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="ukuran_id_varian">Ukuran:</label>
                                <select class="form-control" id="ukuran_id_varian" name="ukuran_id_varian">
                                    <option value="0">Pilih Ukuran (Opsional)</option>
                                    <?php foreach ($daftar_ukuran as $ukuran) : ?>
                                        <option value="<?= htmlspecialchars($ukuran['id']); ?>"><?= htmlspecialchars($ukuran['nama_ukuran']); ?><?php if (!empty($ukuran['singkatan'])) : ?> (<?= htmlspecialchars($ukuran['singkatan']); ?>)<?php endif; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="rasa_id_varian">Rasa:</label>
                                <select class="form-control" id="rasa_id_varian" name="rasa_id_varian">
                                    <option value="0">Pilih Rasa (Opsional)</option>
                                    <?php foreach ($daftar_rasa as $rasa) : ?>
                                        <option value="<?= htmlspecialchars($rasa['id']); ?>"><?= htmlspecialchars($rasa['nama_rasa']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="stok_varian">Stok:</label>
                                <input type="number" class="form-control" id="stok_varian" name="stok_varian" required min="0" value="0">
                            </div>
                            <div class="form-group">
                                <label for="harga_varian">Harga:</label>
                                <input type="number" class="form-control" id="harga_varian" name="harga_varian" required min="0" step="any" value="0">
                            </div>
                            <div class="form-group">
                                <label for="gambar_varian">Gambar Varian:</label>
                                <input type="file" class="form-control-file" id="gambar_varian" name="gambar_varian">
                                <small class="form-text text-muted">Format yang diizinkan: jpg, jpeg, png, avif.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" name="tambah_varian_produk">Simpan Varian</button>
                </div>
            </form>
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
</script>

</body>
</html>
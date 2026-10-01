<?php
// Koneksi ke database (pastikan konfigurasi sudah benar)
$conn = new mysqli("localhost", "username", "password", "nama_database");
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

header('Content-Type: application/json');

// Warna
if (isset($_POST['tambah_warna'])) {
    $nama_warna = $_POST['nama_warna'];
    $stmt = $conn->prepare("INSERT INTO warna (nama_warna) VALUES (?)");
    $stmt->bind_param("s", $nama_warna);
    if ($stmt->execute()) {
        $warna_id = $conn->insert_id;
        $response = ['success' => true, 'warna_baru' => ['id' => $warna_id, 'nama_warna' => $nama_warna]];
    } else {
        $response = ['success' => false, 'message' => 'Gagal menambahkan warna.'];
    }
    echo json_encode($response);
    $stmt->close();
} elseif (isset($_POST['edit_warna'])) {
    $id_warna = $_POST['id_warna_edit'];
    $nama_warna = $_POST['nama_warna_edit'];
    $stmt = $conn->prepare("UPDATE warna SET nama_warna = ? WHERE id = ?");
        $stmt->bind_param("si", $nama_warna, $id_warna);
        if ($stmt->execute()) {
            $response = ['success' => true];
        } else {
            $response = ['success' => false, 'message' => 'Gagal menyimpan perubahan warna.'];
        }
        echo json_encode($response);
        $stmt->close();
    } elseif (isset($_POST['hapus_warna'])) {
        $id_warna = $_POST['id_warna'];
        $stmt = $conn->prepare("DELETE FROM warna WHERE id = ?");
        $stmt->bind_param("i", $id_warna);
        if ($stmt->execute()) {
            $response = ['success' => true];
        } else {
            $response = ['success' => false, 'message' => 'Gagal menghapus warna.'];
        }
        echo json_encode($response);
        $stmt->close();
    }

    // Ukuran
    elseif (isset($_POST['tambah_ukuran'])) {
        $nama_ukuran = $_POST['nama_ukuran'];
        $singkatan_ukuran = $_POST['singkatan_ukuran'];
        $stmt = $conn->prepare("INSERT INTO ukuran (nama_ukuran, singkatan) VALUES (?, ?)");
        $stmt->bind_param("ss", $nama_ukuran, $singkatan_ukuran);
        if ($stmt->execute()) {
            $ukuran_id = $conn->insert_id;
            $response = ['success' => true, 'ukuran_baru' => ['id' => $ukuran_id, 'nama_ukuran' => $nama_ukuran, 'singkatan' => $singkatan_ukuran]];
        } else {
            $response = ['success' => false, 'message' => 'Gagal menambahkan ukuran.'];
        }
        echo json_encode($response);
        $stmt->close();
    } elseif (isset($_POST['edit_ukuran'])) {
        $id_ukuran = $_POST['id_ukuran_edit'];
        $nama_ukuran = $_POST['nama_ukuran_edit'];
        $singkatan_ukuran = $_POST['singkatan_ukuran_edit'];
        $stmt = $conn->prepare("UPDATE ukuran SET nama_ukuran = ?, singkatan = ? WHERE id = ?");
        $stmt->bind_param("ssi", $nama_ukuran, $singkatan_ukuran, $id_ukuran);
        if ($stmt->execute()) {
            $response = ['success' => true];
        } else {
            $response = ['success' => false, 'message' => 'Gagal menyimpan perubahan ukuran.'];
        }
        echo json_encode($response);
        $stmt->close();
    } elseif (isset($_POST['hapus_ukuran'])) {
        $id_ukuran = $_POST['id_ukuran'];
        $stmt = $conn->prepare("DELETE FROM ukuran WHERE id = ?");
        $stmt->bind_param("i", $id_ukuran);
        if ($stmt->execute()) {
            $response = ['success' => true];
        } else {
            $response = ['success' => false, 'message' => 'Gagal menghapus ukuran.'];
        }
        echo json_encode($response);
        $stmt->close();
    }

    // Rasa
    elseif (isset($_POST['tambah_rasa'])) {
        $nama_rasa = $_POST['nama_rasa'];
        $stmt = $conn->prepare("INSERT INTO rasa (nama_rasa) VALUES (?)");
        $stmt->bind_param("s", $nama_rasa);
        if ($stmt->execute()) {
            $rasa_id = $conn->insert_id;
            $response = ['success' => true, 'rasa_baru' => ['id' => $rasa_id, 'nama_rasa' => $nama_rasa]];
        } else {
            $response = ['success' => false, 'message' => 'Gagal menambahkan rasa.'];
        }
        echo json_encode($response);
        $stmt->close();
    } elseif (isset($_POST['edit_rasa'])) {
        $id_rasa = $_POST['id_rasa_edit'];
        $nama_rasa = $_POST['nama_rasa_edit'];
        $stmt = $conn->prepare("UPDATE rasa SET nama_rasa = ? WHERE id = ?");
        $stmt->bind_param("si", $nama_rasa, $id_rasa);
        if ($stmt->execute()) {
            $response = ['success' => true];
        } else {
            $response = ['success' => false, 'message' => 'Gagal menyimpan perubahan rasa.'];
        }
        echo json_encode($response);
        $stmt->close();
    } elseif (isset($_POST['hapus_rasa'])) {
        $id_rasa = $_POST['id_rasa'];
        $stmt = $conn->prepare("DELETE FROM rasa WHERE id = ?");
        $stmt->bind_param("i", $id_rasa);
        if ($stmt->execute()) {
            $response = ['success' => true];
        } else {
            $response = ['success' => false, 'message' => 'Gagal menghapus rasa.'];
        }
        echo json_encode($response);
        $stmt->close();
    }

$conn->close();
?>
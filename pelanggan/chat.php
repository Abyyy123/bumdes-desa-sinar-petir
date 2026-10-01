<?php
// Pastikan path koneksi Anda benar.
// Jika koneksi.php ada di '../koneksi/koneksi.php', pastikan isinya:
// $conn = new mysqli(...); // Gunakan $conn sesuai dengan kode Anda
include '../koneksi/koneksi.php';
session_start(); // Pastikan session sudah dimulai

// Pastikan pengguna sudah login
if (!isset($_SESSION['pengguna_id'])) {
    header('Location: ../login.php'); // Arahkan ke halaman login jika belum login
    exit();
}

$sender_id = $_SESSION['pengguna_id'];
$receiver_id = 0;
$receiver_data = null; // Data penerima (penjual)

if (isset($_GET['receiver_id']) && is_numeric($_GET['receiver_id'])) {
    $receiver_id = $_GET['receiver_id'];

    // Ambil data penerima (penjual) dari tabel 'users'
    // Perhatikan: kolom 'id', 'username', 'nama_toko', 'foto_profil' diasumsikan ada di tabel 'users'
    $sql_receiver = "SELECT pengguna_id, username, nama_toko, foto FROM penjual WHERE pengguna_id = ?";
    $stmt_receiver = mysqli_prepare($conn, $sql_receiver);
    mysqli_stmt_bind_param($stmt_receiver, "i", $receiver_id);
    mysqli_stmt_execute($stmt_receiver);
    $result_receiver = mysqli_stmt_get_result($stmt_receiver);
    $receiver_data = mysqli_fetch_assoc($result_receiver);
    mysqli_stmt_close($stmt_receiver);

    if (!$receiver_data) {
        echo "Penjual tidak ditemukan.";
        exit();
    }

} else {
    echo "ID penerima tidak valid.";
    exit();
}

// Proses pengiriman pesan ke tabel 'pesan'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        // SESUAIKAN: Nama tabel 'pesan', kolom 'pengirim_pengguna_id', 'penerima_pengguna_id', 'isi_pesan'
        // SERTAKAN: 'status_baca' dan 'status_pesan'
        $sql_insert_message = "INSERT INTO pesan (pengirim_pengguna_id, penerima_pengguna_id, isi_pesan, status_baca, status_pesan) VALUES (?, ?, ?, 'belum_dibaca', 'aktif')";
        $stmt_insert_message = mysqli_prepare($conn, $sql_insert_message);
        mysqli_stmt_bind_param($stmt_insert_message, "iis", $sender_id, $receiver_id, $message);
        mysqli_stmt_execute($stmt_insert_message);
        mysqli_stmt_close($stmt_insert_message);
        header("Location: chat.php?receiver_id=" . $receiver_id); // Redirect untuk mencegah resubmission
        exit();
    }
}

// Ambil riwayat pesan dari tabel 'pesan'
$messages = [];
// SESUAIKAN: Nama tabel 'pesan', JOIN ke 'users' berdasarkan ID pengguna
// SESUAIKAN: Kolom 'pengirim_pengguna_id', 'penerima_pengguna_id', 'isi_pesan', 'waktu_kirim'
$sql_messages = "SELECT p.*, s.username AS sender_username, r.username AS receiver_username
                 FROM pesan p
                 JOIN pelanggan s ON p.pengirim_pengguna_id = s.pengguna_id
                 JOIN penjual r ON p.penerima_pengguna_id = r.pengguna_id
                 WHERE (p.pengirim_pengguna_id = ? AND p.penerima_pengguna_id = ?)
                    OR (p.pengirim_pengguna_id = ? AND p.penerima_pengguna_id = ?)
                 ORDER BY p.waktu_kirim ASC"; // SESUAIKAN: Order by waktu_kirim

$stmt_messages = mysqli_prepare($conn, $sql_messages);
mysqli_stmt_bind_param($stmt_messages, "iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
mysqli_stmt_execute($stmt_messages);
$result_messages = mysqli_stmt_get_result($stmt_messages);
$messages = mysqli_fetch_all($result_messages, MYSQLI_ASSOC);
mysqli_stmt_close($stmt_messages);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat dengan <?php echo htmlspecialchars($receiver_data['nama_toko'] ?? $receiver_data['username']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background-color: #f8f9fa; }
        .chat-container {
            max-width: 800px;
            margin: 30px auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 60px); /* Adjust to fill screen height if needed */
        }
        .chat-header {
            background-color: #007bff;
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0; /* Prevent header from shrinking */
        }
        .chat-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            border: 2px solid rgba(255,255,255,0.8);
        }
        .chat-messages {
            padding: 20px;
            flex-grow: 1; /* Allow messages to take available space */
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background-color: #fcfcfc;
        }
        .message {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 15px;
            word-wrap: break-word;
            line-height: 1.4;
        }
        .message.sent {
            background-color: #dcf8c6;
            align-self: flex-end;
        }
        .message.received {
            background-color: #f1f0f0;
            align-self: flex-start;
        }
        .message-time {
            font-size: 0.75rem;
            color: #888;
            margin-top: 3px;
            display: block; /* Ensure time is on a new line */
            text-align: right;
        }
        .message.received .message-time {
            text-align: left;
        }
        .chat-input {
            border-top: 1px solid #eee;
            padding: 15px;
            display: flex;
            gap: 10px;
            background-color: #fff;
            flex-shrink: 0; /* Prevent input from shrinking */
        }
        .chat-input textarea {
            flex-grow: 1;
            border-radius: 20px;
            border: 1px solid #ddd;
            padding: 10px 15px;
            resize: none;
            font-size: 0.9rem;
            min-height: 40px; /* Minimum height for textarea */
            max-height: 120px; /* Maximum height for textarea */
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
        }
        .chat-input button {
            border-radius: 20px;
            padding: 10px 15px; /* Adjust padding for better button size */
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <img src="<?php echo !empty($receiver_data['foto_profil']) ? htmlspecialchars($receiver_data['foto_profil']) : '../img/foto/penjual.jpeg'; ?>" alt="Foto Profil Penerima">
            Chat dengan <?php echo htmlspecialchars($receiver_data['nama_toko'] ?? $receiver_data['username']); ?>
        </div>
        <div class="chat-messages" id="chat-messages">
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo ($msg['pengirim_pengguna_id'] == $sender_id) ? 'sent' : 'received'; ?>">
                        <?php echo htmlspecialchars($msg['isi_pesan']); ?>
                        <div class="message-time"><?php echo date('H:i', strtotime($msg['waktu_kirim'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted">Belum ada pesan dalam percakapan ini.</p>
            <?php endif; ?>
        </div>
        <div class="chat-input">
            <form action="chat.php?receiver_id=<?php echo $receiver_id; ?>" method="POST" style="display: flex; width: 100%;">
                <textarea name="message" placeholder="Ketik pesan Anda..." rows="1"></textarea>
                <button type="submit" class="btn btn-primary"><i class="bi bi-send-fill"></i></button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Scroll to the bottom of the chat messages
        const chatMessages = document.getElementById('chat-messages');
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Auto-resize textarea
        const textarea = document.querySelector('.chat-input textarea');
        textarea.addEventListener('input', () => {
            textarea.style.height = 'auto'; // Reset height
            textarea.style.height = textarea.scrollHeight + 'px'; // Set to scroll height
        });
    </script>
</body>
</html>
<?php
// Pastikan $conn ditutup hanya sekali setelah semua operasi database selesai
mysqli_close($conn);
?>
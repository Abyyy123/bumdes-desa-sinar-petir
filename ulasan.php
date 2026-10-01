<?php
// Pastikan koneksi database sudah di-include di detail_produk.php
// include 'koneksi/koneksi.php';

// Ambil ID produk dari parameter GET (pastikan ini sudah ada di detail_produk.php)
if (isset($produk_id)) {
    // Query untuk mengambil ulasan produk beserta nama pelanggan dan media
    $sql_ulasan = "SELECT
                        up.id AS ulasan_id,
                        up.rating,
                        up.komentar,
                        up.tanggal_ulasan,
                        p.nama AS nama_pelanggan
                    FROM
                        ulasan_produk up
                    JOIN
                        pelanggan p ON up.pelanggan_id = p.pengguna_id
                    WHERE
                        up.produk_id = ?
                    ORDER BY
                        up.tanggal_ulasan DESC";
    $stmt_ulasan = mysqli_prepare($conn, $sql_ulasan);
    mysqli_stmt_bind_param($stmt_ulasan, "i", $produk_id);
    mysqli_stmt_execute($stmt_ulasan);
    $result_ulasan = mysqli_stmt_get_result($stmt_ulasan);
    $daftar_ulasan = mysqli_fetch_all($result_ulasan, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_ulasan);

    // Ambil data media untuk setiap ulasan
    foreach ($daftar_ulasan as &$ulasan) {
        $ulasan_id = $ulasan['ulasan_id'];
        $sql_media = "SELECT nama_file FROM ulasan_media WHERE ulasan_id = ?";
        $stmt_media = mysqli_prepare($conn, $sql_media);
        mysqli_stmt_bind_param($stmt_media, "i", $ulasan_id);
        mysqli_stmt_execute($stmt_media);
        $result_media = mysqli_stmt_get_result($stmt_media);
        $ulasan['media'] = mysqli_fetch_all($result_media, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_media);
    }
    ?>

    <div class="row mt-4">
        <div class="col-12">
            <h3 id="ulasan-pembeli">Ulasan Pembeli</h3>
            <?php if (!empty($daftar_ulasan)): ?>
                <?php foreach ($daftar_ulasan as $ulasan): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex align-items-start">
                                <img src="path/ke/gambar/user_default.png" alt="<?php echo htmlspecialchars($ulasan['nama_pelanggan']); ?>" class="rounded-circle mr-2" width="30" height="30">
                                <div>
                                    <h6 class="card-subtitle mb-0"><?php echo htmlspecialchars($ulasan['nama_pelanggan']); ?></h6>
                                    <p class="text-muted small mb-1"><?php echo date('d F Y H:i', strtotime($ulasan['tanggal_ulasan'])); ?></p>
                                    <div class="rating">
                                        <?php for ($i = 0; $i < $ulasan['rating']; $i++) echo '<i class="fas fa-star text-warning"></i>'; ?>
                                        <?php for ($i = $ulasan['rating']; $i < 5; $i++) echo '<i class="far fa-star text-warning"></i>'; ?>
                                    </div>
                                </div>
                            </div>
                            <p class="card-text mt-2"><?php echo htmlspecialchars($ulasan['komentar']); ?></p>
                            <?php if (!empty($ulasan['media'])): ?>
                                <div class="mt-2 d-flex flex-wrap">
                                    <?php foreach ($ulasan['media'] as $media): ?>
                                        <img src="path/ke/gambar/ulasan/<?php echo htmlspecialchars($media['nama_file']); ?>" alt="Gambar Ulasan" class="img-thumbnail mr-2 mb-2" style="max-width: 100px; max-height: 100px;">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Belum ada ulasan untuk produk ini.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php
} else {
    echo "<p>ID Produk tidak valid.</p>";
}
?>
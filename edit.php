<?php
include 'config/koneksi.php';
include 'includes/header.php';

$id_barang = $_GET['id'] ?? '';
$query = mysqli_query($conn, "SELECT * FROM barang WHERE id_barang = '$id_barang'");
$b = mysqli_fetch_assoc($query);

if (!$b) {
    echo "<script>alert('Data tidak ditemukan!'); window.location='index.php';</script>";
    exit;
}

if (isset($_POST['update_barang'])) {
    $nama_barang = mysqli_real_escape_string($conn, $_POST['nama_barang']);
    $kategori    = mysqli_real_escape_string($conn, $_POST['kategori']);
    $merk_tipe   = mysqli_real_escape_string($conn, $_POST['merk_tipe']);
    $nomor_seri  = mysqli_real_escape_string($conn, $_POST['nomor_seri']);
    $lokasi      = mysqli_real_escape_string($conn, $_POST['lokasi']);
    $deskripsi   = mysqli_real_escape_string($conn, $_POST['deskripsi']);

    $q = "UPDATE barang SET 
          nama_barang = '$nama_barang',
          kategori = '$kategori',
          merk_tipe = '$merk_tipe',
          nomor_seri = '$nomor_seri',
          lokasi = '$lokasi',
          deskripsi = '$deskripsi'
          WHERE id_barang = '$id_barang'";

    if (mysqli_query($conn, $q)) {
        echo "<script>alert('Data Berhasil Diperbarui!'); window.location='index.php';</script>";
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white fw-bold">
                ✏️ Edit Data Barang (<?= $b['kode_barang']; ?>)
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nama Barang</label>
                            <input type="text" name="nama_barang" class="form-control" value="<?= htmlspecialchars($b['nama_barang']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Kategori</label>
                            <input type="text" name="kategori" class="form-control" value="<?= htmlspecialchars($b['kategori']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Merk / Tipe</label>
                            <input type="text" name="merk_tipe" class="form-control" value="<?= htmlspecialchars($b['merk_tipe']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nomor Seri (S/N)</label>
                            <input type="text" name="nomor_seri" class="form-control" value="<?= htmlspecialchars($b['nomor_seri']); ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Lokasi Penyimpanan</label>
                            <input type="text" name="lokasi" class="form-control" value="<?= htmlspecialchars($b['lokasi']); ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($b['deskripsi']); ?></textarea>
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <a href="index.php" class="btn btn-secondary me-2">Batal</a>
                        <button type="submit" name="update_barang" class="btn btn-success fw-bold">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

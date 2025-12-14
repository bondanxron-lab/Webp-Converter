<?php
// Mulai session di paling atas
session_start();

// --- KONFIGURASI PENTING (DIULANG DARI FILE UTAMA UNTUK KEPASTIAN) ---
define('UPLOAD_DIR', 'img/');
define('PASSWORD_HASH', 'yourpassword'); // Hash untuk 'admin'
define('ITEMS_PER_PAGE', 20); // <-- Item per halaman

// --- LOGIKA OTENTIKASI & VARIABEL ---
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

// Tambahan: Cek cookie untuk login persisten (Ingat Saya)
if (!$is_logged_in && isset($_COOKIE['rem_user']) && $_COOKIE['rem_user'] === 'persistent') {
    $_SESSION['is_logged_in'] = true;
    $is_logged_in = true; // Perbarui status
}

if (!$is_logged_in) {
    // Arahkan ke halaman login jika belum login
    header('Location: index.php'); // Asumsi file utama Anda bernama index.php
    exit;
}

$message = null;
$message_type = null;

// Utility function (diulang untuk standalone script)
function formatBytes(int $bytes, int $precision = 2): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $base = log($bytes, 1024);
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
}

// --- LOGIKA PENGHAPUSAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $file_to_delete = basename($_POST['delete_file']); // Ambil hanya nama file, hapus path
    $file_path = UPLOAD_DIR . $file_to_delete;

    // Pemeriksaan keamanan kritis: Pastikan file berada di UPLOAD_DIR dan ada
    if (file_exists($file_path) && is_file($file_path) && strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) === 'webp') {
        if (unlink($file_path)) {
            $message = "File <strong>" . htmlspecialchars($file_to_delete) . "</strong> berhasil dihapus.";
            $message_type = 'success';
        } else {
            $message = "Gagal menghapus file <strong>" . htmlspecialchars($file_to_delete) . "</strong>. Cek izin server.";
            $message_type = 'error';
        }
    } else {
        $message = "File tidak ditemukan atau tidak valid: " . htmlspecialchars($file_to_delete);
        $message_type = 'error';
    }
}

// --- LOGIKA DAFTAR FILE ---
// Ambil semua file .webp dalam direktori upload
$files = glob(UPLOAD_DIR . '*.webp');
$image_list = [];
$total_disk_size = 0; // <-- Variabel baru untuk total ukuran

foreach ($files as $filepath) {
    $current_file_size = filesize($filepath);
    $total_disk_size += $current_file_size; // <-- Tambahkan ukuran file ke total

    $filename = basename($filepath);
    $image_list[] = [
        'name' => $filename,
        'path' => $filepath,
        'size_bytes' => $current_file_size,
        'size_human' => formatBytes($current_file_size),
        'time' => filemtime($filepath),
        'date' => date('d M Y H:i:s', filemtime($filepath)),
    ];
}

// Sortir dari terbaru ke terlama
usort($image_list, function($a, $b) {
    return $b['time'] <=> $a['time'];
});

// --- LOGIKA PAGINATION BARU ---
$total_files = count($image_list);
$view_all = isset($_GET['view']) && $_GET['view'] === 'all';
$current_page = max(1, intval($_GET['page'] ?? 1)); // Dapatkan halaman saat ini, default ke 1

if ($view_all) {
    $paginated_list = $image_list; // Tampilkan semua
    $total_pages = 1;
    $current_page = 1;
} else {
    $total_pages = ceil($total_files / ITEMS_PER_PAGE);
    if ($total_pages == 0) $total_pages = 1; // Pastikan minimal 1 halaman
    $current_page = max(1, min($total_pages, $current_page)); // Pastikan halaman saat ini valid

    $offset = ($current_page - 1) * ITEMS_PER_PAGE;
    $paginated_list = array_slice($image_list, $offset, ITEMS_PER_PAGE);
}
// --- AKHIR LOGIKA PAGINATION ---

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen File Konversi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5; --primary-hover: #4338ca; --text-color: #111827;
            --subtle-text-color: #6b7280; --background-color: #f9fafb; --card-background: #ffffff;
            --border-color: #e5e7eb; --success-color: #059669; --error-color: #dc2626; --error-bg: #fee2e2;
            --delete-color: #ef4444; --delete-hover: #dc2626;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif; line-height: 1.6;
            background: linear-gradient(170deg, #f9fafb 0%, #eef2f7 100%);
            color: var(--text-color); margin: 0; padding: 1.5rem;
            display: flex; justify-content: center; min-height: 100vh;
        }
        .container {
            width: 100%; max-width: 1000px; background-color: var(--card-background);
            padding: clamp(1.5rem, 5vw, 2.5rem); border-radius: 1.5rem;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05), 0 10px 10px -5px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
        }
        header { text-align: center; margin-bottom: 2rem; }
        h1 { font-size: clamp(1.5rem, 4vw, 2rem); font-weight: 700; margin: 0 0 0.5rem 0; }
        .nav-links { margin-top: 1rem; }
        .nav-links a { color: var(--primary-color); text-decoration: none; font-weight: 500; margin: 0 0.75rem; transition: color 0.2s; }
        .nav-links a:hover { color: var(--primary-hover); text-decoration: underline; }
        
        /* --- CSS STATISTIK BARU DITAMBAHKAN --- */
        .stats-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2.5rem; margin-top: 1.5rem; }
        .stats-item { background: linear-gradient(145deg, #f9fafb, #eef2f7); border: 1px solid var(--border-color); border-radius: 1rem; padding: 1rem; display: flex; align-items: center; gap: 1rem; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .stats-item:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.04); }
        .stats-icon { flex-shrink: 0; width: 48px; height: 48px; display: grid; place-items: center; background-color: var(--primary-color); border-radius: 50%; color: white; }
        .stats-icon svg { width: 24px; height: 24px; }
        .stats-info strong { display: block; color: var(--text-color); font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
        .stats-info span { color: var(--subtle-text-color); font-size: 0.875rem; }
        /* --- AKHIR DARI CSS STATISTIK --- */

        .message { padding: 1rem; border-radius: 0.5rem; text-align: center; font-weight: 500; margin-bottom: 1.5rem; }
        .message.error { background-color: var(--error-bg); color: var(--error-color); }
        .message.success { background-color: #d1fae5; color: var(--success-color); }
        .file-table-wrapper { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 0.75rem; }
        .file-table { width: 100%; border-collapse: collapse; min-width: 700px; }
        .file-table th, .file-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .file-table th { background-color: var(--background-color); color: var(--subtle-text-color); font-weight: 500; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
        .file-table tbody tr:last-child td { border-bottom: none; }
        .file-table td img { width: 60px; height: auto; border-radius: 0.25rem; border: 1px solid #ccc; }
        .delete-btn { background-color: var(--delete-color); color: white; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; font-weight: 500; transition: background-color 0.2s; }
        .delete-btn:hover { background-color: var(--delete-hover); }
        .no-files { text-align: center; padding: 3rem; color: var(--subtle-text-color); font-size: 1.125rem; }
        
        /* --- CSS PAGINATION BARU --- */
        .pagination-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .pagination-links {
            display: flex;
            gap: 0.75rem;
        }
        .pagination-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .pagination-btn:hover {
            background-color: var(--primary-hover);
        }
        .pagination-btn.disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }
        .view-toggle {
            font-size: 0.875rem;
            color: var(--subtle-text-color);
        }
        .view-toggle span {
            margin-right: 0.75rem;
        }
        .view-toggle a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .view-toggle a:hover {
            text-decoration: underline;
        }
        /* --- AKHIR CSS PAGINATION --- */
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Manajemen File WebP</h1>
            <p>Kelola (lihat dan hapus) gambar yang telah dikonversi.</p>
            <div class="nav-links">
                <a href="index.php">← Kembali ke Konverter</a>
                <a href="?logout=true">Logout &rarr;</a>
            </div>
        </header>

        <!-- --- KOTAK STATISTIK BARU DITAMBAHKAN --- -->
        <div class="stats-bar">
            <div class="stats-item">
                <!-- --- IKON TELAH DIGANTI --- -->
                <div class="stats-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg></div>
                <div class="stats-info"><strong><?= number_format($total_files) ?></strong><span>File Tersimpan di Disk</span></div>
            </div>
            <div class="stats-item">
                <div class="stats-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg></div>
                <div class="stats-info"><strong><?= htmlspecialchars(formatBytes($total_disk_size)) ?></strong><span>Total Ukuran di Disk</span></div>
            </div>
        </div>
        <!-- --- AKHIR DARI KOTAK STATISTIK --- -->


        <?php if ($message): ?>
            <div class="message <?= $message_type ?>"><?= $message ?></div>
        <?php endif; ?>

        <?php if (empty($image_list)): ?>
            <div class="no-files">
                <p>Belum ada gambar yang tersimpan.</p>
            </div>
        <?php else: ?>
            <div class="file-table-wrapper">
                <table class="file-table">
                    <thead>
                        <tr>
                            <th>Pratinjau</th>
                            <th>Nama File</th>
                            <th>Ukuran</th>
                            <th>Tanggal Konversi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginated_list as $file): // <-- DIGANTI KE $paginated_list ?>
                        <tr>
                            <td><img src="<?= htmlspecialchars($file['path']) ?>" alt="Pratinjau" loading="lazy"></td>
                            <td>
                                <a href="<?= htmlspecialchars($file['path']) ?>" target="_blank" rel="noopener noreferrer" style="color: var(--text-color);"><?= htmlspecialchars($file['name']) ?></a>
                            </td>
                            <td><?= htmlspecialchars($file['size_human']) ?></td>
                            <td><?= htmlspecialchars($file['date']) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus file <?= htmlspecialchars($file['name']) ?>?');">
                                    <input type="hidden" name="delete_file" value="<?= htmlspecialchars($file['name']) ?>">
                                    <button type="submit" class="delete-btn">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- --- KONTROL PAGINATION BARU --- -->
            <div class="pagination-controls">
                <div class="pagination-links">
                    <?php if (!$view_all && $current_page > 1): ?>
                        <a href="?page=<?= $current_page - 1 ?>" class="pagination-btn">&larr; Sebelumnya</a>
                    <?php else: ?>
                        <span class="pagination-btn disabled" style="background-color: #9ca3af; cursor: not-allowed;">&larr; Sebelumnya</span>
                    <?php endif; ?>
                    
                    <?php if (!$view_all && $current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?>" class="pagination-btn">Berikutnya &rarr;</a>
                    <?php else: ?>
                        <span class="pagination-btn disabled" style="background-color: #9ca3af; cursor: not-allowed;">Berikutnya &rarr;</span>
                    <?php endif; ?>
                </div>

                <div class="view-toggle">
                    <?php if ($view_all): ?>
                        <span>Menampilkan Semua (<?= $total_files ?> file)</span>
                        <a href="?page=1">Lihat per Halaman</a>
                    <?php else: ?>
                        <span>Halaman <?= $current_page ?> dari <?= $total_pages ?> (Total <?= $total_files ?> file)</span>
                        <a href="?view=all">Lihat Semua</a>
                    <?php endif; ?>
                </div>
            </div>
            <!-- --- AKHIR KONTROL PAGINATION --- -->

        <?php endif; ?>
    </div>
</body>
</html>


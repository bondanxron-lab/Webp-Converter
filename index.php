<?php
// Mulai session di paling atas
session_start();

// --- KONFIGURASI ---
// Ganti hash ini dengan hash dari kata sandi Anda.
define('PASSWORD_HASH', 'yourpassword');

define('UPLOAD_DIR', 'img/');
define('MAX_FILE_SIZE', 10485760); // 10 MB
define('WEBP_QUALITY', 70);
define('STATS_FILE', 'converter_stats.json');

// --- KONFIGURASI WATERMARK (TEKS) ---
define('ENABLE_WATERMARK', true); // Ganti ke false untuk menonaktifkan watermark
define('WATERMARK_TEXT', '© watchfullreplay.com');
define('WATERMARK_FONT_PATH', __DIR__ . '/Inter-Regular.ttf'); // Pastikan file Inter-Regular.ttf ada di sini
define('WATERMARK_FONT_SIZE', 16);
define('WATERMARK_PADDING', 15); // Jarak dari pinggir gambar
// Warna dalam format RGBA: [Merah, Hijau, Biru, Transparansi (0-127)]
// 0 = tidak transparan, 127 = transparan penuh. 60 adalah sekitar 50% transparan.
define('WATERMARK_COLOR', [255, 0, 68, 70]);


const ALLOWED_MIME_TYPES = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'
];

// --- LOGIKA OTENTIKASI ---
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

// Tambahan: Cek cookie untuk login persisten (Ingat Saya)
if (!$is_logged_in && isset($_COOKIE['rem_user']) && $_COOKIE['rem_user'] === 'persistent') {
    $_SESSION['is_logged_in'] = true;
    $is_logged_in = true; // Perbarui status
}

$login_error = null;

// Proses logout
if (isset($_GET['logout'])) {
    // Hapus cookie persisten saat logout
    setcookie('rem_user', '', time() - 3600, '/', '', false, true);

    session_unset();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (password_verify($_POST['password'], PASSWORD_HASH)) {
        $_SESSION['is_logged_in'] = true;

        // Logic Ingat Saya: atur cookie selama 14 hari jika dicentang
        if (isset($_POST['remember_me']) && $_POST['remember_me'] === 'on') {
            $expire = time() + (86400 * 14); // 14 hari dalam detik
            setcookie('rem_user', 'persistent', $expire, '/', '', false, true);
        } else {
            // Hapus cookie persisten jika login tanpa mencentang 'Ingat Saya'
            setcookie('rem_user', '', time() - 3600, '/', '', false, true);
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = 'Kata sandi yang Anda masukkan salah.';
    }
}


// --- VARIABEL & FUNGSI APLIKASI (hanya jika sudah login) ---
if ($is_logged_in) {
    $error_message = null;
    $result = null;

    /**
     * Menambahkan watermark teks ke data gambar.
     * @param string $imageData Data biner dari gambar.
     * @return string|false Data gambar baru dengan watermark, atau false jika gagal.
     */
    function addWatermark(string $imageData) {
        // Cek apakah GD terinstall
        if (!function_exists('imagecreatefromstring')) {
            error_log('Fungsi Watermark: GD Library tidak terinstall.');
            return false;
        }
        // Cek apakah file font ada
        if (!file_exists(WATERMARK_FONT_PATH)) {
            error_log('Fungsi Watermark: File font tidak ditemukan di ' . WATERMARK_FONT_PATH);
            return false;
        }

        $image = @imagecreatefromstring($imageData);
        if ($image === false) {
            error_log('Fungsi Watermark: Gagal membuat gambar dari string.');
            return false;
        }

        // Aktifkan alpha blending untuk transparansi
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Siapkan warna watermark dengan alpha channel
        $color = imagecolorallocatealpha($image, WATERMARK_COLOR[0], WATERMARK_COLOR[1], WATERMARK_COLOR[2], WATERMARK_COLOR[3]);

        // Hitung posisi untuk menempatkan watermark di kanan bawah
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        $textBox = imagettfbbox(WATERMARK_FONT_SIZE, 0, WATERMARK_FONT_PATH, WATERMARK_TEXT);
        $textWidth = $textBox[2] - $textBox[0];
        $textHeight = $textBox[1] - $textBox[7]; // Perkiraan tinggi

        $posX = $imageWidth - $textWidth - WATERMARK_PADDING;
        $posY = $imageHeight - $textHeight - WATERMARK_PADDING + $textHeight; // Posisi baseline teks

        // Tambahkan teks ke gambar
        imagettftext($image, WATERMARK_FONT_SIZE, 0, $posX, $posY, $color, WATERMARK_FONT_PATH, WATERMARK_TEXT);

        // Tangkap output gambar ke variabel, bukan ke file
        ob_start();
        imagewebp($image, null, WEBP_QUALITY);
        $watermarkedImageData = ob_get_clean();

        // Hapus resource gambar dari memori
        imagedestroy($image);

        return $watermarkedImageData;
    }

    function formatBytes(int $bytes, int $precision = 2): string {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = log($bytes, 1024);
        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
    }

    function fetchUrl(string $url, bool $get_body = true): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Image-Converter-Bot/1.0', CURLOPT_FAILONERROR => true,
            CURLOPT_NOBODY => !$get_body
        ]);
        $data = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);
        return ['data' => $data, 'info' => $info, 'error' => $error];
    }

    function getStats(): array {
        if (!file_exists(STATS_FILE)) return ['count' => 0, 'total_size' => 0];
        $data = @file_get_contents(STATS_FILE);
        $stats = @json_decode($data, true);
        return is_array($stats) && isset($stats['count'], $stats['total_size']) ? $stats : ['count' => 0, 'total_size' => 0];
    }

    function saveStats(array $stats): bool {
        $fp = fopen(STATS_FILE, 'w');
        if (!$fp || !flock($fp, LOCK_EX)) return false;
        $success = fwrite($fp, json_encode($stats, JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
        fclose($fp);
        return (bool)$success;
    }

    $stats = getStats();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image_url'])) {
        $remote_url = trim($_POST['image_url']);
        try {
            if (empty($remote_url)) throw new Exception('URL gambar tidak boleh kosong.');
            if (!filter_var($remote_url, FILTER_VALIDATE_URL)) throw new Exception('Format URL tidak valid.');
            $parsed_url = parse_url($remote_url);
            if (!in_array($parsed_url['scheme'], ['http', 'https'])) throw new Exception('Hanya skema URL http dan https yang diizinkan.');
            $ip_address = gethostbyname($parsed_url['host']);
            if (!filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw new Exception('URL menunjuk ke alamat IP privat atau terlarang.');
            
            $original_headers = fetchUrl($remote_url, false)['info'];
            if ($original_headers['http_code'] !== 200) throw new Exception('Gagal mengakses gambar. Status: ' . $original_headers['http_code']);
            $original_size = $original_headers['download_content_length'] ?? -1;
            if ($original_size > MAX_FILE_SIZE) throw new Exception('Ukuran file asli (' . formatBytes($original_size) . ') melebihi batas ' . formatBytes(MAX_FILE_SIZE) . '.');
            if ($original_size <= 0) throw new Exception('Tidak dapat menentukan ukuran file dari header.');
            
            $original_image_data = fetchUrl($remote_url)['data'];
            if (!$original_image_data) throw new Exception('Gagal mengunduh konten gambar asli.');
            $image_info = @getimagesizefromstring($original_image_data);
            if ($image_info === false || !in_array($image_info['mime'], ALLOWED_MIME_TYPES)) throw new Exception('File yang diunduh bukan format gambar yang didukung.');
            
            $api_url = sprintf('https://images.weserv.nl/?url=%s&output=webp&quality=%d', urlencode($remote_url), WEBP_QUALITY);
            $conversion_response = fetchUrl($api_url);
            $converted_image_data = $conversion_response['data'];
            
            if (!$converted_image_data || $conversion_response['info']['http_code'] !== 200) {
                $api_status_code = $conversion_response['info']['http_code'];
                
                if ($api_status_code == 404) {
                    throw new Exception('API_404_ERROR'); // Lemparkan error khusus untuk 404
                }

                $api_curl_error = $conversion_response['error'];
                $error_details = "Status: $api_status_code.";
                if (!empty($api_curl_error)) {
                    $error_details .= " cURL error: " . $api_curl_error;
                } elseif ($converted_image_data) {
                    $error_details .= " Response: " . htmlspecialchars(substr($converted_image_data, 0, 200));
                }
                throw new Exception('Gagal melakukan konversi gambar via API. ' . $error_details);
            }
            
            // --- PROSES PENAMBAHAN WATERMARK (TEKS) ---
            if (ENABLE_WATERMARK) {
                $watermarked_data = addWatermark($converted_image_data);
                if ($watermarked_data !== false) {
                    $converted_image_data = $watermarked_data; // Ganti data gambar dengan yang sudah ada watermark
                } else {
                    // Opsional: tambahkan pesan error jika watermarking gagal tapi konversi berhasil
                    // Untuk saat ini, kita biarkan gambar asli (tanpa watermark) yang disimpan
                    error_log("Proses watermarking gagal, menyimpan gambar tanpa watermark.");
                }
            }
            // --- AKHIR PROSES WATERMARK (TEKS) ---

            $converted_size = strlen($converted_image_data);
            if ($converted_size === 0) throw new Exception('Hasil konversi gambar kosong.');
            if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) throw new Exception('Gagal membuat direktori ' . UPLOAD_DIR);
            if (!is_writable(UPLOAD_DIR)) throw new Exception('Direktori ' . UPLOAD_DIR . ' tidak dapat ditulisi.');
            
            $file_name = time() . '_' . bin2hex(random_bytes(8)) . '.webp';
            $file_path = UPLOAD_DIR . $file_name;
            if (file_put_contents($file_path, $converted_image_data) === false) throw new Exception('Gagal menyimpan file ke server.');
            
            $result = [
                'saved_url' => $file_path, 'file_name' => $file_name,
                'original_size_bytes' => $original_size, 'original_size_human' => formatBytes($original_size),
                'converted_size_bytes' => $converted_size, 'converted_size_human' => formatBytes($converted_size),
            ];
            
            $stats['count']++;
            $stats['total_size'] += $converted_size;
            saveStats($stats);

        } catch (Exception $e) {
            $original_error = $e->getMessage();
            if ($original_error === 'API_404_ERROR') {
                // Pesan error khusus untuk 404 dari API, tidak di-escape karena mengandung HTML
                $error_message = '<strong>Gagal Konversi: Gambar Tidak Ditemukan (404)</strong><div style="text-align: left; margin-top: 1rem; font-weight: 400;">' .
                                 'Layanan konversi tidak dapat menemukan atau mengakses gambar dari URL yang Anda berikan. Kemungkinan penyebabnya:<ul>' .
                                 '<li style="margin-bottom: 0.5rem;">URL gambar asli salah, atau gambar telah dipindahkan/dihapus.</li>' .
                                 '<li style="margin-bottom: 0.5rem;">Website sumber memblokir akses dari layanan pihak ketiga (dikenal sebagai <i>hotlink protection</i>).</li>' .
                                 '<li>Gambar tersebut memerlukan login atau izin khusus untuk dapat dilihat.</li></ul>' .
                                 '<strong>Saran:</strong> Coba simpan gambar ke komputer Anda terlebih dahulu, lalu unggah ke layanan hosting gambar gratis (seperti <a href="https://postimages.org/" target="_blank" rel="noopener noreferrer" style="color: inherit;">Postimages.org</a>), kemudian gunakan URL baru dari sana.</div>';
            } else {
                // Penanganan error umum lainnya
                $error_message = htmlspecialchars($original_error, ENT_QUOTES, 'UTF-8');
                if (strpos(strtolower($original_error), 'timed out') !== false) {
                     $error_message .= "<br><br><strong>Saran:</strong> Error ini biasanya terjadi karena koneksi antara server Anda dan API lambat atau terputus saat mengunduh. Timeout telah ditingkatkan menjadi 60 detik. Jika masalah berlanjut, hubungi penyedia hosting Anda mengenai kemungkinan adanya pembatasan koneksi keluar (firewall).";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_logged_in ? 'Konverter Gambar ke WebP' : 'Login' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5; --primary-hover: #4338ca; --text-color: #111827;
            --subtle-text-color: #6b7280; --background-color: #f9fafb; --card-background: #ffffff;
            --border-color: #e5e7eb; --success-color: #059669; --error-color: #dc2626; --error-bg: #fee2e2;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif; line-height: 1.6;
            background: linear-gradient(170deg, #f9fafb 0%, #eef2f7 100%);
            color: var(--text-color); margin: 0; padding: 1.5rem;
            display: flex; justify-content: center; align-items: center; min-height: 100vh;
        }
        .container {
            width: 100%; max-width: 700px; background-color: var(--card-background);
            padding: clamp(1.5rem, 5vw, 2.5rem); border-radius: 1.5rem;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05), 0 10px 10px -5px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
        }
        .login-container { max-width: 400px; text-align: center; }
        header { text-align: center; margin-bottom: 2rem; }
        h1 { font-size: clamp(1.5rem, 4vw, 2rem); font-weight: 700; margin: 0 0 0.5rem 0; }
        header p { color: var(--subtle-text-color); margin: 0 auto; max-width: 45ch; }
        .stats-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2.5rem; }
        .stats-item { background: linear-gradient(145deg, #f9fafb, #eef2f7); border: 1px solid var(--border-color); border-radius: 1rem; padding: 1rem; display: flex; align-items: center; gap: 1rem; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .stats-item:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.04); }
        .stats-icon { flex-shrink: 0; width: 48px; height: 48px; display: grid; place-items: center; background-color: var(--primary-color); border-radius: 50%; color: white; }
        .stats-icon svg { width: 24px; height: 24px; }
        .stats-info strong { display: block; color: var(--text-color); font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
        .stats-info span { color: var(--subtle-text-color); font-size: 0.875rem; }
        form { position: relative; }
        .input-group { display: flex; flex-wrap: wrap; gap: 0.75rem; background-color: var(--background-color); border-radius: 0.75rem; padding: 0.5rem; border: 1px solid var(--border-color); }
        .input-group input[type="url"], .input-group input[type="password"] { flex-grow: 1; padding: 0.75rem 1rem; border: none; border-radius: 0.5rem; font-size: 1rem; transition: box-shadow 0.2s; min-width: 200px; background-color: white; }
        .input-group input:focus { outline: none; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
        .input-group button { padding: 0.75rem 1.5rem; background-color: var(--primary-color); color: white; border: none; border-radius: 0.5rem; font-size: 1rem; font-weight: 500; cursor: pointer; transition: background-color 0.2s, transform 0.1s; }
        .input-group button:hover { background-color: var(--primary-hover); }
        .input-group button:active { transform: scale(0.98); }
        .input-group button:disabled { background-color: #9ca3af; cursor: not-allowed; }
        .loader { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); border: 4px solid #f3f3f3; border-top: 4px solid var(--primary-color); border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; display: none; }
        form.loading .input-group { opacity: 0.5; pointer-events: none; }
        form.loading .loader { display: block; }
        @keyframes spin { 100% { transform: translate(-50%, -50%) rotate(360deg); } }
        .message { padding: 1rem; border-radius: 0.5rem; margin-top: 1.5rem; text-align: center; font-weight: 500; }
        .message.error { background-color: var(--error-bg); color: var(--error-color); }
        .result-container { margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 2rem; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .result h2 { text-align: center; margin-bottom: 1.5rem; color: var(--success-color); font-weight: 700; }
        .result-grid { display: grid; gap: 0.75rem; margin-bottom: 1.5rem; }
        .result-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background-color: var(--background-color); border-radius: 0.5rem; border: 1px solid var(--border-color); }
        .result-item span:first-child { font-weight: 500; color: var(--subtle-text-color); }
        .result-item span:last-child { font-weight: 500; color: var(--text-color); }
        .result-item a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .result-item a:hover { text-decoration: underline; }
        .preview { text-align: center; margin-top: 1.5rem; }
        .preview h3 { font-size: 1.125rem; margin-bottom: 1rem; color: var(--subtle-text-color); font-weight: 500; }
        .preview img { max-width: 100%; height: auto; border-radius: 0.5rem; border: 1px solid var(--border-color); background-color: #fff; padding: 0.25rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.05); }
        .logout-link { display: block; text-align: right; margin-top: -1rem; margin-bottom: 1rem; }
        .logout-link a { color: var(--subtle-text-color); font-size: 0.875rem; text-decoration: none; margin-left: 1rem; } /* Menambahkan margin-left agar tautan tidak menempel */
        .logout-link a:first-child { margin-left: 0; margin-right: 1rem; }
        .logout-link a:hover { text-decoration: underline; color: var(--primary-color); }
    </style>
</head>
<body>
    <?php if ($is_logged_in): ?>
    <div class="container">
        <div class="logout-link">
            <!-- Tautan baru ke halaman manajemen file -->
            <a href="manager.php">Manajemen File</a>
            <a href="?logout=true">Logout &rarr;</a>
        </div>
        <header>
            <h1>Konverter Gambar ke WebP</h1>
            <p>Ubah gambar dari URL menjadi format WebP yang modern dan efisien.</p>
        </header>
        <div class="stats-bar">
            <div class="stats-item">
                <div class="stats-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg></div>
                <div class="stats-info"><strong><?= number_format($stats['count']) ?></strong><span>Gambar Dikonversi</span></div>
            </div>
            <div class="stats-item">
                <div class="stats-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg></div>
                <div class="stats-info"><strong><?= htmlspecialchars(formatBytes($stats['total_size'])) ?></strong><span>Total Ukuran Tersimpan</span></div>
            </div>
        </div>
        <form action="" method="POST" id="converter-form">
            <div class="input-group">
                <input type="url" name="image_url" placeholder="https://example.com/path/to/image.jpg" required value="<?= htmlspecialchars($_POST['image_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Konversi</button>
            </div><div class="loader"></div>
        </form>
        <div id="output-area">
            <?php if ($error_message): ?><div class="message error"><?= $error_message ?></div><?php endif; ?>
            <?php if ($result): ?>
            <div class="result-container">
                <div class="result">
                    <h2>✨ Konversi Berhasil! ✨</h2>
                    <div class="result-grid">
                        <div class="result-item"><span>File Disimpan</span><span><?= htmlspecialchars($result['file_name'], ENT_QUOTES, 'UTF-8') ?></span></div>
                        <div class="result-item"><span>Ukuran Asli</span><span><?= htmlspecialchars($result['original_size_human'], ENT_QUOTES, 'UTF-8') ?> <small>(<?= htmlspecialchars($result['original_size_bytes'], ENT_QUOTES, 'UTF-8') ?> B)</small></span></div>
                        <div class="result-item"><span>Ukuran WebP</span><span><?= htmlspecialchars($result['converted_size_human'], ENT_QUOTES, 'UTF-8') ?> <small>(<?= htmlspecialchars($result['converted_size_bytes'], ENT_QUOTES, 'UTF-8') ?> B)</small></span></div>
                        <div class="result-item"><span>URL Langsung</span><span><a href="<?= htmlspecialchars($result['saved_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Buka di Tab Baru</a></span></div>
                    </div>
                    <div class="preview">
                        <h3>Pratinjau</h3>
                        <img src="<?= htmlspecialchars($result['saved_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Gambar hasil konversi">
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="container login-container">
        <h1>Akses Terbatas</h1>
        <p>Silakan masukkan kata sandi untuk melanjutkan.</p>
        <form action="" method="POST" style="margin-top: 2rem;">
            <div class="input-group">
                <input type="password" name="password" placeholder="••••••••" required autofocus>
                <button type="submit" id="login-button">Login</button>
            </div>
            <!-- Bagian baru untuk Ingat Saya -->
            <div style="text-align: left; margin-top: 0.75rem;">
                <input type="checkbox" id="remember_me" name="remember_me">
                <label for="remember_me" style="font-size: 0.875rem; color: var(--subtle-text-color);">Ingat Saya selama 14 hari</label>
            </div>
        </form>
        <?php if ($login_error): ?>
            <div class="message error" style="margin-bottom: 0;">
                <?= htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <script>
        if (document.getElementById('converter-form')) {
            document.getElementById('converter-form').addEventListener('submit', function() {
                if (this.checkValidity()) { this.classList.add('loading'); document.getElementById('output-area').innerHTML = ''; }
            });
        }
    </script>
</body>
</html>

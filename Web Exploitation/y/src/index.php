<?php
// PHAR CORP Seller Hub
// Aplikasi single-file, routing via ?page=

$USERS = [
    'owen@phar.com'    => '10281038103810381082',
    'dean@phar.com'    => '10281038103810381082',
    'tanto@phar.com'   => 'PHAR190206',
    'udin@phar.com'    => '10281038103810381082',
    'bintang@phar.com' => '10281038103810381082',
];

function b64url_encode($d) { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function b64url_decode($d) { return base64_decode(strtr($d, '-_', '+/')); }

// Direktori tempat kunci penandatanganan JWT disimpan.
$SECRET_DIR = '/app/keys/';

function jwt_encode(array $payload, $kid = 'keys/default.key') {
    global $SECRET_DIR;
    $secret = @file_get_contents($SECRET_DIR . $kid);
    if ($secret === false) $secret = '';
    $h   = b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256', 'kid' => $kid]));
    $p   = b64url_encode(json_encode($payload));
    $sig = b64url_encode(hash_hmac('sha256', "$h.$p", $secret, true));
    return "$h.$p.$sig";
}

function jwt_decode($token) {
    global $SECRET_DIR;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    $header = json_decode(b64url_decode($parts[0]), true);
    if (!is_array($header)) return false;
    $kid = $header['kid'] ?? 'keys/default.key';
    // VULNERABLE: nilai "kid" dipakai langsung sebagai path file tanpa sanitasi
    // -> path traversal (mis. ../../../../dev/null) untuk memilih kunci yang isinya diketahui.
    $secret = @file_get_contents($SECRET_DIR . $kid);
    if ($secret === false) return false; // file kunci harus ada
    $expected = b64url_encode(hash_hmac('sha256', "$parts[0].$parts[1]", $secret, true));
    if (!hash_equals($expected, $parts[2])) return false;
    $payload = json_decode(b64url_decode($parts[1]), true);
    return is_array($payload) ? $payload : false;
}

function current_user() {
    if (empty($_COOKIE['token'])) return null;
    $p = jwt_decode($_COOKIE['token']);
    if (!$p || empty($p['username'])) return null;
    return $p;
}

function is_admin($u) { return $u && isset($u['role']) && $u['role'] === 'admin'; }

$me   = current_user();
$page = $_GET['page'] ?? 'home';

// Logout
if ($page === 'logout') {
    setcookie('token', '', ['expires' => time() - 3600, 'path' => '/']);
    header('Location: ?page=login');
    exit;
}

// Login (POST)
$login_error = '';
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    if (isset($USERS[$email]) && hash_equals($USERS[$email], $pass)) {
        $payload = [
            'username' => explode('@', $email)[0],
            'email'    => $email,
            'role'     => 'seller',
            'iat'      => 1700000000,
        ];
        setcookie('token', jwt_encode($payload), [
            'expires' => 0, 'path' => '/', 'httponly' => false, 'samesite' => 'Lax',
        ]);
        header('Location: ?page=dashboard');
        exit;
    }
    $login_error = 'Email atau password salah. Silakan coba lagi.';
}

$forgot_msg = '';
if ($page === 'forgot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $forgot_msg = 'Gagal mengirim link reset — layanan email kami sedang bermasalah (SMTP 550). '
                . 'Silakan gunakan panduan reset manual di bawah untuk sementara.';
}

$need_login = ['dashboard','analytics','orders','products','customers','payouts','reviews','promotions','settings','profile'];
if (in_array($page, $need_login, true) && !$me) {
    header('Location: ?page=login');
    exit;
}

$profile_notice = '';
$profile_uploaded = '';
if ($page === 'profile') {
    if (!is_admin($me)) {
        http_response_code(403);
        $page = 'forbidden';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
        $f = $_FILES['avatar'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $profile_notice = '<div class="alert alert-error">Upload gagal (kode: ' . intval($f['error']) . ').</div>';
        } else {
            $name = basename($f['name']);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $blocked = ['php','php2','php3','php4','php5','php6','php7','phps','pht','phtml','phpt'];
            if (in_array($ext, $blocked, true)) {
                http_response_code(403);
                $profile_notice = '<div class="alert alert-error">🛡️ <b>WAF: Forbidden</b> — Malicious extension detected: <b>.'
                                . htmlspecialchars($ext) . '</b></div>';
            } else {
                $dest = __DIR__ . '/uploads/' . $name;
                if (move_uploaded_file($f['tmp_name'], $dest)) {
                    $profile_uploaded = 'uploads/' . $name;
                    $profile_notice = '<div class="alert alert-info">✅ Foto profil berhasil diunggah ke <b>'
                                    . htmlspecialchars($profile_uploaded) . '</b></div>';
                } else {
                    $profile_notice = '<div class="alert alert-error">Gagal menyimpan file di server. Silakan coba lagi.</div>';
                }
            }
        }
    }
}

function dash_head($title) {
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<title>PHAR CORP</title>'
       . '<link rel="stylesheet" href="assets/style.css">'
       . '<link rel="stylesheet" href="assets/dashboard.css"></head><body><div class="app">';
}

function dash_sidebar($active, $me) {
    $main = [
        ['dashboard',  '📊', 'Overview'],
        ['analytics',  '📈', 'Analytics'],
        ['orders',     '🧾', 'Orders'],
        ['products',   '📦', 'Products'],
        ['customers',  '👥', 'Customers'],
        ['payouts',    '💳', 'Payouts'],
    ];
    $store = [
        ['reviews',    '⭐', 'Reviews'],
        ['promotions', '📣', 'Promotions'],
        ['settings',   '⚙️', 'Settings'],
    ];
    echo '<aside class="side"><div class="brand"><div class="logo">P</div><div>PHAR CORP</div></div>';
    echo '<div class="grp">Menu Utama</div><nav>';
    foreach ($main as $i) {
        $cls = ($active === $i[0]) ? ' class="active"' : '';
        echo '<a href="?page=' . $i[0] . '"' . $cls . '><span class="ic">' . $i[1] . '</span> ' . $i[2] . '</a>';
    }
    echo '</nav><div class="grp">Toko</div><nav>';
    foreach ($store as $i) {
        $cls = ($active === $i[0]) ? ' class="active"' : '';
        echo '<a href="?page=' . $i[0] . '"' . $cls . '><span class="ic">' . $i[1] . '</span> ' . $i[2] . '</a>';
    }
    if (is_admin($me)) {
        $cls = ($active === 'profile') ? ' admin-only active' : ' admin-only';
        echo '<a href="?page=profile" class="' . trim($cls) . '"><span class="ic">🛡️</span> Admin Panel</a>';
    }
    echo '</nav><div class="grp">Akun</div><nav>';
    echo '<a href="?page=logout"><span class="ic">🚪</span> Logout</a>';
    echo '</nav></aside>';
}

function dash_topbar($title, $subtitle, $me) {
    $uname = htmlspecialchars($me['username']);
    $q = trim($_GET['q'] ?? '');
    echo '<main class="main"><div class="topbar"><div>';
    echo '<h1>' . $title . '</h1>';
    echo '<div style="color:var(--muted); font-size:14px;">' . $subtitle . '</div></div>';
    echo '<div class="who"><form method="GET" style="margin:0;">'
       . '<input type="hidden" name="page" value="' . htmlspecialchars($_GET['page'] ?? 'dashboard') . '">'
       . '<input class="search" type="text" name="q" placeholder="🔍 Cari..." value="' . htmlspecialchars($q) . '"></form>';
    echo '<div class="chip">👤 <b>' . $uname . '</b></div>';
    echo '<div class="role">' . (is_admin($me) ? 'ADMIN' : 'SELLER') . '</div></div></div>';
    if ($q !== '') {
        echo '<div class="alert alert-info mb18" style="margin-top:0;">🔍 Hasil pencarian untuk <b>"'
           . htmlspecialchars($q) . '"</b> — tidak ditemukan. Coba kata kunci lain.</div>';
    }
}

function dash_foot() {
    echo '<p style="color:var(--muted); font-size:13px; margin-top:26px;">'
       . 'PHAR CORP Seller Hub v2.4.1 — © 2026</p></main></div></body></html>';
}

switch ($page):

case 'home': ?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PHAR CORP</title>
<link rel="stylesheet" href="assets/style.css">
</head><body>

<div class="nav">
  <div class="brand"><div class="logo">P</div><div>PHAR CORP<br><small>Seller Hub</small></div></div>
  <div class="nav-links">
    <a href="#produk">Produk</a><a href="#fitur">Fitur</a><a href="#testi">Testimoni</a><a href="#kontak">Kontak</a>
    <?php if ($me): ?><a href="?page=dashboard" class="btn btn-primary">Dashboard</a>
    <?php else: ?><a href="?page=login" class="btn btn-primary">Masuk Seller</a><?php endif; ?>
  </div>
</div>

<div class="container">
  <div class="hero">
    <div>
      <span class="pill">🚀 #1 Marketplace Seller Tools</span>
      <h1>Kelola Toko Online-mu <span>Lebih Cepat</span> Bersama PHAR CORP</h1>
      <p class="lead">Platform all-in-one untuk ribuan pedagang: analitik penjualan real-time,
        manajemen stok otomatis, dan pembayaran instan. Sudah dipercaya lebih dari 48.000 seller aktif.</p>
      <div style="display:flex; gap:12px; margin-top:26px;">
        <a href="?page=login" class="btn btn-primary">Mulai Jualan &rarr;</a>
        <a href="#fitur" class="btn btn-ghost">Pelajari Fitur</a>
      </div>
    </div>
    <div class="hero-card">
      <div style="font-weight:800; margin-bottom:10px;">📈 Ringkasan Toko Hari Ini</div>
      <div class="row"><span class="k">Total Penjualan</span><span class="v">Rp 128.400.000</span></div>
      <div class="row"><span class="k">Pesanan Baru</span><span class="v">1.204 <span class="up">▲ 12%</span></span></div>
      <div class="row"><span class="k">Pengunjung Toko</span><span class="v">89.302</span></div>
      <div class="row"><span class="k">Rating Seller</span><span class="v">4.9 ★</span></div>
    </div>
  </div>
</div>

<div class="section" id="fitur"><div class="container">
  <h2>Kenapa Pilih PHAR CORP?</h2>
  <p class="sub">Semua yang kamu butuhkan untuk scale up bisnis online, dalam satu dashboard.</p>
  <div class="grid-3">
    <div class="card"><div class="icn">📊</div><h3>Analitik Real-Time</h3><p>Pantau omzet, konversi, dan performa produk langsung dari dashboard kami.</p></div>
    <div class="card"><div class="icn">📦</div><h3>Manajemen Stok</h3><p>Sinkronisasi stok otomatis lintas marketplace, anti kehabisan barang.</p></div>
    <div class="card"><div class="icn">⚡</div><h3>Pencairan Instan</h3><p>Dana penjualan cair dalam hitungan menit, tanpa ribet verifikasi ulang.</p></div>
    <div class="card"><div class="icn">🛡️</div><h3>Proteksi Penjual</h3><p>Sistem keamanan berlapis melindungi akun dan saldo seller-mu 24/7.</p></div>
    <div class="card"><div class="icn">🤝</div><h3>Support Prioritas</h3><p>Tim CS khusus siap membantu kapan saja lewat live chat dan telepon.</p></div>
    <div class="card"><div class="icn">🌐</div><h3>Jangkauan Nasional</h3><p>Ekspansi ke 514 kota dengan jaringan logistik partner terluas.</p></div>
  </div>
</div></div>

<div class="section" id="produk"><div class="container">
  <h2>Promo Spesial Minggu Ini 🔥</h2>
  <p class="sub">Produk pilihan dengan diskon terbesar untuk seller PHAR CORP.</p>
  <div class="grid-4">
    <div class="product"><div class="thumb">🎧</div><div class="body"><span class="badge">-40%</span><h3 style="margin:8px 0 4px;">Headset</h3><div><span class="price">Rp 299.000</span><span class="old">Rp 499.000</span></div></div></div>
    <div class="product"><div class="thumb">⌚</div><div class="body"><span class="badge">-25%</span><h3 style="margin:8px 0 4px;">Smartwatch Ultra</h3><div><span class="price">Rp 749.000</span><span class="old">Rp 999.000</span></div></div></div>
    <div class="product"><div class="thumb">📱</div><div class="body"><span class="badge">-15%</span><h3 style="margin:8px 0 4px;">Powerbank 20.000mAh</h3><div><span class="price">Rp 169.000</span><span class="old">Rp 199.000</span></div></div></div>
    <div class="product"><div class="thumb">⌨️</div><div class="body"><span class="badge">-33%</span><h3 style="margin:8px 0 4px;">Keyboard Mechanical</h3><div><span class="price">Rp 399.000</span><span class="old">Rp 599.000</span></div></div></div>
  </div>
</div></div>

<div class="section" id="testi"><div class="container">
  <h2>Apa Kata Para Seller Kami 💬</h2>
  <p class="sub">Ribuan pedagang sudah membuktikan. Ini cerita mereka.</p>
  <div class="grid-3">
    <div class="testi"><div class="stars">★★★★★</div><p>"Sejak gabung PHAR CORP, omzet toko saya naik 3x lipat. Dashboard-nya mudah dipahami!"</p><div class="who"><div class="avatar">O</div><div><div class="name">Owen</div><div class="email">owen@phar.com</div></div></div></div>
    <div class="testi"><div class="stars">★★★★★</div><p>"Pencairan dana instan sangat membantu. Modal terus berputar, cash flow jadi aman."</p><div class="who"><div class="avatar">D</div><div><div class="name">Dean</div><div class="email">dean@phar.com</div></div></div></div>
    <div class="testi"><div class="stars">★★★★★</div><p>"Awalnya ragu jualan online, tapi tim support PHAR CORP sabar sekali membimbing dari nol. Puas!"</p><div class="who"><div class="avatar">T</div><div><div class="name">Tanto</div><div class="email">tanto@phar.com</div></div></div></div>
    <div class="testi"><div class="stars">★★★★☆</div><p>"Fitur manajemen stoknya sangat membantu. Tidak pernah lagi kejadian oversold saat order sedang ramai."</p><div class="who"><div class="avatar">U</div><div><div class="name">Udin</div><div class="email">udin@phar.com</div></div></div></div>
    <div class="testi"><div class="stars">★★★★★</div><p>"Analitiknya lengkap dan real-time. Keputusan bisnis jadi lebih cepat dan akurat. Sangat direkomendasikan!"</p><div class="who"><div class="avatar">B</div><div><div class="name">Bintang</div><div class="email">bintang@phar.com</div></div></div></div>
    <div class="testi" style="display:grid; place-items:center; text-align:center;"><div><div style="font-size:34px; font-weight:900; color:var(--brand2);">4.9/5</div><div class="stars">★★★★★</div><p style="color:var(--muted);">dari 12.480 ulasan seller terverifikasi</p></div></div>
  </div>
</div></div>

<div class="section"><div class="container">
  <div class="hero-card" style="text-align:center; padding:44px;">
    <h2 style="margin:0 0 8px;">Siap Jadi Seller Sukses Berikutnya?</h2>
    <p style="color:var(--muted); margin:0 0 22px;">Masuk ke Seller Hub dan mulai kelola tokomu sekarang juga.</p>
    <a href="?page=login" class="btn btn-primary">Masuk ke Seller Hub</a>
  </div>
</div></div>

<div class="footer" id="kontak"><div class="container">
  <div class="cols">
    <div><div class="brand" style="margin-bottom:12px;"><div class="logo">P</div><div>PHAR CORP</div></div>
      <p style="max-width:320px;">Pusat e-commerce dan seller tools terlengkap di Indonesia. Menara PHAR Lt. 21, Jakarta.</p></div>
    <div><h4>Perusahaan</h4><a href="#">Tentang Kami</a><a href="#">Karir</a><a href="#">Blog</a></div>
    <div><h4>Bantuan</h4><a href="#">Pusat Bantuan</a><a href="?page=login">Login Seller</a><a href="#">Hubungi CS</a></div>
    <div><h4>Legal</h4><a href="#">Kebijakan Privasi</a><a href="#">Syarat & Ketentuan</a></div>
  </div>
  <p style="margin-top:26px; border-top:1px solid var(--line); padding-top:18px;">© 2026 PHAR CORP. All rights reserved. support@phar.com</p>
</div></div>

</body></html>
<?php break;

// login
case 'login': ?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PHAR CORP</title><link rel="stylesheet" href="assets/style.css">
</head><body>
<div class="nav">
  <a href="?page=home" class="brand" style="color:inherit; text-decoration:none;"><div class="logo">P</div><div>PHAR CORP<br><small>Seller Hub</small></div></a>
  <div class="nav-links"><a href="?page=home">← Kembali ke Beranda</a></div>
</div>
<div class="auth-wrap"><div class="auth-card">
  <h1>Masuk Seller</h1>
  <p class="muted">Kelola tokomu di PHAR CORP Seller Hub.</p>
  <?php if ($login_error): ?><div class="alert alert-error"><?= htmlspecialchars($login_error) ?></div><?php endif; ?>
  <form method="POST" action="?page=login">
    <div class="field"><label>Email</label><input type="email" name="email" placeholder="nama@phar.com" required autofocus></div>
    <div class="field"><label>Password</label><input type="password" name="password" placeholder="••••••••" required></div>
    <button type="submit" class="btn btn-primary btn-block">Masuk</button>
  </form>
  <div class="auth-links"><a href="?page=forgot">Lupa password?</a><span class="muted" style="font-size:13px;">Belum punya akun? Hubungi CS.</span></div>
  <div class="divider">butuh bantuan?</div>
  <a href="files/guide.pdf" class="btn btn-ghost btn-block" download>📘 Download Panduan Seller (Guide.pdf)</a>
</div></div>
</body></html>
<?php break;

// forgot
case 'forgot': ?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PHAR CORP</title><link rel="stylesheet" href="assets/style.css">
</head><body>
<div class="nav">
  <a href="?page=home" class="brand" style="color:inherit; text-decoration:none;"><div class="logo">P</div><div>PHAR CORP<br><small>Seller Hub</small></div></a>
  <div class="nav-links"><a href="?page=login">← Kembali ke Login</a></div>
</div>
<div class="auth-wrap"><div class="auth-card">
  <h1>Lupa Password</h1>
  <p class="muted">Masukkan email seller kamu, kami akan mengirim link untuk reset password.</p>
  <?php if ($forgot_msg): ?><div class="alert alert-error"><?= htmlspecialchars($forgot_msg) ?></div><?php endif; ?>
  <form method="POST" action="?page=forgot">
    <div class="field"><label>Email Terdaftar</label><input type="email" name="email" placeholder="nama@phar.com" required autofocus></div>
    <button type="submit" class="btn btn-primary btn-block">Kirim Tautan Reset</button>
  </form>
  <div class="divider">atau</div>
  <a href="files/manual.pdf" class="btn btn-accent btn-block" download>📄 Panduan Reset Manual (Manual.pdf)</a>
  <div class="auth-links" style="justify-content:center;"><a href="?page=login">Ingat password? Masuk di sini</a></div>
</div></div>
</body></html>
<?php break;

// forbidden
case 'forbidden': ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><link rel="stylesheet" href="assets/style.css"></head><body>
<div class="auth-wrap"><div class="auth-card">
  <h1>403 Forbidden</h1>
  <p class="muted">Halaman ini khusus untuk administrator.</p>
  <a href="?page=dashboard" class="btn btn-primary btn-block">Kembali ke Dashboard</a>
</div></div></body></html>
<?php break;

// dashboard
case 'dashboard':
    dash_head('Dashboard');
    dash_sidebar('dashboard', $me);
    dash_topbar('Selamat datang kembali, ' . htmlspecialchars($me['username']) . ' 👋', 'Ringkasan performa tokomu • 15 Juli 2026', $me);
    ?>
    <!-- Pengumuman dari admin -->
    <div class="panel mb18" style="border-left:4px solid var(--brand);">
      <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
        <span class="ava" style="background:linear-gradient(135deg,#f43f5e,#8b5cf6);">A</span>
        <div style="flex:1;">
          <div style="font-weight:800;">ADMIN <span style="color:var(--muted); font-weight:500; font-size:13px;">&lt;admin@phar.com&gt;</span></div>
          <div style="color:var(--muted); font-size:12px;">📌 Disematkan • 14 Juli 2026, 09:12</div>
        </div>
        <span class="tag b">PENGUMUMAN</span>
      </div>
      <p style="margin:0; color:var(--txt);">
        Halo para <b>seller</b> 👋 Sistem login kami baru saja bermigrasi ke <b>JWT</b> untuk pengalaman
        yang lebih cepat dan lancar. Jika kamu mengalami kendala login atau sesi tiba-tiba keluar sendiri,
        coba logout lalu login ulang. Tim kami terus memantau kestabilan sistem selama masa rollout ini.
        Terima kasih atas pengertiannya! — <i>Tim Admin</i>
      </p>
    </div>
    <?php if (is_admin($me)): ?>
      <div class="alert alert-info mb18">🔓 Mode Administrator aktif. Kamu punya akses ke pengaturan lanjutan termasuk <a href="?page=profile">Admin Panel</a>.</div>
    <?php endif; ?>
    <div class="stats">
      <div class="stat"><div class="lbl"><span>Total Revenue</span><div class="ic">💰</div></div><div class="val">Rp 128,4jt</div><div class="delta up">▲ 12,4% dari bulan lalu</div></div>
      <div class="stat"><div class="lbl"><span>Pesanan</span><div class="ic">🧾</div></div><div class="val">1,204</div><div class="delta up">▲ 8,1% minggu ini</div></div>
      <div class="stat"><div class="lbl"><span>Pengunjung</span><div class="ic">👁️</div></div><div class="val">89,302</div><div class="delta down">▼ 2,3% dari kemarin</div></div>
      <div class="stat"><div class="lbl"><span>Konversi</span><div class="ic">🎯</div></div><div class="val">3,84%</div><div class="delta up">▲ 0,6%</div></div>
    </div>
    <div class="panels">
      <div class="panel">
        <h3>Grafik Penjualan</h3><p class="muted">Pendapatan 7 hari terakhir (dalam juta Rupiah)</p>
        <div class="chart">
          <div class="bar" style="height:55%"><span>Sen</span></div><div class="bar" style="height:72%"><span>Sel</span></div>
          <div class="bar" style="height:48%"><span>Rab</span></div><div class="bar" style="height:85%"><span>Kam</span></div>
          <div class="bar" style="height:66%"><span>Jum</span></div><div class="bar" style="height:95%"><span>Sab</span></div>
          <div class="bar" style="height:78%"><span>Min</span></div>
        </div><div style="height:14px"></div>
      </div>
      <div class="panel">
        <h3>Kategori Terlaris</h3><p class="muted">Distribusi penjualan per kategori</p>
        <div class="donut"></div>
        <ul class="legend">
          <li><span class="dot" style="background:var(--brand)"></span> Elektronik — 45%</li>
          <li><span class="dot" style="background:var(--brand2)"></span> Fashion — 27%</li>
          <li><span class="dot" style="background:var(--accent)"></span> Rumah Tangga — 16%</li>
          <li><span class="dot" style="background:var(--card2)"></span> Lainnya — 12%</li>
        </ul>
      </div>
    </div>
    <div class="panel">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <h3 style="margin:0;">Pesanan Terbaru</h3><button class="btn btn-ghost" onclick="return false">Export CSV</button>
      </div>
      <table class="tbl"><thead><tr><th>Order ID</th><th>Produk</th><th>Pelanggan</th><th>Total</th><th>Status</th></tr></thead><tbody>
        <tr><td>#ORD-90241</td><td>Headset</td><td>Rina S.</td><td>Rp 299.000</td><td><span class="status ok">Selesai</span></td></tr>
        <tr><td>#ORD-90238</td><td>Smartwatch Ultra</td><td>Budi H.</td><td>Rp 749.000</td><td><span class="status pend">Dikirim</span></td></tr>
        <tr><td>#ORD-90233</td><td>Keyboard Mechanical</td><td>Sari W.</td><td>Rp 399.000</td><td><span class="status ok">Selesai</span></td></tr>
        <tr><td>#ORD-90230</td><td>Powerbank 20.000mAh</td><td>Andi P.</td><td>Rp 169.000</td><td><span class="status can">Dibatalkan</span></td></tr>
        <tr><td>#ORD-90228</td><td>Headset</td><td>Maya L.</td><td>Rp 299.000</td><td><span class="status pend">Diproses</span></td></tr>
      </tbody></table>
    </div>
    <?php dash_foot();
    break;

// analytics
case 'analytics':
    dash_head('Analytics'); dash_sidebar('analytics', $me);
    dash_topbar('Analytics', 'Wawasan performa toko secara mendalam', $me); ?>
    <div class="filters">
      <button class="pill-tab active" onclick="return false">7 Hari</button><button class="pill-tab" onclick="return false">30 Hari</button>
      <button class="pill-tab" onclick="return false">3 Bulan</button><button class="pill-tab" onclick="return false">1 Tahun</button>
      <button class="pill-tab" onclick="return false">📅 Custom</button>
    </div>
    <div class="stats">
      <div class="stat"><div class="lbl"><span>Impresi</span><div class="ic">👁️</div></div><div class="val">1,24 jt</div><div class="delta up">▲ 18,2%</div></div>
      <div class="stat"><div class="lbl"><span>Klik Produk</span><div class="ic">🖱️</div></div><div class="val">312 rb</div><div class="delta up">▲ 9,4%</div></div>
      <div class="stat"><div class="lbl"><span>Add to Cart</span><div class="ic">🛒</div></div><div class="val">48,9 rb</div><div class="delta down">▼ 3,1%</div></div>
      <div class="stat"><div class="lbl"><span>Konversi</span><div class="ic">🎯</div></div><div class="val">3,84%</div><div class="delta up">▲ 0,6%</div></div>
    </div>
    <div class="grid-2c mb18">
      <div class="panel">
        <h3>Tren Pendapatan</h3><p class="muted">Perbandingan minggu ini vs minggu lalu</p>
        <svg class="linechart" viewBox="0 0 600 200" preserveAspectRatio="none">
          <defs><linearGradient id="g1" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.5"/><stop offset="100%" stop-color="#8b5cf6" stop-opacity="0"/></linearGradient></defs>
          <polyline fill="url(#g1)" stroke="none" points="0,150 100,110 200,130 300,70 400,90 500,40 600,60 600,200 0,200"/>
          <polyline fill="none" stroke="#c084fc" stroke-width="3" points="0,150 100,110 200,130 300,70 400,90 500,40 600,60"/>
          <polyline fill="none" stroke="#8a97b4" stroke-width="2" stroke-dasharray="5 5" points="0,170 100,150 200,155 300,120 400,130 500,110 600,120"/>
        </svg>
      </div>
      <div class="panel">
        <h3>Sumber Trafik</h3><p class="muted">Dari mana pengunjung datang</p>
        <div class="mb18"><div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;"><span>Organik</span><b>42%</b></div><div class="progress"><i style="width:42%"></i></div></div>
        <div class="mb18"><div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;"><span>Iklan</span><b>28%</b></div><div class="progress"><i style="width:28%"></i></div></div>
        <div class="mb18"><div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;"><span>Sosial Media</span><b>19%</b></div><div class="progress"><i style="width:19%"></i></div></div>
        <div><div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;"><span>Referral</span><b>11%</b></div><div class="progress"><i style="width:11%"></i></div></div>
      </div>
    </div>
    <div class="panel">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;"><h3 style="margin:0;">Produk Paling Dilihat</h3><button class="btn btn-ghost" onclick="return false">Unduh Laporan</button></div>
      <table class="tbl"><thead><tr><th>Produk</th><th>Dilihat</th><th>Terjual</th><th>Konversi</th><th>Tren</th></tr></thead><tbody>
        <tr><td>Headset</td><td>84.201</td><td>3.120</td><td>3,7%</td><td><span class="tag g">▲ naik</span></td></tr>
        <tr><td>Smartwatch Ultra</td><td>61.882</td><td>1.905</td><td>3,1%</td><td><span class="tag g">▲ naik</span></td></tr>
        <tr><td>Keyboard Mechanical</td><td>44.310</td><td>1.410</td><td>3,2%</td><td><span class="tag">— stabil</span></td></tr>
        <tr><td>Powerbank 20.000mAh</td><td>39.775</td><td>980</td><td>2,5%</td><td><span class="tag a">▼ turun</span></td></tr>
      </tbody></table>
    </div>
    <?php dash_foot();
    break;

// orders
case 'orders':
    dash_head('Orders'); dash_sidebar('orders', $me);
    dash_topbar('Orders', 'Kelola semua pesanan tokomu', $me); ?>
    <div class="stats">
      <div class="stat"><div class="lbl"><span>Perlu Diproses</span><div class="ic">⏳</div></div><div class="val">37</div><div class="delta">butuh tindakan</div></div>
      <div class="stat"><div class="lbl"><span>Dikirim</span><div class="ic">🚚</div></div><div class="val">128</div><div class="delta up">on-track</div></div>
      <div class="stat"><div class="lbl"><span>Selesai</span><div class="ic">✅</div></div><div class="val">1.021</div><div class="delta up">▲ 8,1%</div></div>
      <div class="stat"><div class="lbl"><span>Dibatalkan</span><div class="ic">✖️</div></div><div class="val">18</div><div class="delta down">1,5%</div></div>
    </div>
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <div class="filters">
        <button class="pill-tab active" onclick="return false">Semua</button><button class="pill-tab" onclick="return false">Perlu Diproses</button>
        <button class="pill-tab" onclick="return false">Dikirim</button><button class="pill-tab" onclick="return false">Selesai</button>
        <button class="pill-tab" onclick="return false">Dibatalkan</button>
      </div>
      <button class="btn btn-primary" onclick="return false">＋ Buat Pesanan Manual</button>
    </div>
    <div class="panel">
      <table class="tbl"><thead><tr><th>Order ID</th><th>Tanggal</th><th>Produk</th><th>Pelanggan</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>
        <tr><td>#ORD-90241</td><td>15 Jul 2026</td><td>Headset</td><td>Rina Susanti</td><td>Rp 299.000</td><td><span class="status ok">Selesai</span></td><td><button class="btn btn-ghost" onclick="return false">Detail</button></td></tr>
        <tr><td>#ORD-90238</td><td>15 Jul 2026</td><td>Smartwatch Ultra</td><td>Budi Hartono</td><td>Rp 749.000</td><td><span class="status pend">Dikirim</span></td><td><button class="btn btn-ghost" onclick="return false">Detail</button></td></tr>
        <tr><td>#ORD-90236</td><td>14 Jul 2026</td><td>Keyboard Mechanical</td><td>Sari Wulandari</td><td>Rp 399.000</td><td><span class="status pend">Diproses</span></td><td><button class="btn btn-ghost" onclick="return false">Detail</button></td></tr>
        <tr><td>#ORD-90233</td><td>14 Jul 2026</td><td>Powerbank 20.000mAh</td><td>Andi Pratama</td><td>Rp 169.000</td><td><span class="status ok">Selesai</span></td><td><button class="btn btn-ghost" onclick="return false">Detail</button></td></tr>
        <tr><td>#ORD-90230</td><td>13 Jul 2026</td><td>Headset</td><td>Maya Lestari</td><td>Rp 299.000</td><td><span class="status can">Dibatalkan</span></td><td><button class="btn btn-ghost" onclick="return false">Detail</button></td></tr>
        <tr><td>#ORD-90228</td><td>13 Jul 2026</td><td>Smartwatch Ultra</td><td>Rizky Ananda</td><td>Rp 749.000</td><td><span class="status pend">Diproses</span></td><td><button class="btn btn-ghost" onclick="return false">Detail</button></td></tr>
        <tr><td>#ORD-90225</td><td>12 Jul 2026</td><td>Keyboard Mechanical</td><td>Dewi Anggraini</td><td>Rp 399.000</td><td><span class="status ok">Selesai</span></td><td><button class="btn btn-ghost" onclick="return false">Detail</button></td></tr>
      </tbody></table>
      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; color:var(--muted); font-size:13px;">
        <span>Menampilkan 6 dari 1.204 pesanan</span>
        <div class="filters" style="margin:0;"><button class="pill-tab" onclick="return false">‹ Prev</button><button class="pill-tab active" onclick="return false">1</button><button class="pill-tab" onclick="return false">2</button><button class="pill-tab" onclick="return false">3</button><button class="pill-tab" onclick="return false">Next ›</button></div>
      </div>
    </div>
    <?php dash_foot();
    break;

// products
case 'products':
    dash_head('Products'); dash_sidebar('products', $me);
    dash_topbar('Products', 'Katalog produk tokomu', $me);
    $products = [
      ['🎧','Headset','Rp 299.000',124,'Aktif','g'],['⌚','Smartwatch Ultra','Rp 749.000',58,'Aktif','g'],
      ['📱','Powerbank 20.000mAh','Rp 169.000',0,'Habis','a'],['⌨️','Keyboard Mechanical','Rp 399.000',33,'Aktif','g'],
      ['🖱️','Mouse Wireless RGB','Rp 149.000',210,'Aktif','g'],['🔌','Charger Fast 65W','Rp 199.000',12,'Stok Menipis','a'],
      ['🎮','Gamepad Bluetooth','Rp 259.000',77,'Aktif','g'],['💡','Lampu LED Strip','Rp 89.000',5,'Stok Menipis','a'],
    ]; ?>
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <div class="filters"><button class="pill-tab active" onclick="return false">Semua (312)</button><button class="pill-tab" onclick="return false">Aktif (298)</button><button class="pill-tab" onclick="return false">Habis (8)</button><button class="pill-tab" onclick="return false">Arsip (6)</button></div>
      <button class="btn btn-primary" onclick="return false">＋ Tambah Produk</button>
    </div>
    <div class="grid-4">
      <?php foreach ($products as $p): ?>
      <div class="pcard"><div class="ph"><?= $p[0] ?></div><div class="pb">
        <h4><?= htmlspecialchars($p[1]) ?></h4>
        <div style="color:var(--accent); font-weight:800; margin-bottom:8px;"><?= $p[2] ?></div>
        <div style="display:flex; justify-content:space-between; align-items:center;"><span class="tag <?= $p[5] ?>"><?= $p[4] ?></span><span style="color:var(--muted); font-size:12px;">Stok: <?= $p[3] ?></span></div>
        <div style="display:flex; gap:8px; margin-top:12px;"><button class="btn btn-ghost" style="flex:1; padding:8px;" onclick="return false">✏️ Edit</button><button class="btn btn-ghost" style="padding:8px;" onclick="return false">🗑️</button></div>
      </div></div>
      <?php endforeach; ?>
    </div>
    <?php dash_foot();
    break;

// customers
case 'customers':
    dash_head('Customers'); dash_sidebar('customers', $me);
    dash_topbar('Customers', 'Data pelanggan setia tokomu', $me);
    $cust = [
      ['R','Rina Susanti','rina.s@gmail.com',42,'Rp 6.480.000','VIP','a'],['B','Budi Hartono','budi.h@gmail.com',18,'Rp 3.120.000','Reguler','b'],
      ['S','Sari Wulandari','sari.w@gmail.com',31,'Rp 5.010.000','VIP','a'],['A','Andi Pratama','andi.p@gmail.com',7,'Rp 980.000','Baru','g'],
      ['M','Maya Lestari','maya.l@gmail.com',24,'Rp 4.200.000','Reguler','b'],['D','Dewi Anggraini','dewi.a@gmail.com',12,'Rp 2.100.000','Reguler','b'],
    ]; ?>
    <div class="stats">
      <div class="stat"><div class="lbl"><span>Total Pelanggan</span><div class="ic">👥</div></div><div class="val">18.402</div><div class="delta up">▲ 6,2%</div></div>
      <div class="stat"><div class="lbl"><span>Pelanggan Baru</span><div class="ic">✨</div></div><div class="val">1.204</div><div class="delta up">bulan ini</div></div>
      <div class="stat"><div class="lbl"><span>Pelanggan VIP</span><div class="ic">👑</div></div><div class="val">892</div><div class="delta">≥ Rp 5jt</div></div>
      <div class="stat"><div class="lbl"><span>Retensi</span><div class="ic">🔁</div></div><div class="val">68%</div><div class="delta up">▲ 2,4%</div></div>
    </div>
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <div class="filters"><button class="pill-tab active" onclick="return false">Semua</button><button class="pill-tab" onclick="return false">👑 VIP</button><button class="pill-tab" onclick="return false">Reguler</button><button class="pill-tab" onclick="return false">Baru</button></div>
      <button class="btn btn-primary" onclick="return false">📨 Kirim Broadcast</button>
    </div>
    <div class="panel">
      <table class="tbl"><thead><tr><th>Pelanggan</th><th>Email</th><th>Pesanan</th><th>Total Belanja</th><th>Segmen</th><th></th></tr></thead><tbody>
        <?php foreach ($cust as $c): ?>
        <tr><td><div style="display:flex; align-items:center; gap:10px;"><span class="ava"><?= $c[0] ?></span> <?= htmlspecialchars($c[1]) ?></div></td>
        <td style="color:var(--muted)"><?= htmlspecialchars($c[2]) ?></td><td><?= $c[3] ?></td><td><?= $c[4] ?></td>
        <td><span class="tag <?= $c[6] ?>"><?= $c[5] ?></span></td><td><button class="btn btn-ghost" onclick="return false">Lihat</button></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    </div>
    <?php dash_foot();
    break;

// payouts
case 'payouts':
    dash_head('Payouts'); dash_sidebar('payouts', $me);
    dash_topbar('Payouts', 'Saldo & pencairan dana penjualan', $me); ?>
    <div class="grid-2c mb18">
      <div class="panel balance">
        <div style="opacity:.85; font-size:14px;">Saldo Tersedia</div><div class="amt">Rp 42.850.000</div>
        <div style="opacity:.85; font-size:13px;">Saldo tertahan: Rp 3.120.000</div>
        <div style="margin-top:18px; display:flex; gap:10px;"><button class="btn" style="background:#fff; color:#4c1d95;" onclick="return false">💸 Tarik Dana</button><button class="btn btn-ghost" style="border-color:rgba(255,255,255,.4); color:#fff;" onclick="return false">Riwayat</button></div>
      </div>
      <div class="panel">
        <h3>Rekening Pencairan</h3><p class="muted">Tujuan transfer dana</p>
        <div class="mini-stat mb18"><div class="ring">🏦</div><div><div class="v">BCA •••• 4021</div><div class="l">a.n. PHAR CORP SELLER</div></div></div>
        <button class="btn btn-ghost btn-block" onclick="return false">＋ Tambah Rekening</button>
      </div>
    </div>
    <div class="stats">
      <div class="stat"><div class="lbl"><span>Total Dicairkan</span><div class="ic">💰</div></div><div class="val">Rp 1,2 M</div><div class="delta up">sepanjang 2026</div></div>
      <div class="stat"><div class="lbl"><span>Bulan Ini</span><div class="ic">📅</div></div><div class="val">Rp 128,4 jt</div><div class="delta up">▲ 12,4%</div></div>
      <div class="stat"><div class="lbl"><span>Proses Pending</span><div class="ic">⏳</div></div><div class="val">Rp 3,1 jt</div><div class="delta">1-2 hari kerja</div></div>
      <div class="stat"><div class="lbl"><span>Biaya Admin</span><div class="ic">🧮</div></div><div class="val">1,5%</div><div class="delta">per transaksi</div></div>
    </div>
    <div class="panel">
      <h3 style="margin:0 0 14px;">Riwayat Pencairan</h3>
      <table class="tbl"><thead><tr><th>ID</th><th>Tanggal</th><th>Jumlah</th><th>Metode</th><th>Status</th></tr></thead><tbody>
        <tr><td>#PO-5521</td><td>14 Jul 2026</td><td>Rp 15.000.000</td><td>BCA •••• 4021</td><td><span class="status ok">Berhasil</span></td></tr>
        <tr><td>#PO-5498</td><td>10 Jul 2026</td><td>Rp 22.500.000</td><td>BCA •••• 4021</td><td><span class="status ok">Berhasil</span></td></tr>
        <tr><td>#PO-5470</td><td>07 Jul 2026</td><td>Rp 3.120.000</td><td>BCA •••• 4021</td><td><span class="status pend">Diproses</span></td></tr>
        <tr><td>#PO-5443</td><td>03 Jul 2026</td><td>Rp 18.000.000</td><td>BCA •••• 4021</td><td><span class="status ok">Berhasil</span></td></tr>
      </tbody></table>
    </div>
    <?php dash_foot();
    break;

// reviews
case 'reviews':
    dash_head('Reviews'); dash_sidebar('reviews', $me);
    dash_topbar('Reviews', 'Ulasan & rating dari pelanggan', $me);
    $reviews = [
      ['R','Rina Susanti','★★★★★','Headset','Kualitas suara jernih, pengiriman cepat. Sangat direkomendasikan!'],
      ['B','Budi Hartono','★★★★☆','Smartwatch Ultra','Baterai awet, tapi strap-nya agak keras. Secara keseluruhan puas.'],
      ['S','Sari Wulandari','★★★★★','Keyboard Mechanical','Switch-nya enak dipakai untuk mengetik & gaming. Packing rapi.'],
      ['A','Andi Pratama','★★★☆☆','Powerbank 20.000mAh','Waktu ngecas agak lama, tapi kapasitas sesuai deskripsi.'],
    ]; ?>
    <div class="grid-2c mb18">
      <div class="panel" style="text-align:center;"><h3>Rating Toko</h3><div class="rating-big" style="color:var(--accent);">4.9</div><div class="stars-y" style="font-size:20px;">★★★★★</div><p class="muted" style="margin-top:8px;">dari 12.480 ulasan</p></div>
      <div class="panel"><h3>Distribusi Bintang</h3><p class="muted">Sebaran rating pelanggan</p>
        <?php foreach ([['5★',82],['4★',12],['3★',4],['2★',1],['1★',1]] as $r): ?>
          <div style="display:flex; align-items:center; gap:12px; margin-bottom:10px;"><span style="width:34px; color:var(--muted); font-size:13px;"><?= $r[0] ?></span><div class="progress" style="flex:1;"><i style="width:<?= $r[1] ?>%"></i></div><span style="width:40px; text-align:right; font-size:13px;"><?= $r[1] ?>%</span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="grid-2">
      <?php foreach ($reviews as $rv): ?>
      <div class="panel">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
          <div style="display:flex; align-items:center; gap:10px;"><span class="ava"><?= $rv[0] ?></span><div><div style="font-weight:700;"><?= htmlspecialchars($rv[1]) ?></div><div class="stars-y" style="font-size:13px;"><?= $rv[2] ?></div></div></div>
          <span class="tag b"><?= htmlspecialchars($rv[3]) ?></span>
        </div>
        <p style="color:var(--muted); font-size:14px; margin:0 0 12px;">"<?= htmlspecialchars($rv[4]) ?>"</p>
        <button class="btn btn-ghost" onclick="return false">↩️ Balas Ulasan</button>
      </div>
      <?php endforeach; ?>
    </div>
    <?php dash_foot();
    break;

// promotions
case 'promotions':
    dash_head('Promotions'); dash_sidebar('promotions', $me);
    dash_topbar('Promotions', 'Kelola voucher & kampanye promo', $me);
    $promos = [
      ['🔥','FLASH SALE 12.12','Diskon 40% semua elektronik','Aktif','g','Berakhir 3 hari lagi'],
      ['🎁','GRATIS ONGKIR','Min. belanja Rp 100.000','Aktif','g','Tanpa batas waktu'],
      ['🎉','WELCOME10','Diskon 10% pelanggan baru','Aktif','g','Otomatis'],
      ['💤','GAJIAN25','Diskon 25% akhir bulan','Terjadwal','a','Mulai 25 Jul 2026'],
    ]; ?>
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <div class="filters"><button class="pill-tab active" onclick="return false">Semua</button><button class="pill-tab" onclick="return false">Aktif</button><button class="pill-tab" onclick="return false">Terjadwal</button><button class="pill-tab" onclick="return false">Berakhir</button></div>
      <button class="btn btn-primary" onclick="return false">＋ Buat Promo Baru</button>
    </div>
    <div class="grid-2 mb18">
      <?php foreach ($promos as $p): ?>
      <div class="panel">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
          <div class="mini-stat"><div class="ring"><?= $p[0] ?></div><div><div class="v" style="font-size:16px;"><?= htmlspecialchars($p[1]) ?></div><div class="l"><?= htmlspecialchars($p[2]) ?></div></div></div>
          <span class="tag <?= $p[4] ?>"><?= $p[3] ?></span>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px;">
          <span style="color:var(--muted); font-size:12px;">⏱️ <?= htmlspecialchars($p[5]) ?></span>
          <div style="display:flex; gap:8px;"><button class="btn btn-ghost" style="padding:8px 12px;" onclick="return false">Edit</button><label class="toggle"><input type="checkbox" checked onclick="return false"><span class="track"></span></label></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="panel">
      <h3 style="margin:0 0 14px;">Performa Voucher</h3>
      <table class="tbl"><thead><tr><th>Kode</th><th>Dipakai</th><th>Total Diskon</th><th>Konversi</th><th>Status</th></tr></thead><tbody>
        <tr><td>FLASH1212</td><td>2.104</td><td>Rp 84.160.000</td><td>4,2%</td><td><span class="status ok">Aktif</span></td></tr>
        <tr><td>GRATISONGKIR</td><td>5.881</td><td>Rp 58.810.000</td><td>6,1%</td><td><span class="status ok">Aktif</span></td></tr>
        <tr><td>WELCOME10</td><td>1.204</td><td>Rp 12.040.000</td><td>8,4%</td><td><span class="status ok">Aktif</span></td></tr>
      </tbody></table>
    </div>
    <?php dash_foot();
    break;

// settings
case 'settings':
    dash_head('Settings'); dash_sidebar('settings', $me);
    dash_topbar('Settings', 'Pengaturan toko & akun', $me); ?>
    <div class="filters" id="settabs">
      <button class="pill-tab active" onclick="showTab(this,'toko')">Toko</button>
      <button class="pill-tab" onclick="showTab(this,'akun')">Akun</button>
      <button class="pill-tab" onclick="showTab(this,'notif')">Notifikasi</button>
      <button class="pill-tab" onclick="showTab(this,'keamanan')">Keamanan</button>
    </div>

    <!-- ===== TAB: TOKO ===== -->
    <div class="settab" id="tab-toko">
      <div class="grid-2c">
        <div class="panel">
          <h3>Informasi Toko</h3><p class="muted">Data ini tampil di halaman toko-mu</p>
          <form onsubmit="return false">
            <div class="form-row"><label>Nama Toko</label><input class="input" value="PHAR CORP Official Store"></div>
            <div class="form-row"><label>Deskripsi</label><textarea class="input" rows="3">Pusat elektronik & gadget terpercaya. Garansi resmi, pengiriman cepat.</textarea></div>
            <div class="grid-2"><div class="form-row"><label>Kota</label><input class="input" value="Jakarta Selatan"></div><div class="form-row"><label>Kategori Utama</label><select class="input"><option>Elektronik</option><option>Fashion</option><option>Rumah Tangga</option></select></div></div>
            <div class="form-row"><label>Email Kontak</label><input class="input" value="support@phar.com"></div>
            <button class="btn btn-primary" onclick="return false">💾 Simpan Perubahan</button>
          </form>
        </div>
        <div class="panel">
          <h3>Preferensi</h3><p class="muted">Atur perilaku toko</p>
          <div class="setting-line"><div><div class="t">Mode Liburan</div><div class="d">Tutup toko sementara</div></div><label class="toggle"><input type="checkbox"><span class="track"></span></label></div>
          <div class="setting-line"><div><div class="t">Auto-Accept Order</div><div class="d">Terima pesanan otomatis</div></div><label class="toggle"><input type="checkbox" checked><span class="track"></span></label></div>
          <div class="setting-line"><div><div class="t">Notifikasi Email</div><div class="d">Kirim ringkasan harian</div></div><label class="toggle"><input type="checkbox" checked><span class="track"></span></label></div>
          <div class="setting-line"><div><div class="t">Tampilkan Stok</div><div class="d">Perlihatkan jumlah stok ke pembeli</div></div><label class="toggle"><input type="checkbox"><span class="track"></span></label></div>
          <div class="setting-line"><div><div class="t">Chat Otomatis</div><div class="d">Balasan cepat saat offline</div></div><label class="toggle"><input type="checkbox" checked><span class="track"></span></label></div>
        </div>
      </div>
      <div class="panel" style="margin-top:18px;">
        <h3>Zona Berbahaya</h3><p class="muted">Tindakan berikut bersifat permanen</p>
        <div style="display:flex; gap:10px;"><button class="btn btn-ghost" onclick="return false">Reset Pengaturan</button><button class="btn" style="background:rgba(255,92,122,.15); color:var(--danger);" onclick="return false">Nonaktifkan Toko</button></div>
      </div>
    </div>

    <!-- ===== TAB: AKUN ===== -->
    <div class="settab" id="tab-akun" style="display:none;">
      <div class="grid-2c">
        <div class="panel">
          <h3>Informasi Akun</h3><p class="muted">Data pribadi pemilik akun</p>
          <form onsubmit="return false">
            <div class="form-row"><label>Nama Lengkap</label><input class="input" value="Tanto Wijaya"></div>
            <div class="grid-2"><div class="form-row"><label>Username</label><input class="input" value="tanto"></div><div class="form-row"><label>No. HP</label><input class="input" value="0812-3456-7890"></div></div>
            <div class="form-row"><label>Email</label><input class="input" value="tanto@phar.com"></div>
            <div class="form-row"><label>Zona Waktu</label><select class="input"><option>WIB (GMT+7)</option><option>WITA (GMT+8)</option><option>WIT (GMT+9)</option></select></div>
            <button class="btn btn-primary" onclick="return false">💾 Simpan Akun</button>
          </form>
        </div>
        <div class="panel">
          <h3>Ganti Password</h3><p class="muted">Perbarui kata sandi akunmu</p>
          <form onsubmit="return false">
            <div class="form-row"><label>Password Lama</label><input class="input" type="password" placeholder="••••••••"></div>
            <div class="form-row"><label>Password Baru</label><input class="input" type="password" placeholder="••••••••"></div>
            <div class="form-row"><label>Konfirmasi Password Baru</label><input class="input" type="password" placeholder="••••••••"></div>
            <button class="btn btn-primary" onclick="return false">🔒 Perbarui Password</button>
          </form>
          <div class="setting-line" style="margin-top:8px;"><div><div class="t">Foto Profil</div><div class="d">Kelola avatar akun</div></div><span class="ava">T</span></div>
        </div>
      </div>
    </div>

    <!-- ===== TAB: NOTIFIKASI ===== -->
    <div class="settab" id="tab-notif" style="display:none;">
      <div class="panel">
        <h3>Preferensi Notifikasi</h3><p class="muted">Pilih notifikasi yang ingin kamu terima</p>
        <div class="setting-line"><div><div class="t">Pesanan Baru (Email)</div><div class="d">Email tiap ada order masuk</div></div><label class="toggle"><input type="checkbox" checked><span class="track"></span></label></div>
        <div class="setting-line"><div><div class="t">Pesanan Baru (Push)</div><div class="d">Notifikasi push di perangkat</div></div><label class="toggle"><input type="checkbox" checked><span class="track"></span></label></div>
        <div class="setting-line"><div><div class="t">Ulasan Baru</div><div class="d">Saat pelanggan memberi rating</div></div><label class="toggle"><input type="checkbox" checked><span class="track"></span></label></div>
        <div class="setting-line"><div><div class="t">Promo & Kampanye</div><div class="d">Info program promo PHAR CORP</div></div><label class="toggle"><input type="checkbox"><span class="track"></span></label></div>
        <div class="setting-line"><div><div class="t">Newsletter Mingguan</div><div class="d">Ringkasan performa toko</div></div><label class="toggle"><input type="checkbox" checked><span class="track"></span></label></div>
        <div class="setting-line"><div><div class="t">SMS</div><div class="d">Notifikasi via pesan singkat</div></div><label class="toggle"><input type="checkbox"><span class="track"></span></label></div>
        <div style="margin-top:16px;"><button class="btn btn-primary" onclick="return false">💾 Simpan Preferensi</button></div>
      </div>
    </div>

    <!-- ===== TAB: KEAMANAN ===== -->
    <div class="settab" id="tab-keamanan" style="display:none;">
      <div class="grid-2c mb18">
        <div class="panel">
          <h3>Autentikasi Dua Faktor (2FA)</h3><p class="muted">Lapisan keamanan tambahan saat login</p>
          <div class="setting-line"><div><div class="t">Aktifkan 2FA</div><div class="d">Kode OTP via aplikasi authenticator</div></div><label class="toggle"><input type="checkbox"><span class="track"></span></label></div>
          <div class="setting-line"><div><div class="t">Login Alert</div><div class="d">Email saat ada login dari perangkat baru</div></div><label class="toggle"><input type="checkbox" checked><span class="track"></span></label></div>
          <div style="margin-top:16px;"><button class="btn btn-ghost" onclick="return false">🔑 Atur Ulang Kunci Pemulihan</button></div>
        </div>
        <div class="panel">
          <h3>Sesi Aktif</h3><p class="muted">Perangkat yang sedang login</p>
          <div class="setting-line"><div><div class="t">💻 Chrome · Windows</div><div class="d">Jakarta, ID • sesi ini</div></div><span class="tag g">Aktif</span></div>
          <div class="setting-line"><div><div class="t">📱 Android · App</div><div class="d">Bandung, ID • 2 jam lalu</div></div><button class="btn btn-ghost" style="padding:6px 12px;" onclick="return false">Keluarkan</button></div>
          <div class="setting-line"><div><div class="t">💻 Safari · macOS</div><div class="d">Surabaya, ID • kemarin</div></div><button class="btn btn-ghost" style="padding:6px 12px;" onclick="return false">Keluarkan</button></div>
          <div style="margin-top:16px;"><button class="btn" style="background:rgba(255,92,122,.15); color:var(--danger);" onclick="return false">🚪 Keluar dari Semua Perangkat</button></div>
        </div>
      </div>
      <div class="panel">
        <h3 style="margin:0 0 14px;">Riwayat Login</h3>
        <table class="tbl"><thead><tr><th>Waktu</th><th>Perangkat</th><th>Lokasi / IP</th><th>Status</th></tr></thead><tbody>
          <tr><td>15 Jul 2026, 08:41</td><td>Chrome · Windows</td><td>Jakarta · 103.xx.xx.12</td><td><span class="status ok">Berhasil</span></td></tr>
          <tr><td>14 Jul 2026, 21:07</td><td>Android · App</td><td>Bandung · 114.xx.xx.88</td><td><span class="status ok">Berhasil</span></td></tr>
          <tr><td>13 Jul 2026, 02:19</td><td>Unknown · Linux</td><td>Singapura · 45.xx.xx.7</td><td><span class="status can">Ditolak</span></td></tr>
          <tr><td>12 Jul 2026, 19:55</td><td>Safari · macOS</td><td>Surabaya · 180.xx.xx.4</td><td><span class="status ok">Berhasil</span></td></tr>
        </tbody></table>
      </div>
    </div>

    <script>
    function showTab(btn, id) {
      document.querySelectorAll('#settabs .pill-tab').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      document.querySelectorAll('.settab').forEach(function(t){ t.style.display = 'none'; });
      document.getElementById('tab-' + id).style.display = '';
    }
    </script>
    <?php dash_foot();
    break;

// admin panel (profile)
case 'profile':
    dash_head('Admin Panel'); dash_sidebar('profile', $me);
    dash_topbar('Admin Panel', 'Panel Administrator • Personalisasi Akun', $me);
    echo $profile_notice; ?>
    <div class="panels">
      <div class="panel" style="text-align:center;">
        <h3>Foto Saat Ini</h3><p class="muted">Preview avatar admin</p>
        <?php if ($profile_uploaded): ?>
          <img class="avatar-preview" src="<?= htmlspecialchars($profile_uploaded) ?>" alt="avatar" onerror="this.style.display='none'">
          <p style="margin-top:14px;"><a href="<?= htmlspecialchars($profile_uploaded) ?>">🔗 <?= htmlspecialchars($profile_uploaded) ?></a></p>
        <?php else: ?>
          <div class="avatar-preview" style="display:inline-grid; place-items:center; font-size:44px;">👤</div>
        <?php endif; ?>
      </div>
      <div class="panel">
        <h3>Upload Foto Baru</h3><p class="muted">Unggah foto profil untuk akun admin toko kamu.</p>
        <form method="POST" enctype="multipart/form-data" action="?page=profile">
          <div class="upload-box"><div class="big">🖼️</div>
            <p style="color:var(--muted); margin:0 0 14px;">Seret & lepas atau pilih foto dari perangkatmu</p>
            <input type="file" name="avatar" required style="color:var(--muted);"><br><br>
            <button type="submit" class="btn btn-primary">Unggah Foto Profil</button>
          </div>
        </form>
        <p class="muted" style="margin-top:14px; font-size:12px;">Maks 2MB. Mendukung berbagai format gambar (JPG, PNG, GIF, WEBP).</p>
      </div>
    </div>
    <?php dash_foot();
    break;

// default
default:
    header('Location: ?page=home');
    exit;

endswitch;

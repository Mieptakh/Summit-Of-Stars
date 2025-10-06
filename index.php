<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // hide default PHP errors

// =========================
// CONFIG
// =========================
require_once __DIR__ . '/config.php'; // koneksi database & constants

$defaultPage = 'home';

// PUBLIC PAGES
$publicPages = [
    'home'        => __DIR__ . '/home.php',
    'pendaftaran' => __DIR__ . '/pendaftaran.php'
];

// ADMIN PAGES
$adminPages = [
    'admin/dashboard'  => __DIR__ . '/admin/dashboard.php',
    'admin/faq'        => __DIR__ . '/admin/faq.php',
    'admin/galeri'     => __DIR__ . '/admin/galeri.php',
    'admin/kompetisi'  => __DIR__ . '/admin/kompetisi.php',
    'admin/partners'   => __DIR__ . '/admin/partners.php',
    'admin/pendaftaran'=> __DIR__ . '/admin/pendaftaran.php',
    'admin/stats'      => __DIR__ . '/admin/stats.php',
    'admin/timeline'   => __DIR__ . '/admin/timeline.php',
    'admin/users'      => __DIR__ . '/admin/users.php',
    'admin/login'      => __DIR__ . '/admin/login.php',
    'admin/logout'     => __DIR__ . '/admin/logout.php'
];

// =========================
// CLEAN ROUTE
// =========================
$route = $_GET['route'] ?? $defaultPage;
$route = trim($route, '/'); // hapus trailing slash

// =========================
// ERROR PAGE FUNCTION
// =========================
function renderErrorPage($code, $title, $message){
    http_response_code($code);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $code ?> - <?= htmlspecialchars($title) ?></title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap');
            body {margin:0;font-family:'Inter',sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;background:linear-gradient(120deg,#b58cd9,#ff9dac);color:#fff;overflow:hidden;}
            .error-box {text-align:center;padding:3rem 2rem;background:rgba(0,0,0,0.3);border-radius:20px;animation:fadeIn 1s ease;max-width:600px;}
            h1 {font-size:clamp(3rem,8vw,6rem);margin-bottom:1rem;animation:bounce 1s infinite alternate;}
            p {font-size:1.2rem;margin-bottom:2rem;}
            a {padding:0.8rem 1.6rem;background:#fff;color:#b58cd9;font-weight:700;border-radius:12px;text-decoration:none;transition:0.3s;display:inline-block;}
            a:hover {transform: translateY(-3px) scale(1.05);box-shadow:0 8px 20px rgba(0,0,0,0.2);}
            @keyframes bounce{0%{transform:translateY(0);}100%{transform:translateY(-15px);}}
            @keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1><?= $code ?></h1>
            <p><?= htmlspecialchars($message) ?></p>
            <a href="/">Kembali ke Beranda</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// =========================
// ROUTING
// =========================
try {
    // ADMIN
    if(array_key_exists($route, $adminPages)){
        if($route !== 'admin/login' && !isset($_SESSION['user'])){
            header("Location: /admin/login");
            exit;
        }
        $file = $adminPages[$route];
        if(file_exists($file)){
            require $file;
        } else {
            renderErrorPage(404,'Admin Tidak Ditemukan','Halaman admin yang diminta tidak ada.');
        }
        exit;
    }

    // PUBLIC
    if(array_key_exists($route, $publicPages)){
        $file = $publicPages[$route];
        if(file_exists($file)){
            require $file;
        } else {
            renderErrorPage(404,'Halaman Tidak Ditemukan','Halaman publik yang diminta tidak ada.');
        }
        exit;
    }

    // DEFAULT 404
    renderErrorPage(404,'Halaman Tidak Ditemukan','Halaman yang anda tuju tidak ada.');

} catch (Throwable $e){
    // ERROR 500
    renderErrorPage(500,'Kesalahan Server','Terjadi kesalahan internal: '.$e->getMessage());
}

<?php
// pendaftaran.php
// Single-file: Multi-step registration form + server handling (SQLite)
// Make sure: database/competitions.db exists and uploads/ is writable

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ---------- CONFIG -------------------------------------------------------
$dbFile = __DIR__ . '/database/competitions.db';
$uploadsDir = __DIR__ . '/uploads';

// create uploads dir if missing
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0777, true);
}

// connect DB
try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo "<h2>Gagal koneksi database</h2><p>{$e->getMessage()}</p>";
    exit;
}

// fetch competitions table (if exists)
$kompetisis = [];
try {
    $stmt = $db->query("SELECT id, judul, deskripsi, registration_link, submission_link FROM kompetisi ORDER BY id ASC");
    $kompetisis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // fallback: create local list if table missing
    $kompetisis = [
        ['id'=>1,'judul'=>'Essay Competition','deskripsi'=>'Perseorangan - menulis esai','registration_link'=>'#','submission_link'=>'#'],
        ['id'=>2,'judul'=>'Debate Competition','deskripsi'=>'Tim - debat','registration_link'=>'#','submission_link'=>null],
        ['id'=>3,'judul'=>'Innovation Case Competition','deskripsi'=>'Tim - inovasi kasus','registration_link'=>'#','submission_link'=>'#'],
        ['id'=>4,'judul'=>'Puzzle Competition','deskripsi'=>'Tim - teka-teki','registration_link'=>'#','submission_link'=>null],
    ];
}

// ---------- HELPERS ------------------------------------------------------
function safeEcho($v){ return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function allowed_ext($ext){
    $allow = ['jpg','jpeg','png','gif','webp'];
    return in_array(strtolower($ext), $allow, true);
}
function saveUploadedFiles(array $filesField, string $prefix, string $uploadsDir, array &$errors, int $maxFiles = 10, int $maxSize = 5 * 1024 * 1024){
    // $filesField expected structure from $_FILES['fieldname']
    $saved = [];
    if (!isset($filesField['name'])) return $saved;
    $count = is_array($filesField['name']) ? count($filesField['name']) : 0;
    if ($count === 0) return $saved;
    if ($count > $maxFiles) {
        $errors[] = "Maksimum $maxFiles file untuk {$prefix}. Kamu mengunggah $count file.";
        // we still process first $maxFiles
        $count = $maxFiles;
    }
    for ($i=0; $i<$count; $i++){
        if (empty($filesField['name'][$i])) continue;
        $tmp = $filesField['tmp_name'][$i];
        $orig = $filesField['name'][$i];
        $size = $filesField['size'][$i];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if ($size <= 0 || !is_uploaded_file($tmp)){
            $errors[] = "File '$orig' gagal diunggah.";
            continue;
        }
        if ($size > $maxSize){
            $errors[] = "File '$orig' melebihi batas ukuran " . ($maxSize/1024/1024) . "MB.";
            continue;
        }
        if (!allowed_ext($ext)){
            $errors[] = "Tipe file '$orig' tidak diizinkan. Hanya gambar (jpg/png/webp/gif).";
            continue;
        }
        // generate sanitized unique name
        $safe = preg_replace('/[^a-z0-9_\-\.]/i', '_', pathinfo($orig, PATHINFO_FILENAME));
        $uniq = $safe . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = rtrim($uploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $uniq;
        if (@move_uploaded_file($tmp, $dest)){
            $saved[] = $uniq;
        } else {
            $errors[] = "Gagal menyimpan file '$orig'.";
        }
    }
    return $saved;
}

// ---------- SERVER-SIDE SUBMIT -------------------------------------------
$formStatus = null;
$errors = [];
$stored_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_registration') {
    // gather POST
    $category = trim($_POST['category'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $teamname = trim($_POST['teamname'] ?? '');
    $leader   = trim($_POST['leader'] ?? '');
    $members  = array_values(array_filter(array_map('trim', $_POST['members'] ?? [])));
    $school   = trim($_POST['school'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $notes    = trim($_POST['note'] ?? '');

    // server-side validation (basics)
    if ($category === '') $errors[] = "Kategori lomba harus dipilih.";
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email ketua tidak valid.";
    if ($leader === '') $errors[] = "Nama ketua wajib diisi.";
    // For essay (perseorangan) - ensure no members and only one leader
    $isEssay = stripos($category, 'essay') !== false;

    if ($isEssay) {
        // clear teamname and members
        $teamname = '';
        $members = [];
    } else {
        // team competitions allow up to 4 members
        if (count($members) > 4) {
            $errors[] = "Anggota maksimal 4 orang.";
        }
    }

    // Handle uploads: idcard, proof (follow IG), twibbon, transfer
    $uploaded = [];
    $uploaded['idcard'] = saveUploadedFiles($_FILES['idcard'] ?? [], 'Tanda Pengenal', $uploadsDir, $errors, 10);
    $uploaded['proof'] = saveUploadedFiles($_FILES['proof'] ?? [], 'Bukti Follow', $uploadsDir, $errors, 10);
    $uploaded['twibbon'] = saveUploadedFiles($_FILES['twibbon'] ?? [], 'Twibbon', $uploadsDir, $errors, 10);
    $uploaded['transfer'] = saveUploadedFiles($_FILES['transfer'] ?? [], 'Bukti Transfer', $uploadsDir, $errors, 10);

    // if no critical errors, save to DB
    if (empty($errors)) {
        // prepare data to store into pendaftaran table (keep compatibility with initial schema)
        // use name => leader, school, email, phone, category, note => JSON with extras
        $notePayload = [
            'teamname' => $teamname,
            'members' => array_values($members),
            'uploads' => $uploaded,
            'extra_note' => $notes,
            'submitted_at' => date('c'),
        ];
        try {
            $stmt = $db->prepare("INSERT INTO pendaftaran (name, school, email, phone, category, note) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $leader,
                $school,
                $email,
                $phone,
                $category,
                json_encode($notePayload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
            ]);
            $stored_id = $db->lastInsertId();
            $formStatus = "success";
            // store saved data in session for display on confirmation page
            $_SESSION['last_registration'] = [
                'id' => $stored_id,
                'leader' => $leader,
                'email' => $email,
                'school' => $school,
                'phone' => $phone,
                'category' => $category,
                'teamname' => $teamname,
                'members' => $members,
                'uploads' => $uploaded,
                'note' => $notes,
                'created_at' => date('Y-m-d H:i:s')
            ];
        } catch (PDOException $e) {
            $errors[] = "Gagal menyimpan ke database: " . $e->getMessage();
        }
    }
}

// If user has just registered (stored_id), we will render confirmation page below using session data.
$justRegistered = ($formStatus === 'success' && isset($_SESSION['last_registration']));
$sessionData = $_SESSION['last_registration'] ?? null;

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Pendaftaran Kompetisi — Summit Of Stars</title>
  <meta name="description" content="Form pendaftaran multi-step untuk Summit Of Stars — Essay, Debate, Innovation Case, Puzzle">
  <link rel="preload" href="/fonts/HafferSQXH-Regular.woff" as="font" type="font/woff" crossorigin>
   <!-- Favicon -->
  <link rel="icon" href="images/images/summitstars.png" type="image/x-icon">
  <link rel="shortcut icon" href="images/images/summitstars.png" type="image/x-icon">
  <link rel="apple-touch-icon" sizes="180x180" href="/images/summitstars.png">
  <link rel="icon" type="image/png" sizes="32x32" href="images/summitstars.png">
  <link rel="icon" type="image/png" sizes="16x16" href="images/summitstars.png">
  <link rel="preload" href="/fonts/Telegraf-Regular.woff" as="font" type="font/woff" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="" crossorigin="anonymous" />
  <style>
    /* ===========================
       Clean modern design & animations
       =========================== */
    :root{
      --bg: #f7f6fb;
      --card: #ffffff;
      --muted: #666077;
      --accent1: #8A7CAC;
      --accent2: #FF9DAC;
      --glass-border: rgba(138,124,172,0.12);
      --radius: 14px;
      --maxw: 1060px;
      --success: #25a86f;
      --danger: #d64545;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      background: linear-gradient(180deg, #f8f7fb 0%, #f3eff9 100%);
      font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      color:#2b2435;
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
      line-height:1.45;
      padding-bottom:50px;
    }

    /* =========================================================================
   NAVBAR - FIXED + ADVANCED ANIMATION
   ========================================================================= */
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1200;
  background: linear-gradient(180deg, rgba(249,247,244,0.92), rgba(249,247,244,0.82));
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border-bottom: 1px solid rgba(138,124,172,0.18);
  transition: all 0.35s ease;
  padding: 1.2rem 0;
}

.navbar.scrolled {
  padding: 0.6rem 0;
  box-shadow: 0 8px 30px rgba(0,0,0,0.08);
  background: linear-gradient(180deg, rgba(249,247,244,0.97), rgba(249,247,244,0.97));
  transform: translateY(0);
}

.nav-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

/* =========================================================================
   LOGO
   ========================================================================= */
.logo {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-weight: 800;
  font-size: 1.25rem;
  letter-spacing: -0.5px;
  background: linear-gradient(135deg, var(--accent1), var(--accent2));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  cursor: pointer;
  transition: transform 0.3s ease;
}
.logo:hover { transform: scale(1.05) rotate(-2deg); }

.logo svg {
  width: 38px;
  height: 38px;
  filter: drop-shadow(0 6px 18px rgba(138,124,172,0.2));
  transition: transform 0.3s ease;
}
.logo:hover svg { transform: rotate(8deg) scale(1.1); }

/* =========================================================================
   NAV LINKS
   ========================================================================= */
.nav-links {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.nav-links a {
  text-decoration: none;
  color: #2D2A24;
  padding: .55rem 1rem;
  border-radius: 999px;
  font-weight: 600;
  position: relative;
  overflow: hidden;
  transition: all 0.25s ease;
}
.nav-links a::before {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, var(--accent1), var(--accent2));
  opacity: 0;
  transform: scale(0.8);
  border-radius: 999px;
  transition: all 0.35s ease;
  z-index: -1;
}
.nav-links a:hover {
  color: #fff;
  transform: translateY(-3px);
}
.nav-links a:hover::before {
  opacity: 1;
  transform: scale(1);
}

/* =========================================================================
   CTA SMALL BUTTON
   ========================================================================= */
.cta-small {
  padding: 0.55rem 1.2rem;
  border-radius: 999px;
  font-weight: 700;
  background: linear-gradient(135deg, var(--accent1), var(--accent2));
  color: #fff;
  text-decoration: none;
  box-shadow: 0 10px 28px rgba(138,124,172,0.22);
  transition: all 0.3s ease;
}
.cta-small:hover {
  transform: translateY(-2px) scale(1.05);
  box-shadow: 0 14px 34px rgba(138,124,172,0.28);
}

/* =========================================================================
   HAMBURGER MENU - PERFECT SHAPE + INTERACTIVE
   ========================================================================= */
.hamburger {
  display: none;
  width: 50px;
  height: 50px;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  cursor: pointer;
  position: relative;
  background: transparent;
  overflow: hidden;
  transition: background 0.3s ease;
}
.hamburger:hover { background: rgba(138,124,172,0.08); }

/* Ripple effect */
.hamburger::after {
  content: "";
  position: absolute;
  width: 0;
  height: 0;
  background: rgba(138,124,172,0.25);
  border-radius: 50%;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  opacity: 0;
  transition: all 0.5s ease;
}
.hamburger:active::after {
  width: 200%;
  height: 200%;
  opacity: 1;
  transition: 0s;
}

/* The bars */
.hamburger .bar {
  width: 28px;
  height: 3px;
  background: linear-gradient(90deg, var(--accent1), var(--accent2));
  border-radius: 3px;
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  transition: all 0.4s cubic-bezier(0.77, 0, 0.175, 1);
}
.hamburger .bar:nth-child(1) { top: 14px; }
.hamburger .bar:nth-child(2) { top: 50%; transform: translate(-50%, -50%); }
.hamburger .bar:nth-child(3) { bottom: 14px; }

/* Active (X shape) */
.hamburger.active .bar:nth-child(1) {
  top: 50%;
  transform: translate(-50%, -50%) rotate(45deg);
}
.hamburger.active .bar:nth-child(2) {
  opacity: 0;
  transform: translate(-50%, -50%) scaleX(0);
}
.hamburger.active .bar:nth-child(3) {
  bottom: auto;
  top: 50%;
  transform: translate(-50%, -50%) rotate(-45deg);
}

/* =========================================================================
   MOBILE OVERLAY MENU - CIRCULAR REVEAL ANIMATION
   ========================================================================= */
.mobile-overlay {
  position: fixed;
  inset: 0;
  background: rgba(28, 27, 25, 0.92);
  backdrop-filter: blur(22px);
  -webkit-backdrop-filter: blur(22px);
  z-index: 1100;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  opacity: 0;
  clip-path: circle(0% at 50% 50%);
  transition: clip-path 0.8s cubic-bezier(0.77, 0, 0.175, 1), opacity 0.4s ease;
}
.mobile-overlay.active {
  opacity: 1;
  pointer-events: auto;
  clip-path: circle(150% at 50% 50%);
}

/* =========================================================================
   MOBILE MENU WRAPPER - FADE & SLIDE IN
   ========================================================================= */
.mobile-menu {
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 1.8rem;
  transform: translateY(40px);
  opacity: 0;
  animation: menuWrapperIn 0.7s forwards cubic-bezier(0.22, 1, 0.36, 1);
  animation-delay: 0.25s;
}
@keyframes menuWrapperIn {
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

/* =========================================================================
   MOBILE MENU ITEM - BOUNCE STAGGER
   ========================================================================= */
.mobile-menu a {
  display: block;
  color: #fff;
  font-weight: 700;
  font-size: 2rem;
  text-decoration: none;
  opacity: 0;
  transform: translateY(60px) scale(0.9);
  animation: menuItemIn 0.85s forwards cubic-bezier(0.68, -0.55, 0.27, 1.55);
}
.mobile-menu a:nth-child(1) { animation-delay: 0.35s; }
.mobile-menu a:nth-child(2) { animation-delay: 0.5s; }
.mobile-menu a:nth-child(3) { animation-delay: 0.65s; }
.mobile-menu a:nth-child(4) { animation-delay: 0.8s; }
.mobile-menu a:nth-child(5) { animation-delay: 0.95s; }

@keyframes menuItemIn {
  0% {
    opacity: 0;
    transform: translateY(60px) scale(0.9);
    filter: blur(6px);
  }
  70% {
    opacity: 1;
    transform: translateY(-8px) scale(1.05);
    filter: blur(0);
  }
  100% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.mobile-menu a:hover {
  color: #fff;
  text-shadow: 0 4px 22px rgba(255,255,255,0.35);
  transform: scale(1.1);
  transition: all 0.35s ease;
}

/* =========================================================================
   RESPONSIVE BEHAVIOR
   ========================================================================= */
@media (max-width: 960px) {
  .nav-links { display: none; }
  .hamburger { display: flex; }
}

    .wrap{max-width:var(--maxw); margin:40px auto; padding:28px; position:relative;}
    .card{
      background:var(--card);
      border-radius:20px;
      padding:28px;
      margin-top:5rem;
      box-shadow:0 10px 30px rgba(31,20,50,0.06);
      border:1px solid var(--glass-border);
      overflow:hidden;
    }

    /* header */
    .topbar{
      display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:22px;
    }
    .brand{
      display:flex; align-items:center; gap:14px;
    }
    .brand img{height:48px; width:auto; border-radius:8px; box-shadow:0 6px 20px rgba(138,124,172,0.12)}
    .brand h1{font-size:1.25rem; margin:0; font-weight:800; background:linear-gradient(90deg,var(--accent1),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent}
    .subtitle{color:var(--muted); font-size:0.95rem}

    /* progress */
    .progress-wrap{margin:18px 0 8px}
    .progress{height:10px; background:linear-gradient(90deg,#ede9f6,#fbf0f3); border-radius:999px; overflow:hidden; border:1px solid rgba(0,0,0,0.03)}
    .progress-bar{height:100%; width:0%; background:linear-gradient(90deg,var(--accent1),var(--accent2)); transition:width .6s cubic-bezier(.2,.9,.2,1)}
    .steps-row{display:flex; gap:12px; margin-top:12px; justify-content:center; align-items:center}
    .step-dot{width:12px;height:12px;border-radius:50%; background:#eee; display:inline-block; box-shadow:0 2px 6px rgba(0,0,0,0.04)}
    .step-dot.active{background:linear-gradient(90deg,var(--accent1),var(--accent2)); transform:scale(1.12); box-shadow:0 8px 24px rgba(181,140,217,0.18)}

    /* category options */
    .category-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 16px;
      margin-top: 8px;
    }

    .cat-card {
      padding: 18px;
      border-radius: 12px;
      background: linear-gradient(180deg, #fff, #fbfaff);
      border: 1px solid var(--glass-border);
      cursor: pointer;
      transition: transform 0.28s, box-shadow 0.28s, background 0.28s, border-color 0.28s;
    }

    /* hover effect */
    .cat-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 18px 40px rgba(138, 124, 172, 0.08);
    }

    /* selected state */
    .cat-card.selected {
      background: linear-gradient(180deg, #f0f4ff, #dfe7ff);
      border: 2px solid #bfb7f8; /* highlight lembut, nyambung dengan gradient */
      transform: translateY(-6px) scale(1.03);
      box-shadow: 0 20px 50px rgba(123, 95, 255, 0.15);
    }

    /* text styling */
    .cat-card h3 {
      margin: 0 0 8px;
      font-size: 1.05rem;
    }

    .cat-card p {
      margin: 0;
      color: var(--muted);
      font-size: .92rem;
    }


    /* ===========================
      Form Styles - Elegant & Custom
    =========================== */

    /* Form grid: 2 columns on wide screens */
    form .grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }

    /* Flex row for grouped fields */
    .form-row {
      display: flex;
      gap: 12px;
    }

    /* Single field styling */
    .field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    /* Labels */
    .field label {
      font-weight: 700;
      color: #3b3144; /* elegant dark */
      font-size: 0.9rem;
    }

    /* Inputs, select, url */
    .field input[type="text"],
    .field input[type="email"],
    .field input[type="tel"],
    .field input[type="url"],
    .field select {
      padding: 12px 14px;
      border-radius: 10px;
      border: 1px solid rgba(0, 0, 0, 0.06);
      font-size: 0.98rem;
      background: #fff;
      transition: box-shadow 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
    }

    /* Focus effect */
    .field input:focus,
    .field select:focus {
      outline: none;
      border-color: var(--accent1, #7b5fff); /* accent color default */
      box-shadow: 0 8px 20px rgba(138, 124, 172, 0.08);
      transform: scale(1.01); /* subtle zoom for elegance */
    }

    /* ===========================
      Custom File Input
    =========================== */

    /* File row container */
    .file-row {
      display: grid;
      grid-template-columns: 1fr;
      gap: 10px;
    }

    /* File box */
    .file-box {
      padding: 12px 14px;
      border-radius: 10px;
      border: 1px dashed rgba(138, 124, 172, 0.12);
      display: flex;
      align-items: center;
      gap: 12px;
      background: linear-gradient(180deg, #fff, #fbfbff);
      cursor: pointer;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    }

    /* Hover for file box */
    .file-box:hover {
      border-color: rgba(138, 124, 172, 0.3);
      box-shadow: 0 6px 18px rgba(138, 124, 172, 0.08);
      transform: translateY(-2px);
    }

    /* Actual file input (hidden style) */
    .file-box input[type="file"] {
      border: 0;
      background: transparent;
      flex: 1;
      cursor: pointer;
    }

    /* File preview container */
    .file-preview {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 10px;
    }

    /* Thumbnail box */
    .thumb {
      width: 80px;
      height: 60px;
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid rgba(0, 0, 0, 0.06);
      display: flex;
      align-items: center;
      justify-content: center;
      background: #fff;
      box-shadow: 0 4px 12px rgba(138, 124, 172, 0.06);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    /* Hover effect on thumbnail */
    .thumb:hover {
      transform: translateY(-2px) scale(1.03);
      box-shadow: 0 8px 22px rgba(138, 124, 172, 0.12);
    }

    /* Image inside thumbnail */
    .thumb img {
      max-width: 100%;
      max-height: 100%;
      display: block;
      object-fit: cover;
    }

    /* CTA row */
    .actions{display:flex; gap:12px; justify-content:space-between; align-items:center; margin-top:14px}
    .actions .left{color:var(--muted); font-size:.95rem}
    .btn{padding:12px 18px; border-radius:999px; border:0; cursor:pointer; font-weight:800; color:#fff; background:linear-gradient(90deg,var(--accent1),var(--accent2)); box-shadow:0 8px 28px rgba(138,124,172,0.14); transition:transform .18s}
    .btn.secondary{background:#fff; color:#443650; border:1px solid rgba(0,0,0,0.06); box-shadow:none}
    .btn:active{transform:translateY(2px)}

    /* responsive - single column for small screens */
    @media(max-width:880px){
      .category-grid{grid-template-columns:1fr}
      form .grid{grid-template-columns:1fr}
      .brand h1{font-size:1rem}
    }

    /* notification toast */
    .toast{
      position:fixed; right:20px; bottom:24px; z-index:9999; min-width:240px; max-width:360px;
      border-radius:12px; padding:12px 14px; color:#fff; display:flex; gap:12px; align-items:center; box-shadow:0 12px 30px rgba(0,0,0,0.12);
      transform:translateY(20px); opacity:0; pointer-events:none; transition:transform .34s, opacity .34s;
      background:linear-gradient(90deg,var(--accent1),var(--accent2));
    }
    .toast.show{transform:translateY(0); opacity:1; pointer-events:auto}
    .toast .t-body{font-weight:700}
    .toast .t-close{margin-left:auto; opacity:.9; cursor:pointer}

    /* confirmation layout */
    .confirm{
      display:grid; grid-template-columns:1fr 320px; gap:18px; align-items:start;
    }
    @media(max-width:980px){ .confirm{grid-template-columns:1fr} }

    .summary{padding:16px;border-radius:12px;background:linear-gradient(180deg,#fff,#fbfbff); border:1px solid rgba(0,0,0,0.04)}
    .summary h3{margin:0 0 8px}
    .summary p{margin:6px 0; color:var(--muted)}
    .bank{padding:12px;border-radius:12px;background:linear-gradient(90deg,#fbf5f9,#f9fbff); border:1px solid rgba(138,124,172,0.06)}
    .small{font-size:.9rem;color:var(--muted)}
    .success-badge{display:inline-block;padding:6px 10px;border-radius:999px;background:linear-gradient(90deg,#27c07a,#1f8e58); color:#fff; font-weight:700}
    .danger-badge{display:inline-block;padding:6px 10px;border-radius:999px;background:linear-gradient(90deg,#ff8a8a,#d64545); color:#fff; font-weight:700}
    /* =========================================================================
   FOOTER - Premium & Interactive
   ========================================================================= */
footer {
  position: relative;
  padding: 6rem 1.5rem 3rem;
  background: linear-gradient(135deg, #1e1b29, #292038);
  color: rgba(255,255,255,0.85);
  overflow: hidden;
  font-family: 'Inter', sans-serif;
}

/* Wave top separator - animated & smooth */
footer::before {
  content: "";
  position: absolute;
  top: -1px; left: 0; right: 0;
  height: 80px;
  background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 150"><path fill="%23ffffff" fill-opacity="0.03" d="M0,80 C480,160 960,0 1440,80 L1440,0 L0,0 Z"></path></svg>') no-repeat center top;
  background-size: cover;
  z-index: 1;
  animation: waveFloat 6s ease-in-out infinite alternate;
}
@keyframes waveFloat {
  0% { transform: translateY(0); }
  50% { transform: translateY(6px); }
  100% { transform: translateY(0); }
}

/* Footer Inner Grid */
footer .foot-inner {
  position: relative;
  z-index: 2;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px,1fr));
  gap: 3rem;
  max-width: 1200px;
  margin: 0 auto;
  animation: fadeUp 1s ease forwards;
}

/* Titles - gradient & subtle shadow */
footer h3, footer h4 {
  font-family: 'Telegraf', sans-serif;
  font-weight: 700;
  margin-bottom: 1rem;
  font-size: 1.3rem;
  background: linear-gradient(135deg,#ffb8c3,#b5a5d1);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  text-shadow: 0 2px 6px rgba(0,0,0,0.25);
}

/* Text */
footer p, footer .small {
  font-size: 1rem;
  line-height: 1.75;
  color: rgba(255,255,255,0.85);
}

/* Links */
footer a {
  color: rgba(255,255,255,0.8);
  text-decoration: none;
  position: relative;
  transition: color .3s ease, transform .3s ease;
}
footer a::after {
  content: "";
  position: absolute;
  left: 0; bottom: -2px;
  width: 0%; height: 2px;
  background: linear-gradient(90deg,#ff9dac,#b5a5d1);
  transition: width .35s ease;
  border-radius: 2px;
}
footer a:hover {
  color: #fff;
  transform: translateY(-2px);
}
footer a:hover::after {
  width: 100%;
}

/* Quick links list */
footer ul {
  list-style: none;
  margin: 0; padding: 0;
}
footer ul li { margin-bottom: .6rem; }

/* Social icons - animated gradient & hover */
footer .socials {
  display: flex;
  gap: 0.8rem;
  margin-top: 1.2rem;
}
footer .socials a {
  width: 48px; height: 48px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 50%;
  border: 1px solid rgba(255,255,255,0.15);
  font-size: 1.2rem;
  transition: all .35s ease;
  color: rgba(255,255,255,0.85);
  background: linear-gradient(135deg, rgba(255,255,255,0.02), rgba(0,0,0,0.05));
}
footer .socials a:hover {
  background: linear-gradient(135deg,#ff9dac,#b5a5d1);
  color: #fff;
  transform: translateY(-4px) scale(1.12) rotate(-2deg);
  box-shadow: 0 12px 36px rgba(181,165,209,0.45);
}

/* Logos Section (YRI + MH Teams) - animated */
.foot-logos {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 4rem;
  margin: 2.5rem 0 1.5rem;
  opacity: 0;
  transform: translateY(30px);
  animation: logosFadeIn 1.2s forwards ease-out 0.6s;
}
.foot-logos img {
  height: 70px;
  object-fit: contain;
  transition: transform 0.35s ease, filter 0.35s ease, box-shadow 0.35s ease;
  cursor: pointer;
}
.foot-logos img:hover {
  transform: scale(1.18) rotate(-2deg);
  filter: brightness(1.25);
  box-shadow: 0 8px 28px rgba(0,0,0,0.18);
}

@keyframes logosFadeIn {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Footer bottom copyright */
.footer-bottom {
  text-align: center;
  font-size: .9rem;
  color: rgba(255,255,255,0.65);
  line-height: 1.6;
  border-top: 1px solid rgba(255,255,255,0.08);
  padding-top: 1.6rem;
}
.footer-bottom a {
  font-weight: 600;
  background: linear-gradient(135deg,#ff9dac,#b5a5d1);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  transition: opacity .3s ease;
}
.footer-bottom a:hover {
  opacity: 0.9;
}

/* Animations */
@keyframes fadeUp {
  0% { opacity: 0; transform: translateY(30px);}
  100% { opacity: 1; transform: translateY(0);}
}

/* Responsive */
@media (max-width: 768px) {
  footer .foot-inner {
    grid-template-columns: 1fr;
    gap: 2rem;
    text-align: center;
  }
  .foot-logos {
    flex-direction: column;
    gap: 1.5rem;
  }
  footer .socials {
    justify-content: center;
  }
}
  </style>
</head>
<body>
  <!-- ===========================
       NAVBAR
       =========================== -->
  <header class="navbar" id="navbar" role="navigation" aria-label="Main Navigation">
    <div class="container nav-inner">
      <a href="#home" class="logo" aria-label="YOUTH RANGER INDONESIA REGIONAL SUMATERA SELATAN">
        <img src="images/summitstars.png" 
            alt="Logo YOUTH RANGER INDONESIA REGIONAL SUMATERA SELATAN" 
            style="height: 50px; width: auto; margin-right: 0.6rem; vertical-align: middle;">
        Summit Of Stars
      </a>


      <nav class="nav-links" aria-label="Sekunder">
        <a href="/home">Beranda</a>
        <a href="/home#kompetisi">Kompetisi</a>
        <a href="/home#galeri">Galeri</a>
        <a href="/pendaftaran" class="cta-small">Daftar</a>
      </nav>

      <button class="hamburger" id="hambtn" aria-expanded="false" aria-controls="mobile-menu" title="Buka menu">
        <span class="bar" aria-hidden="true"></span>
        <span class="bar" aria-hidden="true"></span>
        <span class="bar" aria-hidden="true"></span>
      </button>
    </div>
  </header>

  <!-- mobile menu overlay -->
  <div class="mobile-overlay" id="mobile-menu" aria-hidden="true">
    <div class="mobile-menu" role="menu" aria-label="Mobile Navigation">
      <a href="/home">Beranda</a>
      <a href="/home#kompetisi">Kompetisi</a>
      <a href="/home#galeri">Galeri</a>
      <a href="/home#tentang">Tentang</a>
      <a href="/pendaftaran" class="cta-small">Daftar</a>
    </div>
  </div>

  <div class="wrap">
    <div class="card">
      <div class="topbar">
        <div class="brand">
          <img src="images/summitstars.png" alt="logo">
          <div>
            <h1>Summit Of Stars — Pendaftaran</h1>
            <div class="subtitle">Pilih cabang lomba → isi data → konfirmasi. Mudah, cepat, & aman.</div>
          </div>
        </div>
        <div class="subtitle">Tanggal puncak: <strong>18 Januari 2025</strong></div>
      </div>

      <?php if (!empty($errors)): ?>
        <div style="margin-bottom:12px;padding:10px;border-radius:10px;background:linear-gradient(90deg,#fff5f5,#ffeef0);border:1px solid rgba(214,69,69,0.08); color:var(--danger); font-weight:700">
          <i class="fa-solid fa-triangle-exclamation"></i>
          Terjadi <strong><?= count($errors) ?></strong> kesalahan:
          <ul style="margin:8px 0 0 18px;">
            <?php foreach($errors as $er): ?>
              <li><?= safeEcho($er) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($justRegistered && $sessionData): ?>
        <!-- ================= CONFIRMATION (SERVER RENDERED) ================== -->
        <div class="progress-wrap">
          <div class="progress"><div class="progress-bar" style="width:100%"></div></div>
          <div class="steps-row" aria-hidden="true">
            <span class="step-dot"></span>
            <span class="step-dot"></span>
            <span class="step-dot active"></span>
          </div>
        </div>

        <div style="margin-top:14px" class="confirm">
          <div class="summary">
            <h3>Konfirmasi Pendaftaran</h3>
            <p class="small">Terima kasih — pendaftaran Anda telah berhasil tersimpan.</p>

            <p><strong>ID Pendaftaran:</strong> <?= safeEcho($sessionData['id']) ?></p>
            <p><strong>Cabang Lomba:</strong> <?= safeEcho($sessionData['category']) ?></p>
            <p><strong>Nama Ketua:</strong> <?= safeEcho($sessionData['leader']) ?></p>
            <p><strong>Email Ketua:</strong> <?= safeEcho($sessionData['email']) ?></p>
            <?php if (!empty($sessionData['teamname'])): ?>
              <p><strong>Nama Tim:</strong> <?= safeEcho($sessionData['teamname']) ?></p>
            <?php endif; ?>
            <?php if (!empty($sessionData['members'])): ?>
              <p><strong>Anggota:</strong> <?= safeEcho(implode(', ', $sessionData['members'])) ?></p>
            <?php endif; ?>
            <p><strong>Asal/Instansi:</strong> <?= safeEcho($sessionData['school']) ?></p>

            <hr style="border:none;border-top:1px solid rgba(0,0,0,0.04); margin:12px 0">

            <h4 style="margin:8px 0">Unggahan</h4>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
              <?php
                $allUploads = $sessionData['uploads'] ?? [];
                foreach (['idcard','proof','twibbon','transfer'] as $f){
                  if (!empty($allUploads[$f])){
                    foreach ($allUploads[$f] as $fn){
                      $url = 'uploads/' . rawurlencode($fn);
                      echo "<div style='width:72px;height:56px;border-radius:8px;overflow:hidden;border:1px solid rgba(0,0,0,0.05);background:#fff;display:flex;align-items:center;justify-content:center'><img src=\"{$url}\" style=\"max-width:100%;max-height:100%;display:block\"></div>";
                    }
                  }
                }
              ?>
            </div>

            <hr style="border:none;border-top:1px solid rgba(0,0,0,0.04); margin:12px 0">

            <p class="small">Kami telah menyimpan data. Tim panitia akan menghubungi via email yang Anda daftarkan untuk verifikasi dan informasi lanjut.</p>
          </div>

          <aside>
            <div class="bank">
              <h4 style="margin:0 0 8px">Instruksi Pembayaran</h4>
              <p class="small">Silakan transfer biaya pendaftaran ke salah satu rekening berikut:</p>
              <p style="margin:8px 0"><strong>Bank Mandiri</strong><br>1130018161327<br><em>a.n. DELLA APRILIA</em></p>
              <p style="margin:8px 0"><strong>DANA</strong><br>082280943039<br><em>a.n. DELLA APRILIA</em></p>
              <p class="small" style="margin-top:10px">Unggah bukti transfer pada saat pendaftaran (opsional), atau kirimkan via email konfirmasi setelah transfer.</p>
            </div>

            <div style="margin-top:14px; padding:12px; border-radius:12px; background:linear-gradient(90deg,#fbfbff,#fffaf6); border:1px solid rgba(0,0,0,0.04)">
              <p style="margin:0 0 10px;"><strong>Butuh bantuan?</strong></p>
              <p class="small" style="margin:0">
              Hubungi kami: 
              <a href="mailto:info@sumseyouthcomp.com" style="text-decoration:none; color:inherit;">info@sumseyouthcomp.com</a> 
              atau 
              <a href="tel:+62711123456" style="text-decoration:none; color:inherit;">+62 711 123 456</a>
            </p>
            <p style="margin-top:8px">
              <a href="https://mhteams.my.id" target="_blank" class="btn" style="display:inline-block; text-decoration:none;">Butuh Website?</a>
            </p>

            </div>
          </aside>
        </div>

        <div style="margin-top:18px; display:flex; justify-content:center; gap:12px;">
          <a href="/pendaftaran" class="btn secondary" 
            style="text-decoration:none;">
            Daftar Lagi
          </a>
          <a href="/home" class="btn" 
            style="text-decoration:none;">
            Kembali ke Beranda
          </a>
        </div>


      <?php else: ?>
        <!-- ================= MULTI-STEP FORM (client side) ================== -->
        <div id="app">
          <div class="progress-wrap">
            <div class="progress"><div class="progress-bar" id="progressBar" style="width:33%"></div></div>
            <div class="steps-row" aria-hidden="true">
              <span class="step-dot active" id="dot1"></span>
              <span class="step-dot" id="dot2"></span>
              <span class="step-dot" id="dot3"></span>
            </div>
          </div>

          <form id="regForm" class="form" enctype="multipart/form-data" method="POST" action="pendaftaran.php" novalidate>
            <input type="hidden" name="action" value="submit_registration">

            <!-- STEP 1 -->
            <div class="step" data-step="1">
              <p class="small">Langkah 1 — Pilih cabang lomba. Essay = <strong>Perseorangan</strong>. Lainnya = <strong>Tim (max 4 anggota)</strong>.</p>
              <div class="category-grid" role="list">
                <?php foreach($kompetisis as $k): ?>
                  <label class="cat-card" role="listitem">
                    <input type="radio" name="category" value="<?= safeEcho($k['judul']) ?>" style="display:none">
                    <h3><?= safeEcho($k['judul']) ?></h3>
                    <p><?= safeEcho($k['deskripsi']) ?></p>
                  </label>
                <?php endforeach; ?>
              </div>
              <div style="display:flex; justify-content:space-between; align-items:center; margin-top:18px">
                <div class="left small">Pilih satu kategori untuk melanjutkan.</div>
                <div>
                  <button type="button" class="btn secondary" id="resetBtn">Reset</button>
                  <button type="button" class="btn" id="toStep2">Lanjut →</button>
                </div>
              </div>
            </div>

            <!-- STEP 2 -->
            <div class="step" data-step="2" style="display:none">
              <p class="small">Langkah 2 — Isi data peserta. Form disusun dua kolom pada layar besar untuk tampilan rapi.</p>

              <div class="grid">
                <div class="field"><label>Email Ketua <span style="color:var(--danger)">*</span></label><input type="email" name="email" required placeholder="email@domain.com"></div>
                <div class="field"><label>Nomor HP Ketua</label><input type="tel" name="phone" placeholder="0812xxxxxxx"></div>

                <div class="field" id="teamNameField" style="display:none"><label>Nama Tim (untuk Puzzle)</label><input type="text" name="teamname" placeholder="Nama tim"></div>
                <div class="field"><label>Nama Ketua <span style="color:var(--danger)">*</span></label><input type="text" name="leader" required placeholder="Nama Ketua"></div>

                <div class="field" id="membersBlock" style="display:none">
                  <label>Anggota (maks 4)</label>
                  <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:8px">
                    <input type="text" name="members[]" placeholder="Anggota 1">
                    <input type="text" name="members[]" placeholder="Anggota 2">
                    <input type="text" name="members[]" placeholder="Anggota 3">
                    <input type="text" name="members[]" placeholder="Anggota 4">
                  </div>
                </div>

                <div class="field"><label>Instansi / Sekolah</label><input type="text" name="school" placeholder="Nama sekolah / instansi"></div>
                <div class="field"><label>Keterangan tambahan (opsional)</label><input type="text" name="note" placeholder="Catatan / kebutuhan khusus"></div>
              </div>

              <hr style="margin:12px 0;border:none;border-top:1px solid rgba(0,0,0,0.04)">

              <div class="file-row">
                <div>
                  <label class="small">Scan Tanda Pengenal (jpg/png/webp) — Maks 10 file</label>
                  <div class="file-box"><i class="fa-regular fa-id-card" style="color:var(--muted)"></i><input type="file" name="idcard[]" id="idcard" accept="image/*" multiple></div>
                  <div id="idPreview" class="file-preview"></div>
                </div>

                <div>
                  <label class="small">Bukti Follow (tangkapan layar) — youthranger.id, youthranger.sumsel, summitofstarsyri — Maks 10 file</label>
                  <div class="file-box"><i class="fa-brands fa-instagram" style="color:var(--muted)"></i><input type="file" name="proof[]" id="proof" accept="image/*" multiple></div>
                  <div id="proofPreview" class="file-preview"></div>
                </div>

                <div>
                  <label class="small">Bukti Upload Twibbon — Maks 10 file</label>
                  <div class="file-box"><i class="fa-regular fa-image" style="color:var(--muted)"></i><input type="file" name="twibbon[]" id="twibbon" accept="image/*" multiple></div>
                  <div id="twibbonPreview" class="file-preview"></div>
                </div>

                <div>
                  <label class="small">Bukti Transfer (jpg/png/webp) — Maks 10 file</label>
                  <div class="file-box"><i class="fa-solid fa-money-bill-transfer" style="color:var(--muted)"></i><input type="file" name="transfer[]" id="transfer" accept="image/*" multiple></div>
                  <div id="transferPreview" class="file-preview"></div>
                </div>
              </div>

              <div class="actions" style="margin-top:8px">
                <div class="left small">Semua file maksimal 5MB per file; gambar disarankan JPG/PNG/WEBP.</div>
                <div>
                  <button type="button" class="btn secondary" id="backTo1">← Kembali</button>
                  <button type="button" class="btn" id="toStep3">Lihat Ringkasan →</button>
                </div>
              </div>
            </div>

            <!-- STEP 3 (client preview) -->
            <div class="step" data-step="3" style="display:none">
              <p class="small">Langkah 3 — Konfirmasi data. Pastikan semua benar sebelum klik "Kirim Pendaftaran".</p>

              <div class="confirm" style="margin-top:12px">
                <div class="summary" id="previewSummary">
                  <!-- filled by JS -->
                  <h3>Ringkasan Pendaftaran</h3>
                  <p class="small">Periksa kembali semua data dan unggahan.</p>
                  <div id="summaryBody"></div>
                </div>
                <aside>
                  <div class="bank">
                    <h4 style="margin:0 0 8px">Cara Pembayaran</h4>
                    <p class="small">Transfer ke salah satu rekening:</p>
                    <p style="margin:8px 0"><strong>Bank Mandiri</strong><br>1130018161327<br><em>a.n. DELLA APRILIA</em></p>
                    <p style="margin:8px 0"><strong>DANA</strong><br>082280943039<br><em>a.n. DELLA APRILIA</em></p>
                    <p class="small" style="margin-top:10px">Unggah bukti transfer di form sebelumnya, atau kirim melalui email jika belum.</p>
                  </div>

                  <div style="margin-top:12px; padding:12px; border-radius:12px; background:linear-gradient(90deg,#fbfbff,#fffaf6); border:1px solid rgba(0,0,0,0.04)">
                    <p style="margin:0"><strong>Perlu bantuan?</strong></p>
                    <p class="small" style="margin:6px 0 0">Kontak: <a href="mailto:info@sumseyouthcomp.com">info@sumseyouthcomp.com</a></p>
                  </div>
                </aside>
              </div>

              <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:14px">
                <button type="button" class="btn secondary" id="backTo2">← Kembali</button>
                <button type="submit" class="btn" id="submitBtn">Kirim Pendaftaran</button>
              </div>
            </div>
          </form>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- toast -->
  <div id="toast" role="status" class="toast" aria-live="polite" style="display:none">
    <div class="t-body">Pesan</div>
    <div class="t-close" onclick="hideToast()" aria-hidden="true" style="margin-left:10px"><i class="fa-solid fa-xmark"></i></div>
  </div>

  <footer>
  <!-- Info & About -->
  <div class="foot-inner">
    <div class="foot-about">
      <h3>Summit Of Stars</h3>
      <p>Platform terdepan untuk mengembangkan potensi dan prestasi generasi muda Sumatera Selatan. Kami berkomitmen mendukung kreativitas, inovasi, dan semangat kompetitif para peserta melalui lomba-lomba yang inspiratif.</p>
      <p>
        <strong>Email:</strong> <a href="mailto:info@sumseyouthcomp.com">info@sumseyouthcomp.com</a><br>
        <strong>Telepon:</strong> <a href="tel:+62711123456">+62 711 123 456</a>
      </p>
    </div>

    <!-- Quick Links -->
    <div class="foot-links">
      <h4>Navigasi Cepat</h4>
      <ul>
        <li><a href="#home">Beranda</a></li>
        <li><a href="#kompetisi">Kompetisi</a></li>
        <li><a href="#pendaftaran">Pendaftaran</a></li>
        <li><a href="#tentang">Tentang Kami</a></li>
        <li><a href="#faq">FAQ</a></li>
        <li><a href="#galeri">Galeri</a></li>
      </ul>
    </div>

    <!-- Social Media -->
    <div class="foot-social">
      <h4>Ikuti Kami di Media Sosial</h4>
      <p>Temukan berita terbaru, tips lomba, dan inspirasi generasi muda:</p>
      <div class="socials">
        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
        <a href="https://www.instagram.com/summitofstarsyri?utm_source=ig_web_button_share_sheet&igsh=aG01bjlpMXlrM3R4" aria-label="Info Lomba"><i class="fab fa-info"></i></a>
        <a href="https://youtube.com/@youthrangerindonesia2649?si=l-aM1P3aeIugbjDW" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
      </div>
    </div>
  </div>

  <!-- Logos Section -->
  <div class="foot-logos">
    <img src="images/summitstars.png" alt="YRI Sumsel Logo">
    <img src="images/20250320_190104[1].png" alt="MH Teams Logo">
    <img src="images/YOUTH RANGER INDONESIA REGIONAL SUMATERA SELATAN (1).png" alt="New Logo">
  </div>

  <!-- Footer Bottom: Credit & CTA -->
  <div class="footer-bottom">
    <p>&copy; 2025 Summit Of Stars. Semua hak cipta dilindungi.</p>
    <p>
      Dibangun dengan <span>❤️</span> dan dedikasi oleh <a href="https://mhteams.my.id" target="_blank">MH Teams</a>. 
      MH Teams tidak hanya membuat website, tapi juga membantu Anda mewujudkan platform digital profesional, elegan, dan interaktif. 
      <strong>Butuh website seperti ini untuk organisasi, lomba, atau bisnis Anda?</strong> 
      <a href="https://mhteams.my.id" target="_blank">Klik di sini untuk konsultasi & penawaran</a>.
    </p>
    <p>
      Bergabunglah dengan komunitas kami dan rasakan pengalaman digital yang inovatif, cepat, dan ramah pengguna. Semua konten, galeri, dan informasi lomba tersedia untuk memudahkan pengunjung dan peserta.
    </p>
  </div>
</footer>

<script>
  // ======== Utilities ========
  const $ = sel => document.querySelector(sel);
  const $$ = sel => Array.from(document.querySelectorAll(sel));
  function showToast(message, timeout=3800){
    const t = document.getElementById('toast');
    t.querySelector('.t-body').textContent = message;
    t.style.display = 'flex';
    setTimeout(()=> t.classList.add('show'), 50);
    if (timeout>0){
      clearTimeout(t._timer);
      t._timer = setTimeout(()=>{ hideToast(); }, timeout);
    }
  }
  function hideToast(){
    const t = document.getElementById('toast');
    t.classList.remove('show');
    setTimeout(()=> t.style.display='none', 350);
  }

  // ======== Multi-step logic ========
  (function(){
    const steps = $$('.step');
    let current = 1;
    const total = steps.length;
    const progressBar = $('#progressBar');
    const dot1 = $('#dot1'), dot2=$('#dot2'), dot3=$('#dot3');

    function showStep(n){
      steps.forEach(s=> s.style.display='none');
      const el = document.querySelector('.step[data-step="'+n+'"]');
      if (el) el.style.display='block';
      // progress
      const pct = Math.round((n/3)*100);
      progressBar.style.width = pct + '%';
      dot1.classList.toggle('active', n>=1);
      dot2.classList.toggle('active', n>=2);
      dot3.classList.toggle('active', n>=3);
      current = n;
      window.scrollTo({top:0, behavior:'smooth'});
    }

    // pick category card
    document.querySelectorAll('.cat-card').forEach(card=>{
      card.addEventListener('click', ()=>{
        // set radio inside
        const r = card.querySelector('input[type="radio"]');
        if (r){ r.checked = true; }
        // visual
        document.querySelectorAll('.cat-card').forEach(c=> c.style.boxShadow='none');
        card.style.boxShadow = '0 18px 40px rgba(138,124,172,0.12)';
      });
    });

    $('#toStep2').addEventListener('click', ()=>{
      const chosen = document.querySelector('input[name="category"]:checked');
      if (!chosen){
        showToast('Pilih kategori lomba terlebih dahulu');
        return;
      }
      // reveal step 2 and adjust fields
      const cat = chosen.value.toLowerCase();
      const isEssay = cat.includes('essay');
      document.getElementById('teamNameField').style.display = isEssay ? 'none' : (cat.includes('puzzle')? 'block':'none');
      document.getElementById('membersBlock').style.display = isEssay ? 'none' : 'block';
      // mark radio values to a hidden field? we'll keep radios as is and let form submit
      showStep(2);
    });

    $('#backTo1').addEventListener('click', ()=> showStep(1));
    $('#resetBtn').addEventListener('click', ()=> {
      $$('input[name="category"]').forEach(r=> r.checked=false);
      document.querySelectorAll('.cat-card').forEach(c=> c.style.boxShadow='');
    });

    // Step2 -> Step3
    $('#toStep3').addEventListener('click', ()=>{
      // validate required fields on step2
      const email = document.querySelector('input[name="email"]').value.trim();
      const leader = document.querySelector('input[name="leader"]').value.trim();
      const chosen = document.querySelector('input[name="category"]:checked');
      if (!chosen) { showToast('Kategori belum dipilih'); showStep(1); return; }
      if (!email || !/^\S+@\S+\.\S+$/.test(email)) { showToast('Masukkan email ketua yang valid'); return; }
      if (!leader) { showToast('Masukkan nama ketua'); return; }

      // populate summary
      const summary = $('#summaryBody');
      const form = document.getElementById('regForm');
      const fd = new FormData(form);
      // we need to read some values
      function getVal(name){ return (fd.getAll(name).join(',')||'').toString() }
      let html = '';
      html += `<p><strong>Cabang:</strong> ${safe(getVal('category'))}</p>`;
      html += `<p><strong>Nama Ketua:</strong> ${safe(getVal('leader'))}</p>`;
      html += `<p><strong>Email Ketua:</strong> ${safe(getVal('email'))}</p>`;
      const teamname = safe(getVal('teamname'));
      if (teamname) html += `<p><strong>Nama Tim:</strong> ${teamname}</p>`;
      const members = fd.getAll('members[]').filter(v=>v && v.trim()).map(v=>safe(v));
      if (members.length) html += `<p><strong>Anggota:</strong> ${members.join(', ')}</p>`;
      html += `<p><strong>Instansi:</strong> ${safe(getVal('school'))}</p>`;
      html += `<p class="small">Periksa unggahan di bawah, pastikan semua gambar sudah benar.</p>`;
      // previews (client-side)
      function renderFileList(inputId){
        const inp = document.getElementById(inputId);
        if (!inp || !inp.files || inp.files.length===0) return '';
        let out = '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">';
        for (let i=0;i<inp.files.length;i++){
          const f = inp.files[i];
          const url = URL.createObjectURL(f);
          out += `<div class="thumb"><img src="${url}" alt="${safe(f.name)}"></div>`;
        }
        out += '</div>';
        return out;
      }
      html += '<h4 style="margin-top:12px">Preview Unggahan</h4>';
      html += '<div class="small"><strong>Scan ID:</strong></div>' + renderFileList('idcard');
      html += '<div class="small"><strong>Bukti Follow:</strong></div>' + renderFileList('proof');
      html += '<div class="small"><strong>Twibbon:</strong></div>' + renderFileList('twibbon');
      html += '<div class="small"><strong>Bukti Transfer:</strong></div>' + renderFileList('transfer');

      summary.innerHTML = html;
      showStep(3);
    });

    $('#backTo2').addEventListener('click', ()=> showStep(2));

    // handle submit: normal form POST; show client toast while submitting
    $('#regForm').addEventListener('submit', function(e){
      // final checks (files count limits)
      const maxFiles = 10;
      const idcount = (document.getElementById('idcard').files||[]).length;
      const proofcount = (document.getElementById('proof').files||[]).length;
      const twcount = (document.getElementById('twibbon').files||[]).length;
      const trcount = (document.getElementById('transfer').files||[]).length;
      if (idcount>maxFiles || proofcount>maxFiles || twcount>maxFiles || trcount>maxFiles){
        e.preventDefault();
        showToast('Maksimum 10 file per unggahan. Kurangi jumlah file dan coba lagi.');
        showStep(2);
        return false;
      }
      showToast('Mengirim pendaftaran... Mohon tunggu', 6000);
      // allow form to submit (page reload will show server confirmation)
    });

    // previewing selected files in step2
    function attachPreview(inputId, previewId){
      const inp = document.getElementById(inputId);
      const box = document.getElementById(previewId);
      if (!inp || !box) return;
      inp.addEventListener('change', ()=>{
        box.innerHTML = '';
        for (let i=0;i<inp.files.length;i++){
          const f = inp.files[i];
          const url = URL.createObjectURL(f);
          const div = document.createElement('div');
          div.className = 'thumb';
          const img = document.createElement('img');
          img.src = url;
          img.alt = f.name;
          div.appendChild(img);
          box.appendChild(div);
        }
      });
    }
    attachPreview('idcard','idPreview');
    attachPreview('proof','proofPreview');
    attachPreview('twibbon','twibbonPreview');
    attachPreview('transfer','transferPreview');

    // safe text
    function safe(s){ return String(s).replace(/</g,'&lt;').replace(/>/g,'&gt;') }

    // init to step1
    showStep(1);
  })();
</script>

<script>
  const cards = document.querySelectorAll('.cat-card');

cards.forEach(card => {
  card.addEventListener('click', () => {
    // hapus class selected dari semua kartu
    cards.forEach(c => c.classList.remove('selected'));
    // tambahkan class selected ke kartu yang diklik
    card.classList.add('selected');
  });
});
</script>

<script>
  /* -------------------------
         Navbar scroll appearance
         ------------------------- */
      const navbar = document.getElementById('navbar');
      window.addEventListener('scroll', () => {
        if (window.scrollY > 80) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
      });

      /* -------------------------
         Mobile Menu toggle
         ------------------------- */
      const hambtn = document.getElementById('hambtn');
      const mobileMenu = document.getElementById('mobile-menu');
      const closeMobile = document.getElementById('closeMobile');

      function openMobile() {
        hambtn.classList.add('active');
        mobileMenu.classList.add('active');
        mobileMenu.setAttribute('aria-hidden', 'false');
        hambtn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
      }

      function closeMobileMenu() {
        hambtn.classList.remove('active');
        mobileMenu.classList.remove('active');
        mobileMenu.setAttribute('aria-hidden', 'true');
        hambtn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      }

      hambtn.addEventListener('click', () => {
        if (mobileMenu.classList.contains('active')) closeMobileMenu();
        else openMobile();
      });

      closeMobile && closeMobile.addEventListener('click', closeMobileMenu);

      // close mobile menu when clicking any anchor inside
      mobileMenu.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', closeMobileMenu);
      });

      /* -------------------------
         Smooth anchor scrolling (accessible)
         ------------------------- */
      document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
          const href = this.getAttribute('href');
          if (!href || href === '#') return;
          const target = document.querySelector(href);
          if (!target) return;
          e.preventDefault();
          closeMobileMenu();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          // update focus for accessibility
          setTimeout(() => target.setAttribute('tabindex', '-1'), 600);
        });
      });
</script>
</body>
</html>

<?php
session_start();
require_once "../config.php"; // Pastikan $conn adalah instance PDO

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Handle Delete
if (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['id'])) {
    $id = (int) $_POST['id'];

    try {
        $stmt = $conn->prepare("DELETE FROM pendaftaran WHERE id = ?");
        $stmt->execute([$id]);

        $_SESSION['message'] = "✅ Pendaftaran dengan ID $id berhasil dihapus!";
    } catch (PDOException $e) {
        $_SESSION['message'] = "❌ Gagal menghapus pendaftaran: " . $e->getMessage();
    }

    header("Location: pendaftaran.php");
    exit;
}

// Fetch all registrations
try {
    $stmt = $conn->query("SELECT * FROM pendaftaran ORDER BY created_at DESC");
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ Error fetch data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Links - Admin Panel</title>
    <link rel="shortcut icon" href="images\20250320_190104[1].png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --dark: #212529;
            --light: #f8f9fa;
            --sidebar-width: 250px;
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: #f5f7fa;
            color: var(--dark);
            min-height: 100vh;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark);
            color: white;
            position: fixed;
            height: 100vh;
            transition: var(--transition);
            z-index: 1000;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h3 {
            font-weight: 600;
            font-size: 1.5rem;
            margin: 0;
        }
        
        .sidebar-header p {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
            margin: 0;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu ul {
            list-style: none;
            padding: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 4px solid var(--primary);
        }
        
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 4px solid var(--primary);
        }
        
        .sidebar-menu i {
            margin-right: 15px;
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
        }
        
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 20px;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 30px;
            transition: var(--transition);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .welcome-text h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }
        
        .welcome-text p {
            color: #6c757d;
            margin: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info .btn-logout {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: var(--transition);
        }
        
        .user-info .btn-logout:hover {
            background: #e02c71;
            transform: translateY(-2px);
        }
        
        .user-info .btn-logout i {
            margin-right: 5px;
        }
        
        /* Page Content */
        .page-content {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .btn-add {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .btn-add:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }
        
        .btn-add i {
            margin-right: 8px;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
        }
        
        .custom-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
        }
        
        .custom-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .custom-table tr:last-child td {
            border-bottom: none;
        }
        
        .custom-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge-color {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 5px;
            vertical-align: middle;
        }
        
        .badge-primary { background: #4361ee; }
        .badge-danger { background: #f72585; }
        .badge-success { background: #4cc9f0; }
        .badge-dark { background: #212529; }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-edit {
            background: var(--warning);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            transition: var(--transition);
        }
        
        .btn-edit:hover {
            background: #e68a1a;
            transform: translateY(-1px);
        }
        
        .btn-delete {
            background: var(--danger);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            transition: var(--transition);
        }
        
        .btn-delete:hover {
            background: #e02c71;
            transform: translateY(-1px);
        }
        
        .btn-view {
            background: var(--success);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .btn-view:hover {
            background: #3ab7d9;
            transform: translateY(-1px);
        }
        
        .btn i {
            margin-right: 5px;
        }
        
        /* Modal Styles */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            background: var(--primary);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 15px 20px;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #e9ecef;
            border-radius: 0 0 12px 12px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #495057;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 8px;
            padding: 12px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            border: none;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ced4da;
        }
        
        .empty-state h4 {
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .sidebar-header h3, .sidebar-header p, .sidebar-menu span {
                display: none;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.4rem;
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 15px;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                display: none;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-info {
                margin-top: 15px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .btn-add {
                margin-top: 15px;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
            
            .menu-toggle {
                display: block;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
                background: var(--primary);
                color: white;
                border: none;
                border-radius: 5px;
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            }
        }
        
        .menu-toggle {
            display: none;
        }
        
        .sidebar.show {
            width: var(--sidebar-width);
            display: block;
        }
        
        .sidebar.show .sidebar-header h3,
        .sidebar.show .sidebar-header p,
        .sidebar.show .sidebar-menu span {
            display: block;
        }
        
        .sidebar.show .sidebar-menu i {
            margin-right: 15px;
        }
        
        .sidebar.show .sidebar-menu a {
            justify-content: flex-start;
            padding: 12px 20px;
        }
    </style>
</head>
<body>
<button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

<div class="admin-container">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Admin Panel</h3>
            <p>MHTeams Dashboard</p>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="pendaftaran.php" class="active"><i class="fas fa-clipboard-list"></i> Pendaftaran</a></li>
            </ul>
        </div>
        <div class="sidebar-footer">
            <p>Made with ❤️ by <strong>MHTeams</strong></p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header d-flex justify-content-between align-items-center">
            <div class="welcome-text">
                <h1>Daftar Pendaftar</h1>
                <p>Kelola pendaftar event / kategori yang tersedia</p>
            </div>
            <div class="user-info">
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>

        <div class="page-content">
            <?php if(isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); endif; ?>

            <?php if(count($registrations) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Sekolah</th>
                            <th>Email</th>
                            <th>Telepon</th>
                            <th>Kategori</th>
                            <th>Nama Tim</th>
                            <th>Anggota Tim</th>
                            <th>Catatan</th>
                            <th>ID Card</th>
                            <th>Bukti Follow</th>
                            <th>Twibbon</th>
                            <th>Bukti Transfer</th>
                            <th>Waktu Daftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($registrations as $r): ?>
                        <tr>
                            <!-- Data utama -->
                            <td class="text-center"><?= $r['id'] ?></td>
                            <td><?= htmlspecialchars($r['name']) ?></td>
                            <td><?= htmlspecialchars($r['school']) ?></td>
                            <td><?= htmlspecialchars($r['email']) ?></td>
                            <td><?= htmlspecialchars($r['phone']) ?></td>
                            <td><?= htmlspecialchars($r['category']) ?></td>
                            <td><?= htmlspecialchars($r['teamname']) ?></td>

                            <!-- Anggota tim -->
                            <td>
                                <?php if(!empty($r['members'])): 
                                    $members = json_decode($r['members'], true);
                                    if(is_array($members)){
                                        echo "<ul class='mb-0 ps-3'>";
                                        foreach($members as $m){
                                            echo "<li>".htmlspecialchars($m)."</li>";
                                        }
                                        echo "</ul>";
                                    }
                                endif; ?>
                            </td>

                            <td><?= htmlspecialchars($r['note']) ?></td>

                            <!-- Lampiran -->
                            <td>
                                <?php if($r['idcard']): 
                                    $arr = json_decode($r['idcard'],true);
                                    if(is_array($arr)){ foreach($arr as $f){ ?>
                                        <a href="../upload/<?= htmlspecialchars($f) ?>" target="_blank" class="badge bg-primary text-decoration-none mb-1">Lihat</a>
                                    <?php }} ?>
                            </td>
                            <td>
                                <?php if($r['proof']): 
                                    $arr = json_decode($r['proof'],true);
                                    if(is_array($arr)){ foreach($arr as $f){ ?>
                                        <a href="../upload/<?= htmlspecialchars($f) ?>" target="_blank" class="badge bg-success text-decoration-none mb-1">Lihat</a>
                                    <?php }} ?>
                            </td>
                            <td>
                                <?php if($r['twibbon']): 
                                    $arr = json_decode($r['twibbon'],true);
                                    if(is_array($arr)){ foreach($arr as $f){ ?>
                                        <a href="../upload/<?= htmlspecialchars($f) ?>" target="_blank" class="badge bg-warning text-dark text-decoration-none mb-1">Lihat</a>
                                    <?php }} ?>
                            </td>
                            <td>
                                <?php if($r['transfer']): 
                                    $arr = json_decode($r['transfer'],true);
                                    if(is_array($arr)){ foreach($arr as $f){ ?>
                                        <a href="../upload/<?= htmlspecialchars($f) ?>" target="_blank" class="badge bg-danger text-decoration-none mb-1">Lihat</a>
                                    <?php }} ?>
                            </td>

                            <td class="text-center"><?= date("d M Y H:i", strtotime($r['created_at'])) ?></td>

                            <!-- Aksi -->
                            <td class="text-center">
                                <form method="post" onsubmit="return confirm('Yakin ingin menghapus pendaftaran ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                                <button 
                                    type="button" 
                                    class="btn btn-primary btn-sm mt-2" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#emailModal" 
                                    data-email="<?= htmlspecialchars($r['email']) ?>">
                                    <i class="fas fa-envelope"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center p-4">
                <i class="fas fa-clipboard-list fa-3x text-muted mb-2"></i>
                <h4 class="text-muted">Belum ada pendaftar</h4>
                <p>Pendaftar akan muncul setelah mereka mendaftar melalui form.</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="footer mt-4 text-center">
            <p>Made with ❤️ by <strong>MHTeams</strong> | © <?= date('Y'); ?></p>
        </div>
    </div>
</div>

<!-- Modal Kirim Email -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="send_email.php">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-envelope"></i> Kirim Email</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="to_email" id="emailTo">
                    <div class="mb-3">
                        <label class="form-label">Judul Pesan</label>
                        <input type="text" class="form-control" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Isi Pesan</label>
                        <textarea class="form-control" name="message" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Kirim</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var emailModal = document.getElementById('emailModal');
emailModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var email = button.getAttribute('data-email');
    emailModal.querySelector('#emailTo').value = email;
});
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');
            
            if (window.innerWidth < 768 && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target) &&
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
        
        // Auto close alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
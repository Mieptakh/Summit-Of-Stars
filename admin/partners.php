<?php
session_start();
include "../config.php"; // pastikan koneksi $conn (PDO) tersedia

if(!isset($_SESSION['admin'])){ header("Location: login.php"); exit; }

// Handle Add/Edit/Delete
if(isset($_POST['action'])){
    $name = $_POST['name'];
    $image_path = $_POST['old_image'] ?? '';

    // Handle image upload
    if(isset($_FILES['image']) && $_FILES['image']['tmp_name']){
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image_path = '../images/partners_'.time()."_".rand(1000,9999).'.'.$ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $image_path);
        $image_path = substr($image_path,3); // remove "../" untuk path frontend
    }

    if($_POST['action']=='add'){
        $stmt = $conn->prepare("INSERT INTO partners (name, image_url) VALUES (?, ?)");
        $stmt->execute([$name, $image_path]);
        $_SESSION['message'] = "Partner berhasil ditambahkan!";
    } elseif($_POST['action']=='edit'){
        $id = $_POST['id'];
        $stmt = $conn->prepare("UPDATE partners SET name=?, image_url=? WHERE id=?");
        $stmt->execute([$name, $image_path, $id]);
        $_SESSION['message'] = "Partner berhasil diperbarui!";
    } elseif($_POST['action']=='delete'){
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM partners WHERE id=?");
        $stmt->execute([$id]);
        $_SESSION['message'] = "Partner berhasil dihapus!";
    }
    header("Location: partners.php"); exit;
}

// Fetch all partners
$partners = $conn->query("SELECT * FROM partners ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - Admin Panel</title>
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
            background: var(--success);
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
            background: #3ab7d9;
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
        
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .product-title {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .product-desc {
            font-size: 0.85rem;
            color: #6c757d;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-price {
            font-weight: 600;
            color: var(--success);
        }
        
        .product-type {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            background: #e9ecef;
            color: #495057;
        }
        
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
            background: var(--primary);
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
            background: var(--secondary);
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
            background: var(--success);
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
        
        .form-control, .form-select, .form-control-file {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus, .form-control-file:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 0.25rem rgba(76, 201, 240, 0.15);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .image-preview {
            width: 100%;
            max-width: 200px;
            height: 200px;
            border: 2px dashed #ced4da;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
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
                background: var(--success);
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
                <li><a href="partners.php" class="active"><i class="fas fa-handshake"></i> Partners</a></li>
            </ul>
        </div>
        <div class="sidebar-footer">
            <p>Made with ❤️ by <strong>MHTeams</strong></p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="welcome-text">
                <h1>Kelola Partners</h1>
                <p>Tambahkan, edit, atau hapus partner yang bekerja sama dengan Anda.</p>
            </div>
            <div class="user-info">
                <a href="logout.php" class="btn btn-danger"><i class="fas fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>

        <div class="page-content">
            <div class="d-flex justify-content-between mb-3">
                <h2>Daftar Partners</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus"></i> Tambah Partner Baru
                </button>
            </div>

            <?php if(isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); endif; ?>

            <?php if(count($partners) > 0): ?>
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nama Partner</th>
                        <th>Gambar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($partners as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" style="height:50px;"></td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $p['id'] ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus partner ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editModal<?= $p['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Partner</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="post" enctype="multipart/form-data">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="old_image" value="<?= $p['image_url'] ?>">
                                        <div class="mb-3">
                                            <label>Nama Partner</label>
                                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($p['name']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label>Gambar Partner</label>
                                            <input type="file" name="image" class="form-control">
                                            <small>Biarkan kosong jika tidak ingin mengganti gambar</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button class="btn btn-primary">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="text-center p-4">
                <i class="fas fa-handshake fa-3x text-muted mb-2"></i>
                <h4 class="text-muted">Belum ada partner</h4>
                <p>Tambahkan partner pertama dengan tombol "Tambah Partner Baru"</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="footer mt-4">
            <p>Made with ❤️ by <strong>MHTeams</strong> | © <?= date('Y'); ?></p>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Partner Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label>Nama Partner</label>
                        <input type="text" name="name" class="form-control" placeholder="Masukkan nama partner" required>
                    </div>
                    <div class="mb-3">
                        <label>Gambar Partner</label>
                        <input type="file" name="image" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary">Simpan Partner</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
        
        // Image preview for add modal
        const addImageInput = document.querySelector('#addModal input[type="file"]');
        const addImagePreview = document.getElementById('addImagePreview');
        
        if (addImageInput) {
            addImageInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        addImagePreview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    }
                    reader.readAsDataURL(file);
                } else {
                    addImagePreview.innerHTML = '<span class="text-muted">Pratinjau gambar</span>';
                }
            });
        }
        
        // Image preview for edit modals
        <?php foreach($products as $p): ?>
        const editImageInput<?= $p['id'] ?> = document.querySelector('#editModal<?= $p['id'] ?> input[type="file"]');
        const editImagePreview<?= $p['id'] ?> = document.getElementById('editImagePreview<?= $p['id'] ?>');
        
        if (editImageInput<?= $p['id'] ?>) {
            editImageInput<?= $p['id'] ?>.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        editImagePreview<?= $p['id'] ?>.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    }
                    reader.readAsDataURL(file);
                } else {
                    <?php if($p['image']): ?>
                        editImagePreview<?= $p['id'] ?>.innerHTML = '<img src="../<?= $p['image'] ?>" alt="Preview">';
                    <?php else: ?>
                        editImagePreview<?= $p['id'] ?>.innerHTML = '<span class="text-muted">Pratinjau gambar</span>';
                    <?php endif; ?>
                }
            });
        }
        <?php endforeach; ?>
    </script>
</body>
</html>
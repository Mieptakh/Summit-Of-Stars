<?php
// ==============================
// INIT DATABASE: SQLite FULL FIXED
// ==============================

// Lokasi database
$dbDir = __DIR__ . '/database';
$dbFile = $dbDir . '/competitions.db';

// Pastikan folder ada dan writable
if (!is_dir($dbDir)) {
    if (!mkdir($dbDir, 0777, true)) {
        die("Gagal membuat folder database. Pastikan PHP punya hak tulis.");
    }
} elseif (!is_writable($dbDir)) {
    die("Folder database tidak writable. Ubah permission folder: $dbDir");
}

try {
    // Buat koneksi SQLite
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==============================
    // Buat semua tabel
    // ==============================
    $db->exec("
        CREATE TABLE IF NOT EXISTS faq (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            question TEXT NOT NULL,
            answer TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS pendaftaran (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,              -- nama ketua / peserta tunggal
            school TEXT,                     -- asal sekolah / instansi
            email TEXT NOT NULL,             -- email kontak
            phone TEXT,                      -- nomor HP
            category TEXT NOT NULL,          -- kategori lomba
            teamname TEXT,                   -- nama tim (kosong jika perseorangan)
            members TEXT,                    -- JSON string anggota tim (maks 4)
            note TEXT,                       -- catatan tambahan
            
            -- kolom lampiran upload
            idcard TEXT,                     -- tanda pengenal (JSON daftar file)
            proof TEXT,                      -- bukti follow (JSON daftar file)
            twibbon TEXT,                    -- twibbon (JSON daftar file)
            transfer TEXT,                   -- bukti transfer (JSON daftar file)

            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS partners (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            image_url TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS kompetisi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            judul TEXT NOT NULL,
            deskripsi TEXT NOT NULL,
            icon_svg TEXT NOT NULL,
            registration_link TEXT,
            submission_link TEXT
        );

        CREATE TABLE IF NOT EXISTS timeline (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tanggal_start TEXT NOT NULL,
            tanggal_end TEXT NOT NULL,
            judul TEXT NOT NULL,
            deskripsi TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            label TEXT NOT NULL,
            value INTEGER NOT NULL
        );

        CREATE TABLE IF NOT EXISTS galeri (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            image_url TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role='admin'),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
    
    // ==============================
    // Insert admin jika belum ada
    // ==============================
    $checkAdmin = $db->query("SELECT COUNT(*) as c FROM users")->fetch(PDO::FETCH_ASSOC);
    if ($checkAdmin['c'] == 0) {
        $username = "admin";
        $password = password_hash("admin123", PASSWORD_DEFAULT); // password terenkripsi
        $role = "admin";

        $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $role]);
    }

    // ==============================
    // Insert dummy data jika belum ada
    // ==============================
    $check = $db->query("SELECT COUNT(*) as c FROM faq")->fetch(PDO::FETCH_ASSOC);
    if ($check['c'] == 0) {
        // --- FAQ ---
        $faqData = [
            [
                "Berapa biaya pendaftaran dan bagaimana sistem pembayarannya?",
                "Ada empat cabang lomba seru yang dapat diikuti, yaitu Debate Competition dengan biaya pendaftaran sebesar Rp150.000, Essay Competition sebesar Rp35.000, Innovation Case Challenge sebesar Rp60.000, dan Logic Puzzle Competition sebesar Rp50.000. Sistem pembayaran dilakukan melalui transfer ke Bank Mandiri dengan nomor rekening yang telah disediakan di Google Form pendaftaran. Peserta diharapkan melakukan pembayaran sesuai dengan biaya pendaftaran cabang lomba yang dipilih dan mengonfirmasi pembayaran melalui Google Form tersebut agar pendaftaran dapat diproses dengan lancar. Bukti transfer wajib disimpan sebagai bukti pembayaran dan konfirmasi keikutsertaan."
            ],
            [
                "Kapan pelaksanaan hari puncak nya?",
                "Untuk hari puncak akan dilaksanakan pada tanggal 18 Januari 2025."
            ],
            [
                "Apa saja aturan atau tata tertib lomba yang harus saya ikuti?",
                "Untuk mengetahui aturan lengkap dan tata tertib lomba, peserta dapat mengakses booklet resmi yang telah disediakan melalui tautan berikut: https://bit.ly/bookletcompetition"
            ],
            [
                "Apakah peserta mendapatkan sertifikat?",
                "Peserta akan mendapatkan sertifikat meskipun tidak sampai babak finalis."
            ],
            [
                "Bagaimana cara mendaftar lomba?",
                "Untuk mendaftar perlombaan kami sudah menyiapkan 4 link pendaftaran dan 2 link untuk pengumpulan yang dapat diakses melalui lomba yang sudah ada."
            ]
        ];
        $stmt = $db->prepare("INSERT INTO faq (question, answer) VALUES (?, ?)");
        foreach ($faqData as $f) $stmt->execute($f);

        // --- Partners ---
        $partnersData = [
            ["Pemerintah Provinsi Sumatera Selatan", "images/MHTeams.png"],
            ["Pemerintah Provinsi Sumatera Selatan (DISPORA)", "/partnership/DISPORA.png"],
            ["Dinas Pendidikan Sumsel", "/partnership/DPPI.png"],
            ["Universitas Sriwijaya (GDCOC)", "/partnership/GDCOC.png"],
            ["Bank Sumsel Babel (Himapenmas)", "/partnership/HIMAPENMAS.png"],
            ["PT Pupuk Sriwidjaja (HMPS PGSD UPGRIP)", "/partnership/HMPPGSD.png"],
            ["PT Bukit Asam (Kejar Mimpi Palembang)", "/partnership/KEJARMIMPIPALEMBANG.jpg"],
            ["PTBA (LOGO AIESEC)", "/partnership/AIESEC.png"],
            ["PT Pusri (LOGO GEN LIMAS)", "/partnership/GENLINMAS.png"],
            ["Pemerintah Provinsi Sumatera Selatan (IRRSA)", "/partnership/IRRSA.png"],
            ["Dinas Pendidikan Sumsel (Youth Ranger Jakarta)", "/partnership/YRIJAKARTA.png"],
            ["Universitas Sriwijaya (YRI SUMUT)", "/partnership/YRISUMUT.png"],
            ["Bank Sumsel Babel (YRI Yogyakarta)", "/partnership/YRIYOGYAKARTA.jpg"],
            ["PT Pupuk Sriwidjaja (Novo Club Region 8)", "/partnership/NOVOCLUBREG8.png"],
            ["PT Bukit Asam (RRI INDONESIA)", "/partnership/RRI.jpg"],
            ["PTBA (SRE UNSRI)", "/partnership/SRE.jpg"],
            ["PT Pusri (Youth Ranger Indonesia Sumatera Selatan)", "/partnership/YRISUMSEL.png"],
            ["PTBA (YRI Distrik Sumatera 2)", "/partnership/YRISUMATERADIS2.png"],
            ["PT Pusri (YRI Pusat)", "/partnership/YRIPUSAT.png"]
        ];
        $stmt = $db->prepare("INSERT INTO partners (name, image_url) VALUES (?, ?)");
        foreach ($partnersData as $p) $stmt->execute($p);

        // --- Kompetisi ---
        $kompetisiData = [
            [
                "Essay Competition",
                "Kompetisi menulis esai untuk melatih kemampuan analisis dan berpikir kritis peserta.",
                "<svg width='28' height='28'><rect width='24' height='24' fill='none' stroke='currentColor' stroke-width='2'/></svg>",
                "https://bit.ly/FormPendaftaranLombaEssayYRI", // registration link
                "https://bit.ly/PengumpulanKaryaEssayYRI"      // submission link
            ],
            [
                "Debate Competition",
                "Kompetisi debat untuk melatih kemampuan berbicara, logika, dan persuasi peserta.",
                "<svg width='28' height='28'><circle cx='12' cy='12' r='10' stroke='currentColor' fill='none' stroke-width='2'/></svg>",
                "https://bit.ly/FormPendaftaranLombaDebatYRI",
                null
            ],
            [
                "Innovation Case Competition",
                "Kompetisi inovasi kasus untuk mengembangkan solusi kreatif dan praktis terhadap permasalahan nyata.",
                "<svg width='28' height='28'><polygon points='12,2 22,22 2,22' fill='none' stroke='currentColor' stroke-width='2'/></svg>",
                "https://bit.ly/FormPendaftaranLombaInovasiCaseYRI",
                "https://bit.ly/PengumpulanKaryaInovasiCaseYRI"
            ],
            [
                "Puzzle Competition",
                "Kompetisi teka-teki untuk mengasah kemampuan logika, strategi, dan problem solving peserta.",
                "<svg width='28' height='28'><path d='M2 2 L22 2 L22 22 L2 22 Z' fill='none' stroke='currentColor' stroke-width='2'/></svg>",
                "https://bit.ly/formulirpendaftaranlogicpuzzle",
                null
            ]
        ];
        $stmt = $db->prepare("INSERT INTO kompetisi (judul, deskripsi, icon_svg, registration_link, submission_link) VALUES (?, ?, ?, ?, ?)");
        foreach ($kompetisiData as $k) $stmt->execute($k);

        // --- Timeline ---
        $timelineData = [
            ["2025-09-22", "2025-10-10", "Open Registration Batch 1", "Pendaftaran Batch 1 dibuka mulai 22 September hingga 10 Oktober."],
            ["2025-10-11", "2025-10-25", "Project Submission Batch 1", "Pengumpulan proyek Batch 1 dimulai 11 Oktober hingga 25 Oktober."],
            ["2025-10-12", "2025-11-09", "Open Registration Batch 2", "Pendaftaran Batch 2 dibuka mulai 12 Oktober hingga 9 November."],
            ["2025-11-10", "2025-11-16", "Project Submission Batch 2", "Pengumpulan proyek Batch 2 berlangsung 10-16 November."],
            ["2025-11-17", "2025-11-23", "Initial Screening", "Proses seleksi awal untuk semua peserta."],
            ["2025-11-26", "2025-11-26", "Initial Screening Result", "Pengumuman hasil seleksi awal."],
            ["2025-11-30", "2025-11-30", "Technical Meeting", "Peserta mengikuti technical meeting untuk persiapan lomba."],
            ["2025-12-05", "2025-12-07", "Final of Debate Competition", "Babak final kompetisi debat 5-7 Desember."],
            ["2025-12-12", "2025-12-12", "Final of Innovation Case Challenge", "Babak final Innovation Case Challenge 12 Desember."],
            ["2025-12-14", "2025-12-14", "Final of Essay Competition", "Babak final kompetisi esai 14 Desember."],
            ["2026-01-17", "2026-01-17", "Final of Puzzle Competition", "Babak final kompetisi puzzle 17 Januari."],
            ["2026-01-18", "2026-01-18", "Awarding Day", "Penghargaan bagi para pemenang kompetisi."]
        ];
        $stmt = $db->prepare("INSERT INTO timeline (tanggal_start, tanggal_end, judul, deskripsi) VALUES (?, ?, ?, ?)");
        foreach ($timelineData as $t) $stmt->execute($t);

        // --- Stats ---
        $statsData = [
            ["Peserta Aktif", 5000],
            ["Kategori Kompetisi", 25],
            ["Sekolah Berpartisipasi", 100],
            ["Total Hadiah (IDR)", 1000000]
        ];
        $stmt = $db->prepare("INSERT INTO stats (label, value) VALUES (?, ?)");
        foreach ($statsData as $s) $stmt->execute($s);

        // --- Galeri ---
        $galeriData = [
            ["Workshop Inovasi", "https://cdn-brilio-net.akamaized.net/webp/news/2023/10/07/265668/1200xauto-aspek-rasio-43-vs-169-manakah-yang-lebih-baik-untuk-foto-dan-video-231007p.jpg"],
            ["Final Debat", "https://cdn-brilio-net.akamaized.net/webp/news/2023/10/07/265668/1200xauto-aspek-rasio-43-vs-169-manakah-yang-lebih-baik-untuk-foto-dan-video-231007p.jpg"],
            ["Penganugerahan", "https://cdn-brilio-net.akamaized.net/webp/news/2023/10/07/265668/1200xauto-aspek-rasio-43-vs-169-manakah-yang-lebih-baik-untuk-foto-dan-video-231007p.jpg"]
        ];
        $stmt = $db->prepare("INSERT INTO galeri (title, image_url) VALUES (?, ?)");
        foreach ($galeriData as $g) $stmt->execute($g);
    }

    echo "✅ Database siap! Admin: 'admin', Password: 'admin123'. Semua tabel & dummy data sudah ada!";
} catch (PDOException $e) {
    die("❌ Error DB: " . $e->getMessage());
}

<?php
// ============================================================
// SESSION & TIMEZONE
// ============================================================
session_start();
date_default_timezone_set('Asia/Jakarta');

// ============================================================
// MODEL — Koneksi Database & Semua Query CRUD
// ============================================================

class Database {
    private $host = "localhost";
    private $db_name = "rental_ku";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            $this->conn->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            die("<div style='padding:20px;font-family:Arial;background:#fee;color:#900;border:1px solid #f99;margin:20px;border-radius:8px'>
                <h3>Koneksi Database Gagal</h3>
                <p>" . $e->getMessage() . "</p>
                <p><strong>Solusi:</strong></p>
                <ol>
                    <li>Pastikan MySQL di XAMPP sudah Running</li>
                    <li>Pastikan database <code>rental_ku</code> sudah dibuat</li>
                    <li>Import file <code>rental_ku.sql</code> melalui SQLyog</li>
                </ol>
            </div>");
        }
        return $this->conn;
    }
}

$db = new Database();
$pdo = $db->getConnection();

// ============================================================
// AUTO-SETUP: Buat tabel jika belum ada & perbaiki user admin
// ============================================================
try {
    $pdo->query("SELECT 1 FROM users LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            category VARCHAR(50) NOT NULL,
            price_per_day INT NOT NULL,
            stock INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            duration INT NOT NULL,
            subtotal INT NOT NULL,
            discount INT NOT NULL DEFAULT 0,
            total INT NOT NULL,
            payment INT NOT NULL,
            change_amount INT NOT NULL,
            status ENUM('Menunggu','Disewa','Dikembalikan','Selesai','Dibatalkan') DEFAULT 'Menunggu',
            transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id)
        );
    ");
}

// Auto-fix user admin
$adminCheck = $pdo->query("SELECT * FROM users WHERE username = 'admin' LIMIT 1")->fetch();
$needFix = false;
if (!$adminCheck) {
    $needFix = true;
} else {
    if (!password_verify('admin123', $adminCheck['password'])) {
        $needFix = true;
    }
}
if ($needFix) {
    $newHash = password_hash('admin123', PASSWORD_DEFAULT);
    if ($adminCheck) {
        $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'")->execute([$newHash]);
    } else {
        $pdo->prepare("INSERT INTO users (name, username, password) VALUES ('Administrator', 'admin', ?)")->execute([$newHash]);
    }
}

// Auto-seed data contoh kalau kosong
if ($pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() == 0) {
    $pdo->exec("
        INSERT INTO customers (name, phone, address) VALUES
        ('Budi Santoso', '081234567890', 'Jl. Merdeka No. 10, Jakarta'),
        ('Siti Aminah', '081298765432', 'Jl. Sudirman No. 25, Jakarta'),
        ('Rudi Hermawan', '081345678901', 'Jl. Gatot Subroto No. 5, Jakarta'),
        ('Dewi Lestari', '081456789012', 'Jl. Thamrin No. 15, Jakarta')
    ");
}
if ($pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() == 0) {
    $pdo->exec("
        INSERT INTO products (name, category, price_per_day, stock) VALUES
        ('Kamera DSLR', 'Kamera', 50000, 5),
        ('Tripod', 'Aksesoris', 15000, 10),
        ('Laptop', 'Elektronik', 100000, 3),
        ('Proyektor', 'Elektronik', 75000, 4),
        ('Speaker', 'Audio', 30000, 8),
        ('Mikrofon', 'Audio', 25000, 12)
    ");
}

// ---------- QUERY: USERS ----------
function findUserByUsername($username) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

// ---------- QUERY: CUSTOMERS ----------
function getAllCustomers($search = '') {
    global $pdo;
    if ($search) {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE name LIKE ? OR phone LIKE ? ORDER BY id DESC");
        $stmt->execute(["%$search%", "%$search%"]);
        return $stmt->fetchAll();
    }
    return $pdo->query("SELECT * FROM customers ORDER BY id DESC")->fetchAll();
}

function getCustomerById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function createCustomer($name, $phone, $address) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
    return $stmt->execute([$name, $phone, $address]);
}

function updateCustomer($id, $name, $phone, $address) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?");
    return $stmt->execute([$name, $phone, $address, $id]);
}

function deleteCustomer($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    return $stmt->execute([$id]);
}

// ---------- QUERY: PRODUCTS ----------
function getAllProducts($search = '') {
    global $pdo;
    if ($search) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE name LIKE ? OR category LIKE ? ORDER BY id DESC");
        $stmt->execute(["%$search%", "%$search%"]);
        return $stmt->fetchAll();
    }
    return $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
}

function getProductById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function createProduct($name, $category, $price, $stock) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO products (name, category, price_per_day, stock) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$name, $category, $price, $stock]);
}

function updateProduct($id, $name, $category, $price, $stock) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE products SET name = ?, category = ?, price_per_day = ?, stock = ? WHERE id = ?");
    return $stmt->execute([$name, $category, $price, $stock, $id]);
}

function deleteProduct($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    return $stmt->execute([$id]);
}

function reduceProductStock($id, $qty) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
    return $stmt->execute([$qty, $id, $qty]);
}

// ---------- QUERY: TRANSACTIONS ----------
function getAllTransactions($search = '', $status = '', $startDate = '', $endDate = '') {
    global $pdo;
    $sql = "SELECT t.*, c.name AS customer_name, p.name AS product_name, p.category AS product_category
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            JOIN products p ON t.product_id = p.id
            WHERE 1=1";
    $params = [];

    if ($search) {
        $sql .= " AND (c.name LIKE ? OR p.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($status) {
        $sql .= " AND t.status = ?";
        $params[] = $status;
    }
    if ($startDate && $endDate) {
        $sql .= " AND DATE(t.transaction_date) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    }

    $sql .= " ORDER BY t.transaction_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getRecentTransactions($limit = 5) {
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT t.*, c.name AS customer_name, p.name AS product_name
         FROM transactions t
         JOIN customers c ON t.customer_id = c.id
         JOIN products p ON t.product_id = p.id
         ORDER BY t.transaction_date DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function createTransaction($customerId, $productId, $quantity, $duration, $subtotal, $discount, $total, $payment, $change) {
    global $pdo;
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO transactions
             (customer_id, product_id, quantity, duration, subtotal, discount, total, payment, change_amount, status, transaction_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Disewa', NOW())"
        );
        $stmt->execute([$customerId, $productId, $quantity, $duration, $subtotal, $discount, $total, $payment, $change]);
        $transactionId = $pdo->lastInsertId();

        // Kurangi stok dengan validasi
        $stmtStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
        $stmtStock->execute([$quantity, $productId, $quantity]);

        if ($stmtStock->rowCount() === 0) {
            throw new Exception("Stok tidak cukup");
        }

        $pdo->commit();
        return $transactionId;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function updateTransactionStatus($id, $status) {
    global $pdo;
    $validStatus = ['Menunggu','Disewa','Dikembalikan','Selesai','Dibatalkan'];
    if (!in_array($status, $validStatus)) return false;

    // Kalau status jadi Dikembalikan atau Dibatalkan, kembalikan stok
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$id]);
    $trx = $stmt->fetch();
    if (!$trx) return false;

    try {
        $pdo->beginTransaction();

        $oldStatus = $trx['status'];
        $newStatus = $status;

        // Kalau dari Disewa/Menunggu → Dikembalikan/Dibatalkan, kembalikan stok
        $needReturnStock = in_array($oldStatus, ['Menunggu','Disewa'])
                        && in_array($newStatus, ['Dikembalikan','Dibatalkan']);
        // Kalau dari Dikembalikan/Dibatalkan → Disewa, kurangi stok lagi
        $needReduceStock = in_array($oldStatus, ['Dikembalikan','Dibatalkan'])
                        && $newStatus === 'Disewa';

        if ($needReturnStock) {
            $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")
                ->execute([$trx['quantity'], $trx['product_id']]);
        }
        if ($needReduceStock) {
            $stmtStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $stmtStock->execute([$trx['quantity'], $trx['product_id'], $trx['quantity']]);
            if ($stmtStock->rowCount() === 0) {
                throw new Exception("Stok tidak cukup");
            }
        }

        $pdo->prepare("UPDATE transactions SET status = ? WHERE id = ?")
            ->execute([$status, $id]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function deleteTransaction($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$id]);
    $trx = $stmt->fetch();
    if (!$trx) return false;

    try {
        $pdo->beginTransaction();
        // Kalau transaksi masih aktif, kembalikan stok
        if (in_array($trx['status'], ['Menunggu','Disewa'])) {
            $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")
                ->execute([$trx['quantity'], $trx['product_id']]);
        }
        $pdo->prepare("DELETE FROM transactions WHERE id = ?")->execute([$id]);
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

// ---------- STATISTIK DASHBOARD ----------
function getDashboardStats() {
    global $pdo;
    return [
        'total_products'     => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'total_customers'    => $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
        'total_transactions' => $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn(),
        'today_revenue'      => $pdo->query("SELECT COALESCE(SUM(total), 0) FROM transactions WHERE DATE(transaction_date) = CURDATE() AND status != 'Dibatalkan'")->fetchColumn(),
    ];
}

// ---------- FUNGSI DISKON ----------
// Aturan: jika quantity >= 10 → 8 pertama harga normal, sisanya harga setengah
function hitungDiskon($pricePerDay, $quantity, $duration) {
    // Subtotal awal = harga × jumlah × durasi
    $subtotal = $pricePerDay * $quantity * $duration;

    if ($quantity < 10) {
        return [
            'subtotal' => $subtotal,
            'discount' => 0,
            'total'    => $subtotal,
            'rincian'  => "$quantity × Rp " . number_format($pricePerDay, 0, ',', '.') . " × $duration hari"
        ];
    }

    // Kalau quantity >= 10
    $normalQty = 8;
    $discountQty = $quantity - 8;
    $discountPrice = $pricePerDay / 2;

    $subtotalNormal = $normalQty * $pricePerDay * $duration;
    $subtotalDiskon = $discountQty * $discountPrice * $duration;
    $total = $subtotalNormal + $subtotalDiskon;
    $discount = $subtotal - $total;

    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total'    => $total,
        'rincian'  => "$normalQty × Rp " . number_format($pricePerDay, 0, ',', '.') .
                      " + $discountQty × Rp " . number_format($discountPrice, 0, ',', '.') .
                      " (diskon 50%) × $duration hari"
    ];
}

// ---------- HELPER BADGE ----------
function badgeStatus($status) {
    $map = [
        'Menunggu'      => 'badge-warning',
        'Disewa'        => 'badge-primary',
        'Dikembalikan'  => 'badge-info',
        'Selesai'       => 'badge-success',
        'Dibatalkan'    => 'badge-danger'
    ];
    $class = $map[$status] ?? 'badge-secondary';
    return '<span class="badge ' . $class . '">' . htmlspecialchars($status) . '</span>';
}

function badgeStock($stock) {
    if ($stock <= 0) {
        return '<span class="badge badge-danger">Habis</span>';
    } elseif ($stock <= 3) {
        return '<span class="badge badge-warning">Stok Menipis</span>';
    }
    return '<span class="badge badge-success">Tersedia</span>';
}

// ============================================================
// CONTROLLER — Handle Request
// ============================================================

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? '';
$message = '';
$messageType = '';

// ---------- LOGIN ----------
if (isset($_POST['do_login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $user = findUserByUsername($username);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['flash_success'] = 'Login berhasil!';
        header('Location: index.php?page=dashboard');
        exit;
    } else {
        $_SESSION['flash_error'] = 'Username atau password salah!';
        header('Location: index.php?page=login');
        exit;
    }
}

// ---------- LOGOUT ----------
if ($page === 'logout') {
    session_destroy();
    header('Location: index.php?page=login');
    exit;
}

// ---------- CRUD CUSTOMER ----------
if (isset($_POST['save_customer'])) {
    $id      = $_POST['id'] ?? '';
    $name    = trim($_POST['name']);
    $phone   = trim($_POST['phone']);
    $address = trim($_POST['address']);

    if ($name && $phone) {
        if ($id) {
            updateCustomer($id, $name, $phone, $address);
            $_SESSION['flash_success'] = 'Pelanggan berhasil diubah!';
        } else {
            createCustomer($name, $phone, $address);
            $_SESSION['flash_success'] = 'Pelanggan berhasil ditambahkan!';
        }
    } else {
        $_SESSION['flash_error'] = 'Nama dan telepon wajib diisi!';
    }
    header('Location: index.php?page=pelanggan');
    exit;
}

if ($page === 'pelanggan' && $action === 'hapus') {
    $id = $_GET['id'] ?? 0;
    if ($id) {
        deleteCustomer($id);
        $_SESSION['flash_success'] = 'Pelanggan berhasil dihapus!';
    }
    header('Location: index.php?page=pelanggan');
    exit;
}

// ---------- CRUD PRODUCT ----------
if (isset($_POST['save_product'])) {
    $id       = $_POST['id'] ?? '';
    $name     = trim($_POST['name']);
    $category = trim($_POST['category']);
    $price    = (int) $_POST['price_per_day'];
    $stock    = (int) $_POST['stock'];

    if ($name && $category && $price > 0 && $stock >= 0) {
        if ($id) {
            updateProduct($id, $name, $category, $price, $stock);
            $_SESSION['flash_success'] = 'Barang berhasil diubah!';
        } else {
            createProduct($name, $category, $price, $stock);
            $_SESSION['flash_success'] = 'Barang berhasil ditambahkan!';
        }
    } else {
        $_SESSION['flash_error'] = 'Data tidak valid!';
    }
    header('Location: index.php?page=barang');
    exit;
}

if ($page === 'barang' && $action === 'hapus') {
    $id = $_GET['id'] ?? 0;
    if ($id) {
        deleteProduct($id);
        $_SESSION['flash_success'] = 'Barang berhasil dihapus!';
    }
    header('Location: index.php?page=barang');
    exit;
}

// ---------- TRANSAKSI BARU ----------
if (isset($_POST['save_transaction'])) {
    $customerId = (int) $_POST['customer_id'];
    $productId  = (int) $_POST['product_id'];
    $quantity   = (int) $_POST['quantity'];
    $duration   = (int) $_POST['duration'];
    $payment    = (int) $_POST['payment'];

    $product = getProductById($productId);

    if ($product && $quantity > 0 && $duration > 0) {
        // Validasi stok
        if ($product['stock'] < $quantity) {
            $_SESSION['flash_error'] = "Stok {$product['name']} tidak cukup (tersisa {$product['stock']})!";
            header('Location: index.php?page=transaksi');
            exit;
        }

        // Hitung ulang di PHP (jangan percaya JS)
        $calc = hitungDiskon($product['price_per_day'], $quantity, $duration);

        if ($payment < $calc['total']) {
            $_SESSION['flash_error'] = 'Pembayaran kurang Rp ' . number_format($calc['total'] - $payment, 0, ',', '.');
            header('Location: index.php?page=transaksi');
            exit;
        }

        $change = $payment - $calc['total'];
        $trxId = createTransaction(
            $customerId, $productId, $quantity, $duration,
            $calc['subtotal'], $calc['discount'], $calc['total'],
            $payment, $change
        );

        if ($trxId) {
            $_SESSION['flash_success'] = 'Transaksi berhasil disimpan!';
        } else {
            $_SESSION['flash_error'] = 'Gagal menyimpan transaksi (stok tidak cukup)!';
        }
    } else {
        $_SESSION['flash_error'] = 'Data transaksi tidak valid!';
    }
    header('Location: index.php?page=transaksi');
    exit;
}

// ---------- UBAH STATUS TRANSAKSI ----------
if ($page === 'riwayat' && $action === 'status') {
    $id = (int) ($_GET['id'] ?? 0);
    $status = $_GET['status'] ?? '';
    if ($id && updateTransactionStatus($id, $status)) {
        $_SESSION['flash_success'] = 'Status berhasil diubah!';
    } else {
        $_SESSION['flash_error'] = 'Gagal mengubah status!';
    }
    header('Location: index.php?page=riwayat');
    exit;
}

// ---------- HAPUS TRANSAKSI ----------
if ($page === 'riwayat' && $action === 'hapus') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id && deleteTransaction($id)) {
        $_SESSION['flash_success'] = 'Transaksi berhasil dihapus & stok dikembalikan!';
    } else {
        $_SESSION['flash_error'] = 'Gagal menghapus transaksi!';
    }
    header('Location: index.php?page=riwayat');
    exit;
}

// ---------- PROTEKSI LOGIN ----------
$publicPages = ['login'];
if (!isset($_SESSION['user_id']) && !in_array($page, $publicPages)) {
    header('Location: index.php?page=login');
    exit;
}
if (isset($_SESSION['user_id']) && $page === 'login') {
    header('Location: index.php?page=dashboard');
    exit;
}

// Flash messages
if (isset($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    $messageType = 'success';
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $message = $_SESSION['flash_error'];
    $messageType = 'error';
    unset($_SESSION['flash_error']);
}

// ============================================================
// VIEW — HTML Tampilan
// ============================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentalKu - Penyewaan Barang</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<?php if ($page === 'login'): ?>
<!-- ============================ LOGIN ============================ -->
<div class="login-page">
    <div class="login-card">
        <div class="login-brand">
            <div class="login-logo">R</div>
            <h1>RentalKu</h1>
            <p>Aplikasi Penyewaan Barang</p>
        </div>
        <form method="POST" action="index.php">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Masukkan username" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password" required>
            </div>
            <button type="submit" name="do_login" class="btn btn-primary btn-block btn-lg">Masuk</button>
        </form>
        <div class="login-hint">
            <strong>Akun default:</strong><br>
            Username: <code>admin</code> / Password: <code>admin123</code>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ============================ HALAMAN UTAMA ============================ -->
<div class="app-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-logo">R</div>
            <div class="brand-text">
                <span class="brand-name">RentalKu</span>
                <span class="brand-sub">Penyewaan Barang</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <span class="nav-label">Menu Utama</span>
            <a href="index.php?page=dashboard" class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                Dashboard
            </a>
            <a href="index.php?page=pelanggan" class="nav-item <?= $page === 'pelanggan' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Pelanggan
            </a>
            <a href="index.php?page=barang" class="nav-item <?= $page === 'barang' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                Barang
            </a>
            <a href="index.php?page=transaksi" class="nav-item <?= $page === 'transaksi' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                Transaksi
            </a>
            <a href="index.php?page=riwayat" class="nav-item <?= $page === 'riwayat' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Riwayat
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></div>
                <div class="user-detail">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                    <span class="user-role">Administrator</span>
                </div>
            </div>
            <button class="btn-logout" onclick="confirmLogout()" title="Logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </button>
        </div>
    </aside>

    <!-- MAIN AREA -->
    <div class="main-area">
        <header class="topbar">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div class="topbar-title">
                <h1>
                    <?php
                    $titles = [
                        'dashboard' => 'Dashboard',
                        'pelanggan' => 'Manajemen Pelanggan',
                        'barang'    => 'Manajemen Barang',
                        'transaksi' => 'Transaksi Penyewaan',
                        'riwayat'   => 'Riwayat Transaksi'
                    ];
                    echo $titles[$page] ?? 'Dashboard';
                    ?>
                </h1>
                <span class="topbar-subtitle" id="currentDateTime"></span>
            </div>
            <div class="topbar-right">
                <div class="cashier-chip">
                    <span class="chip-dot"></span>
                    <span><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                </div>
            </div>
        </header>

        <main class="content">

        <?php
        // ---------- DASHBOARD ----------
        if ($page === 'dashboard'):
            $stats = getDashboardStats();
            $recentTrx = getRecentTransactions(5);
        ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon stat-primary">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    </div>
                    <div class="stat-body">
                        <span class="stat-label">Total Barang</span>
                        <span class="stat-value"><?= $stats['total_products'] ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-success">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    </div>
                    <div class="stat-body">
                        <span class="stat-label">Total Pelanggan</span>
                        <span class="stat-value"><?= $stats['total_customers'] ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-warning">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    </div>
                    <div class="stat-body">
                        <span class="stat-label">Total Transaksi</span>
                        <span class="stat-value"><?= $stats['total_transactions'] ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-danger">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div class="stat-body">
                        <span class="stat-label">Pendapatan Hari Ini</span>
                        <span class="stat-value">Rp <?= number_format($stats['today_revenue'], 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>Transaksi Terbaru</h3>
                    <a href="index.php?page=riwayat" class="link">Lihat Semua</a>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pelanggan</th>
                                <th>Barang</th>
                                <th class="text-center">Jumlah</th>
                                <th class="text-center">Durasi</th>
                                <th class="text-right">Total</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTrx)): ?>
                                <tr><td colspan="8" class="empty-state">Belum ada transaksi</td></tr>
                            <?php else: foreach ($recentTrx as $t):
                                $dt = new DateTime($t['transaction_date']);
                            ?>
                                <tr>
                                    <td><span class="mono">#TRX<?= str_pad($t['id'], 3, '0', STR_PAD_LEFT) ?></span></td>
                                    <td><?= htmlspecialchars($t['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($t['product_name']) ?></td>
                                    <td class="text-center"><?= $t['quantity'] ?></td>
                                    <td class="text-center"><?= $t['duration'] ?> hari</td>
                                    <td class="text-right">Rp <?= number_format($t['total'], 0, ',', '.') ?></td>
                                    <td><?= badgeStatus($t['status']) ?></td>
                                    <td><?= $dt->format('d M Y, H:i') ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php
        // ---------- PELANGGAN ----------
        elseif ($page === 'pelanggan'):
            $search = $_GET['search'] ?? '';
            $customers = getAllCustomers($search);
        ?>
            <div class="panel">
                <div class="panel-header">
                    <h3>Daftar Pelanggan</h3>
                    <div class="panel-actions">
                        <form method="GET" class="search-form">
                            <input type="hidden" name="page" value="pelanggan">
                            <input type="text" name="search" placeholder="Cari pelanggan..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-secondary">Cari</button>
                        </form>
                        <button class="btn btn-primary" onclick="openCustomerModal()">+ Tambah Pelanggan</button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>No. Telepon</th>
                                <th>Alamat</th>
                                <th>Tanggal Dibuat</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr><td colspan="6" class="empty-state">Tidak ada pelanggan</td></tr>
                            <?php else: foreach ($customers as $c):
                                $dt = new DateTime($c['created_at']);
                            ?>
                                <tr>
                                    <td>#<?= $c['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($c['phone']) ?></td>
                                    <td><?= htmlspecialchars($c['address']) ?></td>
                                    <td><?= $dt->format('d M Y, H:i') ?></td>
                                    <td class="text-center">
                                        <button class="btn-icon" onclick='editCustomer(<?= json_encode($c) ?>)' title="Edit">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <button class="btn-icon btn-icon-danger" onclick='deleteCustomer(<?= $c['id'] ?>, <?= json_encode($c['name']) ?>)' title="Hapus">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MODAL CUSTOMER -->
            <div class="modal-overlay" id="customerModal">
                <div class="modal">
                    <div class="modal-header">
                        <h3 id="customerModalTitle">Tambah Pelanggan</h3>
                        <button class="modal-close" onclick="closeModal('customerModal')">&times;</button>
                    </div>
                    <form method="POST" action="index.php">
                        <div class="modal-body">
                            <input type="hidden" name="id" id="customerId">
                            <div class="form-group">
                                <label>Nama Pelanggan</label>
                                <input type="text" name="name" id="customerName" placeholder="Contoh: Budi Santoso" required>
                            </div>
                            <div class="form-group">
                                <label>No. Telepon</label>
                                <input type="text" name="phone" id="customerPhone" placeholder="08xxxxxxxxxx" required>
                            </div>
                            <div class="form-group">
                                <label>Alamat</label>
                                <textarea name="address" id="customerAddress" placeholder="Alamat lengkap" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('customerModal')">Batal</button>
                            <button type="submit" name="save_customer" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php
        // ---------- BARANG ----------
        elseif ($page === 'barang'):
            $search = $_GET['search'] ?? '';
            $products = getAllProducts($search);
        ?>
            <div class="panel">
                <div class="panel-header">
                    <h3>Daftar Barang</h3>
                    <div class="panel-actions">
                        <form method="GET" class="search-form">
                            <input type="hidden" name="page" value="barang">
                            <input type="text" name="search" placeholder="Cari barang..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-secondary">Cari</button>
                        </form>
                        <button class="btn btn-primary" onclick="openProductModal()">+ Tambah Barang</button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Barang</th>
                                <th>Kategori</th>
                                <th class="text-right">Harga / Hari</th>
                                <th class="text-center">Stok</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr><td colspan="7" class="empty-state">Tidak ada barang</td></tr>
                            <?php else: foreach ($products as $p): ?>
                                <tr>
                                    <td>#<?= $p['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($p['category']) ?></td>
                                    <td class="text-right">Rp <?= number_format($p['price_per_day'], 0, ',', '.') ?></td>
                                    <td class="text-center"><?= $p['stock'] ?></td>
                                    <td><?= badgeStock($p['stock']) ?></td>
                                    <td class="text-center">
                                        <button class="btn-icon" onclick='editProduct(<?= json_encode($p) ?>)' title="Edit">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <button class="btn-icon btn-icon-danger" onclick='deleteProduct(<?= $p['id'] ?>, <?= json_encode($p['name']) ?>)' title="Hapus">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MODAL PRODUCT -->
            <div class="modal-overlay" id="productModal">
                <div class="modal">
                    <div class="modal-header">
                        <h3 id="productModalTitle">Tambah Barang</h3>
                        <button class="modal-close" onclick="closeModal('productModal')">&times;</button>
                    </div>
                    <form method="POST" action="index.php">
                        <div class="modal-body">
                            <input type="hidden" name="id" id="productId">
                            <div class="form-group">
                                <label>Nama Barang</label>
                                <input type="text" name="name" id="productName" placeholder="Contoh: Kamera DSLR" required>
                            </div>
                            <div class="form-group">
                                <label>Kategori</label>
                                <input type="text" name="category" id="productCategory" placeholder="Contoh: Kamera" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Harga / Hari (Rp)</label>
                                    <input type="number" name="price_per_day" id="productPrice" min="1" placeholder="0" required>
                                </div>
                                <div class="form-group">
                                    <label>Stok</label>
                                    <input type="number" name="stock" id="productStock" min="0" placeholder="0" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('productModal')">Batal</button>
                            <button type="submit" name="save_product" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php
        // ---------- TRANSAKSI ----------
        elseif ($page === 'transaksi'):
            $customers = getAllCustomers();
            $products = getAllProducts();
        ?>
            <div class="transaksi-layout">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Form Transaksi Penyewaan</h3>
                    </div>
                    <form method="POST" action="index.php" id="transaksiForm">
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Pelanggan</label>
                                <select name="customer_id" id="customerSelect" required>
                                    <option value="">-- Pilih Pelanggan --</option>
                                    <?php foreach ($customers as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> - <?= htmlspecialchars($c['phone']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Barang</label>
                                <select name="product_id" id="productSelect" required onchange="updateHarga()">
                                    <option value="">-- Pilih Barang --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= $p['id'] ?>"
                                                data-price="<?= $p['price_per_day'] ?>"
                                                data-stock="<?= $p['stock'] ?>"
                                                data-name="<?= htmlspecialchars($p['name']) ?>">
                                            <?= htmlspecialchars($p['name']) ?> - Rp <?= number_format($p['price_per_day'], 0, ',', '.') ?>/hari (Stok: <?= $p['stock'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Jumlah</label>
                                    <input type="number" name="quantity" id="quantityInput" min="1" value="1" required oninput="updateHarga()">
                                </div>
                                <div class="form-group">
                                    <label>Durasi (hari)</label>
                                    <input type="number" name="duration" id="durationInput" min="1" value="1" required oninput="updateHarga()">
                                </div>
                            </div>

                            <!-- Rincian Perhitungan -->
                            <div class="calc-box" id="calcBox">
                                <div class="calc-row">
                                    <span>Harga / Hari</span>
                                    <span id="calcPrice">Rp 0</span>
                                </div>
                                <div class="calc-row">
                                    <span>Jumlah × Durasi</span>
                                    <span id="calcQty">0 × 0</span>
                                </div>
                                <div class="calc-row">
                                    <span>Subtotal</span>
                                    <span id="calcSubtotal">Rp 0</span>
                                </div>
                                <div class="calc-row text-success">
                                    <span>Diskon</span>
                                    <span id="calcDiscount">- Rp 0</span>
                                </div>
                                <div class="calc-row calc-total">
                                    <span>Total</span>
                                    <span id="calcTotal">Rp 0</span>
                                </div>
                                <div class="calc-info" id="calcInfo"></div>
                            </div>

                            <div class="form-group">
                                <label>Uang Dibayar (Rp)</label>
                                <input type="number" name="payment" id="paymentInput" min="0" placeholder="0" required oninput="updateKembalian()">
                            </div>

                            <div class="calc-box">
                                <div class="calc-row calc-total">
                                    <span>Kembalian</span>
                                    <span id="calcChange">Rp 0</span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset</button>
                            <button type="button" class="btn btn-primary" onclick="konfirmasiTransaksi()">Simpan Transaksi</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php
        // ---------- RIWAYAT ----------
        elseif ($page === 'riwayat'):
            $search    = $_GET['search'] ?? '';
            $status    = $_GET['status'] ?? '';
            $startDate = $_GET['start_date'] ?? '';
            $endDate   = $_GET['end_date'] ?? '';
            $transactions = getAllTransactions($search, $status, $startDate, $endDate);
        ?>
            <div class="panel">
                <div class="panel-header">
                    <h3>Riwayat Transaksi</h3>
                </div>
                <div class="filter-bar">
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="page" value="riwayat">
                        <input type="text" name="search" placeholder="Cari pelanggan/barang..." value="<?= htmlspecialchars($search) ?>">
                        <select name="status">
                            <option value="">Semua Status</option>
                            <?php foreach (['Menunggu','Disewa','Dikembalikan','Selesai','Dibatalkan'] as $st): ?>
                                <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                        <span>s/d</span>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="index.php?page=riwayat" class="btn btn-secondary">Reset</a>
                    </form>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>Pelanggan</th>
                                <th>Barang</th>
                                <th class="text-center">Qty</th>
                                <th class="text-center">Durasi</th>
                                <th class="text-right">Subtotal</th>
                                <th class="text-right">Diskon</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Bayar</th>
                                <th class="text-right">Kembali</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr><td colspan="14" class="empty-state">Tidak ada transaksi</td></tr>
                            <?php else: foreach ($transactions as $t):
                                $dt = new DateTime($t['transaction_date']);
                            ?>
                                <tr>
                                    <td><span class="mono">#TRX<?= str_pad($t['id'], 3, '0', STR_PAD_LEFT) ?></span></td>
                                    <td><?= $dt->format('d M Y') ?></td>
                                    <td><?= $dt->format('H:i') ?></td>
                                    <td><?= htmlspecialchars($t['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($t['product_name']) ?></td>
                                    <td class="text-center"><?= $t['quantity'] ?></td>
                                    <td class="text-center"><?= $t['duration'] ?> hari</td>
                                    <td class="text-right">Rp <?= number_format($t['subtotal'], 0, ',', '.') ?></td>
                                    <td class="text-right text-success">Rp <?= number_format($t['discount'], 0, ',', '.') ?></td>
                                    <td class="text-right"><strong>Rp <?= number_format($t['total'], 0, ',', '.') ?></strong></td>
                                    <td class="text-right">Rp <?= number_format($t['payment'], 0, ',', '.') ?></td>
                                    <td class="text-right">Rp <?= number_format($t['change_amount'], 0, ',', '.') ?></td>
                                    <td><?= badgeStatus($t['status']) ?></td>
                                    <td class="text-center">
                                        <button class="btn-icon" onclick='showDetail(<?= json_encode($t) ?>)' title="Detail">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                        </button>
                                        <select class="status-select" onchange="changeStatus(<?= $t['id'] ?>, this.value)">
                                            <?php foreach (['Menunggu','Disewa','Dikembalikan','Selesai','Dibatalkan'] as $st): ?>
                                                <option value="<?= $st ?>" <?= $t['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn-icon btn-icon-danger" onclick='deleteTransaction(<?= $t['id'] ?>)' title="Hapus">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        </main>
    </div>
</div>
<?php endif; ?>

<script src="app.js"></script>
<script>
<?php if ($message): ?>
    Swal.fire({
        icon: '<?= $messageType ?>',
        title: '<?= $messageType === 'success' ? 'Berhasil' : 'Gagal' ?>',
        text: '<?= addslashes($message) ?>',
        timer: 2500,
        showConfirmButton: false
    });
<?php endif; ?>
</script>

</body>
</html>
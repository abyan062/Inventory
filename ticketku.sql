-- ============================================================
-- DATABASE: TICKETKU
-- Materi latihan MySQL: Login, CRUD, Timestamp, Durasi,
-- Penjualan Tiket, Transaksi, Detail Transaksi, dan Record
-- ============================================================

CREATE DATABASE IF NOT EXISTS ticketku;
USE ticketku;

-- ============================================================
-- 1. TABLE USERS
-- Untuk login dan role admin/kasir
-- ============================================================
CREATE TABLE users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    role ENUM('admin', 'kasir') DEFAULT 'kasir',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Contoh data
-- Catatan: untuk aplikasi PHP sungguhan, password sebaiknya
-- dibuat menggunakan password_hash(), bukan teks biasa.
INSERT INTO users (username, password, nama, role) VALUES
('abyan', 'belajar123', 'Abyan', 'admin'),
('budi', 'kasir123', 'Budi', 'kasir');

-- ============================================================
-- 2. TABLE EVENTS
-- Menyimpan informasi acara dan waktu mulai/selesai
-- ============================================================
CREATE TABLE events (
    id_event INT AUTO_INCREMENT PRIMARY KEY,
    nama_event VARCHAR(100) NOT NULL,
    lokasi VARCHAR(150) NOT NULL,
    tanggal_event DATE NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO events
(nama_event, lokasi, tanggal_event, jam_mulai, jam_selesai)
VALUES
('Konser Musik Indonesia',
 'Surabaya Convention Hall',
 '2026-10-10',
 '19:00:00',
 '22:00:00');

-- ============================================================
-- 3. TABLE TICKETS
-- Satu event dapat mempunyai beberapa jenis tiket
-- ============================================================
CREATE TABLE tickets (
    id_ticket INT AUTO_INCREMENT PRIMARY KEY,
    id_event INT NOT NULL,
    nama_tiket VARCHAR(50) NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    stok INT NOT NULL DEFAULT 0,
    FOREIGN KEY (id_event) REFERENCES events(id_event)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

INSERT INTO tickets (id_event, nama_tiket, harga, stok) VALUES
(1, 'VIP', 500000, 50),
(1, 'Regular', 250000, 100),
(1, 'Festival', 150000, 200);

-- ============================================================
-- 4. TABLE TRANSACTIONS
-- Header transaksi: siapa yang melakukan transaksi,
-- kapan transaksi terjadi, dan total harga.
-- ============================================================
CREATE TABLE transactions (
    id_transaction INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    waktu_transaksi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_harga DECIMAL(12,2) DEFAULT 0,
    FOREIGN KEY (id_user) REFERENCES users(id_user)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

-- ============================================================
-- 5. TABLE TRANSACTION_DETAILS
-- Detail tiket yang dibeli dalam sebuah transaksi.
-- ============================================================
CREATE TABLE transaction_details (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_transaction INT NOT NULL,
    id_ticket INT NOT NULL,
    jumlah INT NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (id_transaction) REFERENCES transactions(id_transaction)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    FOREIGN KEY (id_ticket) REFERENCES tickets(id_ticket)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

-- ============================================================
-- CONTOH TRANSAKSI
-- Abyan membeli 2 tiket VIP
-- ============================================================
INSERT INTO transactions (id_user, total_harga)
VALUES (1, 1000000);

INSERT INTO transaction_details
(id_transaction, id_ticket, jumlah, harga, subtotal)
VALUES
(1, 1, 2, 500000, 1000000);

-- Kurangi stok setelah penjualan
UPDATE tickets
SET stok = stok - 2
WHERE id_ticket = 1;

-- ============================================================
-- CONTOH CRUD
-- ============================================================

-- CREATE / INSERT
INSERT INTO events
(nama_event, lokasi, tanggal_event, jam_mulai, jam_selesai)
VALUES
('Festival Sekolah',
 'Aula Sekolah',
 '2026-11-01',
 '08:00:00',
 '12:00:00');

-- READ / SELECT
SELECT * FROM events;

SELECT *
FROM events
WHERE id_event = 1;

-- UPDATE
UPDATE events
SET lokasi = 'Grand City Surabaya'
WHERE id_event = 1;

-- DELETE
-- Hapus hanya jika event tidak lagi dibutuhkan.
-- DELETE FROM events WHERE id_event = 2;

-- ============================================================
-- JOIN
-- Menampilkan tiket beserta nama event
-- ============================================================
SELECT
    tickets.id_ticket,
    tickets.nama_tiket,
    tickets.harga,
    tickets.stok,
    events.nama_event
FROM tickets
JOIN events
    ON tickets.id_event = events.id_event;

-- ============================================================
-- RECORD / RIWAYAT PENJUALAN
-- ============================================================
SELECT
    transactions.id_transaction,
    users.nama AS kasir,
    transactions.waktu_transaksi,
    transactions.total_harga
FROM transactions
JOIN users
    ON transactions.id_user = users.id_user
ORDER BY transactions.waktu_transaksi DESC;

-- ============================================================
-- DETAIL RECORD PENJUALAN
-- ============================================================
SELECT
    transactions.id_transaction,
    users.nama AS kasir,
    events.nama_event,
    tickets.nama_tiket,
    transaction_details.jumlah,
    transaction_details.harga,
    transaction_details.subtotal,
    transactions.waktu_transaksi
FROM transactions
JOIN users
    ON transactions.id_user = users.id_user
JOIN transaction_details
    ON transactions.id_transaction = transaction_details.id_transaction
JOIN tickets
    ON transaction_details.id_ticket = tickets.id_ticket
JOIN events
    ON tickets.id_event = events.id_event
ORDER BY transactions.waktu_transaksi DESC;

-- ============================================================
-- DURASI EVENT
-- Menghitung selisih jam mulai dan selesai
-- ============================================================
SELECT
    nama_event,
    jam_mulai,
    jam_selesai,
    TIMEDIFF(jam_selesai, jam_mulai) AS durasi
FROM events;

-- ============================================================
-- TOTAL PENJUALAN
-- ============================================================
SELECT SUM(total_harga) AS total_penjualan
FROM transactions;

-- ============================================================
-- JUMLAH TIKET TERJUAL
-- ============================================================
SELECT SUM(jumlah) AS tiket_terjual
FROM transaction_details;

-- ============================================================
-- JUMLAH TRANSAKSI
-- ============================================================
SELECT COUNT(*) AS jumlah_transaksi
FROM transactions;

-- ============================================================
-- PENJUALAN PER JENIS TIKET
-- ============================================================
SELECT
    tickets.nama_tiket,
    SUM(transaction_details.jumlah) AS jumlah_terjual,
    SUM(transaction_details.subtotal) AS pendapatan
FROM transaction_details
JOIN tickets
    ON transaction_details.id_ticket = tickets.id_ticket
GROUP BY tickets.id_ticket, tickets.nama_tiket;

-- ============================================================
-- FILTER TRANSAKSI BERDASARKAN TANGGAL
-- ============================================================
SELECT *
FROM transactions
WHERE DATE(waktu_transaksi) = '2026-09-21';

-- ============================================================
-- CATATAN LOGIN PHP
-- ============================================================
-- Query dasar untuk mencari username:
--
-- SELECT * FROM users WHERE username = 'abyan';
--
-- Pada PHP:
-- password_hash($password, PASSWORD_DEFAULT);
-- password_verify($password, $hash);
--
-- Jangan menyimpan password asli dalam project sungguhan.

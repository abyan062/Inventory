// ============================================================
// MODAL HELPER
// ============================================================
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('show');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('show');
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show'));
    }
});

// ============================================================
// SIDEBAR TOGGLE (MOBILE)
// ============================================================
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

// ============================================================
// LOGOUT
// ============================================================
function confirmLogout() {
    Swal.fire({
        title: 'Logout?',
        text: 'Anda akan keluar dari aplikasi.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Logout',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then(r => {
        if (r.isConfirmed) window.location.href = 'index.php?page=logout';
    });
}

// ============================================================
// JAM REALTIME
// ============================================================
function updateClock() {
    const now = new Date();
    const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    const str = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} • ${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')}`;
    const el = document.getElementById('currentDateTime');
    if (el) el.textContent = str;
}
if (document.getElementById('currentDateTime')) {
    updateClock();
    setInterval(updateClock, 1000);
}

// ============================================================
// CRUD CUSTOMER
// ============================================================
function openCustomerModal() {
    document.getElementById('customerModalTitle').textContent = 'Tambah Pelanggan';
    document.getElementById('customerId').value = '';
    document.getElementById('customerName').value = '';
    document.getElementById('customerPhone').value = '';
    document.getElementById('customerAddress').value = '';
    openModal('customerModal');
}

function editCustomer(c) {
    document.getElementById('customerModalTitle').textContent = 'Edit Pelanggan';
    document.getElementById('customerId').value = c.id;
    document.getElementById('customerName').value = c.name;
    document.getElementById('customerPhone').value = c.phone;
    document.getElementById('customerAddress').value = c.address || '';
    openModal('customerModal');
}

function deleteCustomer(id, name) {
    Swal.fire({
        title: 'Hapus pelanggan ini?',
        html: `Pelanggan <strong>${name}</strong> akan dihapus.<br>Data yang dihapus tidak dapat dikembalikan.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(r => {
        if (r.isConfirmed) {
            window.location.href = 'index.php?page=pelanggan&action=hapus&id=' + id;
        }
    });
}

// ============================================================
// CRUD PRODUCT
// ============================================================
function openProductModal() {
    document.getElementById('productModalTitle').textContent = 'Tambah Barang';
    document.getElementById('productId').value = '';
    document.getElementById('productName').value = '';
    document.getElementById('productCategory').value = '';
    document.getElementById('productPrice').value = '';
    document.getElementById('productStock').value = '';
    openModal('productModal');
}

function editProduct(p) {
    document.getElementById('productModalTitle').textContent = 'Edit Barang';
    document.getElementById('productId').value = p.id;
    document.getElementById('productName').value = p.name;
    document.getElementById('productCategory').value = p.category;
    document.getElementById('productPrice').value = p.price_per_day;
    document.getElementById('productStock').value = p.stock;
    openModal('productModal');
}

function deleteProduct(id, name) {
    Swal.fire({
        title: 'Hapus barang ini?',
        html: `Barang <strong>${name}</strong> akan dihapus.<br>Data yang dihapus tidak dapat dikembalikan.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(r => {
        if (r.isConfirmed) {
            window.location.href = 'index.php?page=barang&action=hapus&id=' + id;
        }
    });
}

// ============================================================
// TRANSAKSI — KALKULASI REALTIME
// ============================================================
function rupiah(n) {
    return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

// Logika diskon: jika qty >= 10 → 8 pertama harga normal, sisanya setengah
function hitungHargaJS(pricePerDay, quantity, duration) {
    const subtotal = pricePerDay * quantity * duration;
    let discount = 0;
    let total = subtotal;
    let info = '';

    if (quantity >= 10) {
        const normalQty = 8;
        const discountQty = quantity - 8;
        const discountPrice = pricePerDay / 2;

        const subtotalNormal = normalQty * pricePerDay * duration;
        const subtotalDiskon = discountQty * discountPrice * duration;

        total = subtotalNormal + subtotalDiskon;
        discount = subtotal - total;

        info = `Diskon diterapkan: ${normalQty} × ${rupiah(pricePerDay)} + ${discountQty} × ${rupiah(discountPrice)} × ${duration} hari`;
    } else {
        info = `Harga normal: ${quantity} × ${rupiah(pricePerDay)} × ${duration} hari`;
    }

    return { subtotal, discount, total, info };
}

function updateHarga() {
    const productSelect = document.getElementById('productSelect');
    const quantityInput = document.getElementById('quantityInput');
    const durationInput = document.getElementById('durationInput');
    if (!productSelect || !quantityInput || !durationInput) return;

    const option = productSelect.options[productSelect.selectedIndex];
    const price = option ? parseInt(option.dataset.price) || 0 : 0;
    const stock = option ? parseInt(option.dataset.stock) || 0 : 0;
    const quantity = parseInt(quantityInput.value) || 0;
    const duration = parseInt(durationInput.value) || 0;

    if (price > 0 && quantity > 0 && duration > 0) {
        // Validasi stok realtime
        if (quantity > stock) {
            quantityInput.setCustomValidity('Stok tidak cukup');
        } else {
            quantityInput.setCustomValidity('');
        }

        const calc = hitungHargaJS(price, quantity, duration);
        document.getElementById('calcPrice').textContent = rupiah(price) + ' / hari';
        document.getElementById('calcQty').textContent = quantity + ' × ' + duration + ' hari';
        document.getElementById('calcSubtotal').textContent = rupiah(calc.subtotal);
        document.getElementById('calcDiscount').textContent = '- ' + rupiah(calc.discount);
        document.getElementById('calcTotal').textContent = rupiah(calc.total);
        document.getElementById('calcInfo').textContent = calc.info;
    } else {
        document.getElementById('calcPrice').textContent = 'Rp 0';
        document.getElementById('calcQty').textContent = '0 × 0';
        document.getElementById('calcSubtotal').textContent = 'Rp 0';
        document.getElementById('calcDiscount').textContent = '- Rp 0';
        document.getElementById('calcTotal').textContent = 'Rp 0';
        document.getElementById('calcInfo').textContent = '';
    }
    updateKembalian();
}

function updateKembalian() {
    const productSelect = document.getElementById('productSelect');
    const quantityInput = document.getElementById('quantityInput');
    const durationInput = document.getElementById('durationInput');
    const paymentInput = document.getElementById('paymentInput');
    if (!productSelect || !quantityInput || !durationInput || !paymentInput) return;

    const option = productSelect.options[productSelect.selectedIndex];
    const price = option ? parseInt(option.dataset.price) || 0 : 0;
    const quantity = parseInt(quantityInput.value) || 0;
    const duration = parseInt(durationInput.value) || 0;
    const payment = parseInt(paymentInput.value) || 0;

    const calc = hitungHargaJS(price, quantity, duration);
    const change = payment - calc.total;

    const el = document.getElementById('calcChange');
    if (change < 0) {
        el.textContent = 'Kurang ' + rupiah(Math.abs(change));
        el.style.color = '#dc2626';
    } else {
        el.textContent = rupiah(change);
        el.style.color = '#16a34a';
    }
}

function resetForm() {
    const form = document.getElementById('transaksiForm');
    if (form) form.reset();
    updateHarga();
    updateKembalian();
}

function konfirmasiTransaksi() {
    const customerSelect = document.getElementById('customerSelect');
    const productSelect = document.getElementById('productSelect');
    const quantityInput = document.getElementById('quantityInput');
    const durationInput = document.getElementById('durationInput');
    const paymentInput = document.getElementById('paymentInput');

    if (!customerSelect.value) {
        Swal.fire('Validasi', 'Pilih pelanggan terlebih dahulu', 'warning');
        return;
    }
    if (!productSelect.value) {
        Swal.fire('Validasi', 'Pilih barang terlebih dahulu', 'warning');
        return;
    }

    const option = productSelect.options[productSelect.selectedIndex];
    const price = parseInt(option.dataset.price) || 0;
    const stock = parseInt(option.dataset.stock) || 0;
    const quantity = parseInt(quantityInput.value) || 0;
    const duration = parseInt(durationInput.value) || 0;
    const payment = parseInt(paymentInput.value) || 0;

    if (quantity <= 0) {
        Swal.fire('Validasi', 'Jumlah harus lebih dari 0', 'warning');
        return;
    }
    if (duration <= 0) {
        Swal.fire('Validasi', 'Durasi harus lebih dari 0', 'warning');
        return;
    }
    if (quantity > stock) {
        Swal.fire('Stok Tidak Cukup', `Stok tersedia hanya ${stock}`, 'error');
        return;
    }

    const calc = hitungHargaJS(price, quantity, duration);
    const change = payment - calc.total;

    if (payment < calc.total) {
        Swal.fire('Pembayaran Kurang', `Total: ${rupiah(calc.total)}<br>Dibayar: ${rupiah(payment)}<br><strong>Kurang: ${rupiah(Math.abs(change))}</strong>`, 'error');
        return;
    }

    Swal.fire({
        title: 'Konfirmasi Transaksi',
        html: `
            <div style="text-align:left;font-size:14px;line-height:2">
                <div style="display:flex;justify-content:space-between"><span>Subtotal</span><strong>${rupiah(calc.subtotal)}</strong></div>
                <div style="display:flex;justify-content:space-between;color:#16a34a"><span>Diskon</span><strong>- ${rupiah(calc.discount)}</strong></div>
                <div style="display:flex;justify-content:space-between;border-top:1px solid #e2e8f0;padding-top:8px;margin-top:8px"><span><strong>Total</strong></span><strong>${rupiah(calc.total)}</strong></div>
                <div style="display:flex;justify-content:space-between"><span>Dibayar</span><strong>${rupiah(payment)}</strong></div>
                <div style="display:flex;justify-content:space-between;color:#16a34a"><span>Kembalian</span><strong>${rupiah(change)}</strong></div>
            </div>
            <p style="margin-top:12px;color:#64748b">Proses transaksi ini?</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Proses Transaksi',
        cancelButtonText: 'Batalkan',
        confirmButtonColor: '#2563eb'
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('transaksiForm').submit();
        }
    });
}

// ============================================================
// RIWAYAT TRANSAKSI
// ============================================================
function showDetail(t) {
    const dt = new Date(t.transaction_date);
    const dateStr = dt.toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });

    Swal.fire({
        title: 'Detail Transaksi #TRX' + String(t.id).padStart(3, '0'),
        html: `
            <div style="text-align:left;font-size:13px;line-height:1.8">
                <div style="padding-bottom:8px;margin-bottom:8px;border-bottom:1px solid #e2e8f0">
                    <div><strong>Pelanggan:</strong> ${t.customer_name}</div>
                    <div><strong>Barang:</strong> ${t.product_name}</div>
                    <div><strong>Jumlah:</strong> ${t.quantity}</div>
                    <div><strong>Durasi:</strong> ${t.duration} hari</div>
                    <div><strong>Tanggal:</strong> ${dateStr}</div>
                </div>
                <div style="display:flex;justify-content:space-between"><span>Subtotal</span><strong>Rp ${parseInt(t.subtotal).toLocaleString('id-ID')}</strong></div>
                <div style="display:flex;justify-content:space-between;color:#16a34a"><span>Diskon</span><strong>- Rp ${parseInt(t.discount).toLocaleString('id-ID')}</strong></div>
                <div style="display:flex;justify-content:space-between;border-top:1px dashed #e2e8f0;margin-top:6px;padding-top:6px;font-size:15px"><span><strong>Total</strong></span><strong>Rp ${parseInt(t.total).toLocaleString('id-ID')}</strong></div>
                <div style="display:flex;justify-content:space-between;margin-top:6px"><span>Dibayar</span><span>Rp ${parseInt(t.payment).toLocaleString('id-ID')}</span></div>
                <div style="display:flex;justify-content:space-between"><span>Kembalian</span><span>Rp ${parseInt(t.change_amount).toLocaleString('id-ID')}</span></div>
                <div style="display:flex;justify-content:space-between;margin-top:8px"><span>Status</span><strong>${t.status}</strong></div>
            </div>
        `,
        width: 500,
        confirmButtonText: 'Tutup'
    });
}

function changeStatus(id, status) {
    window.location.href = `index.php?page=riwayat&action=status&id=${id}&status=${encodeURIComponent(status)}`;
}

function deleteTransaction(id) {
    Swal.fire({
        title: 'Hapus transaksi ini?',
        text: 'Data transaksi akan dihapus permanen. Stok akan dikembalikan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(r => {
        if (r.isConfirmed) {
            window.location.href = 'index.php?page=riwayat&action=hapus&id=' + id;
        }
    });
}

console.log('RentalKu siap digunakan.');
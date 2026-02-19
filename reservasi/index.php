<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
requireAuth();
requireModuleAccess('reservasi');
require_once '../config/database.php';

// Pagination settings
$items_per_page = 6; // 2 rows x 3 cards per row
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Handle status filter
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$valid_statuses = ['pending', 'confirmed', 'checkin', 'checkout', 'cancelled'];

// Build query with pagination
if (!empty($filter_status) && in_array($filter_status, $valid_statuses)) {
    // Count total for pagination
    $count_stmt = mysqli_prepare($connection, "SELECT COUNT(*) as total FROM `reservasi` WHERE `status` = ?");
    mysqli_stmt_bind_param($count_stmt, "s", $filter_status);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt));
    $total_items = $count_result['total'];
    mysqli_stmt_close($count_stmt);
    
    // Get paginated data
    $stmt = mysqli_prepare($connection, "SELECT * FROM `reservasi` WHERE `status` = ? ORDER BY id DESC LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($stmt, "sii", $filter_status, $items_per_page, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    // Count total for pagination
    $count_result = mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as total FROM `reservasi`"));
    $total_items = $count_result['total'];
    
    // Get paginated data
    $result = mysqli_query($connection, "SELECT * FROM `reservasi` ORDER BY id DESC LIMIT $items_per_page OFFSET $offset");
    $filter_status = ''; // Reset to show all
}

// Calculate total pages
$total_pages = ceil($total_items / $items_per_page);


// Status badge color mapping based on UI/UX requirements
function getStatusBadgeClass($status) {
    return match($status) {
        'checkin' => 'status-active',      // Green - Active guest
        'checkout' => 'status-completed',  // Gray - History
        'confirmed' => 'status-upcoming',  // Yellow/Gold - Future booking
        'cancelled' => 'status-cancelled', // Red - Cancelled
        default => 'status-pending'        // Yellow - Pending
    };
}

function getStatusLabel($status) {
    return match($status) {
        'checkin' => 'CHECK-IN',
        'checkout' => 'SELESAI',
        'confirmed' => 'DIKONFIRMASI',
        'cancelled' => 'DIBATALKAN',
        default => 'PENDING'
    };
}
?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<style>
/* Booking Card Styles - Refactored */
.booking-card {
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    background: #FFFFFF;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.booking-card .card-header-custom {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 16px;
    background: #FAFBFC;
    border-bottom: 1px solid #E2E8F0;
    gap: 12px;
}

.booking-card .guest-info {
    flex: 1;
    min-width: 0;
}

.booking-card .guest-name {
    font-family: 'Playfair Display', serif;
    font-size: 1.25rem;
    font-weight: 700;
    color: #1A202C;
    margin: 0 0 4px 0;
    line-height: 1.3;
}

.booking-card .booking-id {
    font-size: 0.75rem;
    color: #718096;
    font-family: 'Inter', sans-serif;
    letter-spacing: 0.3px;
}

.booking-card .header-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    flex-shrink: 0;
}

.booking-card .room-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: #1A202C;
    color: #FFFFFF;
    font-size: 0.7rem;
    font-weight: 600;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.booking-card .room-badge i {
    font-size: 0.8rem;
}

.booking-card .status-badge-custom {
    display: inline-block;
    padding: 4px 10px;
    font-size: 0.65rem;
    font-weight: 700;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.booking-card .status-active {
    background: #C6F6D5;
    color: #22543D;
}

.booking-card .status-completed {
    background: #E2E8F0;
    color: #4A5568;
}

.booking-card .status-upcoming {
    background: #FEFCBF;
    color: #744210;
}

.booking-card .status-pending {
    background: rgba(197, 160, 89, 0.2);
    color: #92702D;
}

.booking-card .status-cancelled {
    background: #FED7D7;
    color: #822727;
}

.booking-card .card-body-custom {
    padding: 16px;
    flex: 1;
}

.booking-card .info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.booking-card .info-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.booking-card .info-item {}

.booking-card .info-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}

.booking-card .info-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1E293B;
}

.booking-card .info-value.amount {
    font-family: 'Inter', sans-serif;
    color: #1A202C;
}

.booking-card .payment-status {
    margin-top: 4px;
}

.booking-card .badge-lunas {
    display: inline-block;
    padding: 3px 8px;
    background: #C6F6D5;
    color: #22543D;
    font-size: 0.65rem;
    font-weight: 700;
    border-radius: 4px;
    text-transform: uppercase;
}

.booking-card .amount-remaining {
    font-weight: 700;
    color: #C53030;
    font-size: 0.9rem;
}

.booking-card .card-footer-custom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #FAFBFC;
    border-top: 1px solid #E2E8F0;
    gap: 12px;
}

.booking-card .phone-info {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    color: #64748B;
}

.booking-card .phone-info i {
    color: #94A3B8;
}

.booking-card .btn-detail {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #1A202C;
    color: #FFFFFF;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 6px;
    text-decoration: none;
    transition: background 0.2s;
}

.booking-card .btn-detail:hover {
    background: #2D3748;
    color: #FFFFFF;
}

.booking-card .action-buttons {
    display: flex;
    gap: 8px;
}

/* Custom Pagination Styling - Match Theme */
.pagination {
    gap: 4px;
}

.pagination .page-link {
    border: 1px solid #E2E8F0;
    color: #4A5568;
    background: #FFFFFF;
    font-size: 0.875rem;
    font-weight: 500;
    padding: 8px 14px;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.pagination .page-link:hover {
    background: #F7FAFC;
    border-color: #CBD5E0;
    color: #1A202C;
}

.pagination .page-item.active .page-link {
    background: #1A202C;
    border-color: #1A202C;
    color: #FFFFFF;
}

.pagination .page-item.disabled .page-link {
    background: #F7FAFC;
    border-color: #E2E8F0;
    color: #A0AEC0;
}

.pagination .page-item:first-child .page-link,
.pagination .page-item:last-child .page-link {
    font-weight: 600;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <h2 class="mb-0">Daftar Reservasi</h2>
        
        <?php if (isset($_GET['cancelled'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-0 py-2 px-3" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            Reservasi berhasil dibatalkan.
            <?php if (isset($_GET['refund']) && $_GET['refund'] > 0): ?>
            DP sebesar <strong>Rp <?= number_format((float)$_GET['refund'], 0, ',', '.') ?></strong> dikembalikan.
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Status Filter Dropdown -->
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="border-color: #E2E8F0; background: white;">
                <i class="bi bi-funnel"></i>
                <span><?php
                    switch($filter_status) {
                        case 'pending': echo 'Pending'; break;
                        case 'confirmed': echo 'Dikonfirmasi'; break;
                        case 'checkin': echo 'Check-In'; break;
                        case 'checkout': echo 'Selesai'; break;
                        case 'cancelled': echo 'Dibatalkan'; break;
                        default: echo 'Semua Status';
                    }
                ?></span>
            </button>
            <ul class="dropdown-menu" aria-labelledby="filterDropdown" style="min-width: 180px;">
                <li><a class="dropdown-item <?= empty($filter_status) ? 'active' : '' ?>" href="index.php">
                    <i class="bi bi-list me-2"></i>Semua Status
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item <?= $filter_status === 'pending' ? 'active' : '' ?>" href="index.php?status=pending">
                    <span class="badge me-2" style="background: rgba(197, 160, 89, 0.2); color: #92702D;">●</span>Pending
                </a></li>
                <li><a class="dropdown-item <?= $filter_status === 'confirmed' ? 'active' : '' ?>" href="index.php?status=confirmed">
                    <span class="badge me-2" style="background: #FEFCBF; color: #744210;">●</span>Dikonfirmasi
                </a></li>
                <li><a class="dropdown-item <?= $filter_status === 'checkin' ? 'active' : '' ?>" href="index.php?status=checkin">
                    <span class="badge me-2" style="background: #C6F6D5; color: #22543D;">●</span>Check-In
                </a></li>
                <li><a class="dropdown-item <?= $filter_status === 'checkout' ? 'active' : '' ?>" href="index.php?status=checkout">
                    <span class="badge me-2" style="background: #E2E8F0; color: #4A5568;">●</span>Selesai
                </a></li>
                <li><a class="dropdown-item <?= $filter_status === 'cancelled' ? 'active' : '' ?>" href="index.php?status=cancelled">
                    <span class="badge me-2" style="background: #FED7D7; color: #822727;">●</span>Dibatalkan
                </a></li>
            </ul>
        </div>
        
        <?php if (!empty($filter_status)): ?>
        <a href="index.php" class="btn btn-sm btn-light" style="border: 1px solid #E2E8F0;">
            <i class="bi bi-x-lg me-1"></i>Reset Filter
        </a>
        <?php endif; ?>
    </div>
    
    <a href="add.php" class="btn btn-primary">+ Tambah Reservasi</a>
</div>

<?php if (mysqli_num_rows($result) > 0): ?>

<div class="row">
<?php 
$booking_index = 0;
while ($row = mysqli_fetch_assoc($result)): ?>
    <?php 
    $status_class = getStatusBadgeClass($row['status']);
    $status_label = getStatusLabel($row['status']);
    $is_lunas = ($row['sisa_bayar'] == 0 && $row['total_bayar'] > 0);
    
    // Get room numbers for this reservasi
    $kamar_query = mysqli_query($connection, "SELECT k.nomor_kamar FROM reservasi_detail bd JOIN kamar k ON bd.kamar_id = k.id WHERE bd.reservasi_id = " . $row['id']);
    $kamar_list = [];
    while ($kamar = mysqli_fetch_assoc($kamar_query)) {
        $kamar_list[] = $kamar['nomor_kamar'];
    }
    ?>
    <div class="col-12 col-md-6 col-xl-4 mb-4">
        <div class="booking-card">
            <!-- Header: Guest Name (Primary) + Badges -->
            <div class="card-header-custom">
                <div class="guest-info">
                    <h3 class="guest-name"><?= htmlspecialchars($row['nama_tamu']) ?></h3>
                    <div class="booking-id"><?= htmlspecialchars($row['no_reservasi']) ?></div>
                </div>
                <div class="header-badges">
                    <?php if (!empty($kamar_list)): ?>
                    <span class="room-badge">
                        <i class="bi bi-door-closed"></i>
                        <?= implode(', ', $kamar_list) ?>
                    </span>
                    <?php endif; ?>
                    <span class="status-badge-custom <?= $status_class ?>"><?= $status_label ?></span>
                </div>
            </div>
            
            <!-- Body: 2-Column Grid -->
            <div class="card-body-custom">
                <div class="info-grid">
                    <!-- Left Column: Dates -->
                    <div class="info-section">
                        <div class="info-item">
                            <div class="info-label">Check-In</div>
                            <div class="info-value"><?= date('d M Y', strtotime($row['tanggal_checkin'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Check-Out</div>
                            <div class="info-value"><?= date('d M Y', strtotime($row['tanggal_checkout'])) ?></div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Payment -->
                    <div class="info-section">
                        <div class="info-item">
                            <div class="info-label">Total Bayar</div>
                            <div class="info-value amount">Rp <?= number_format((float)$row['total_bayar'], 0, ',', '.') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status Pembayaran</div>
                            <div class="payment-status">
                                <?php if ($is_lunas): ?>
                                    <span class="badge-lunas">LUNAS</span>
                                <?php elseif ($row['sisa_bayar'] > 0): ?>
                                    <span class="amount-remaining">Rp <?= number_format((float)$row['sisa_bayar'], 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer: Phone + Actions -->
            <div class="card-footer-custom">
                <?php if ($row['no_telepon']): ?>
                <div class="phone-info">
                    <i class="bi bi-telephone-fill"></i>
                    <span><?= htmlspecialchars($row['no_telepon']) ?></span>
                </div>
                <?php else: ?>
                <div></div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <?php if ($row['total_bayar'] == 0 && $row['status'] === 'pending'): ?>
                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteBookingModal<?= $booking_index ?>">Hapus</button>
                    <?php endif; ?>
                    <?php if ($row['status'] === 'confirmed'): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelBookingModal<?= $booking_index ?>">
                        <i class="bi bi-x-circle me-1"></i>Batal
                    </button>
                    <?php endif; ?>
                    <a href="detail.php?id=<?= $row['id'] ?>" class="btn-detail">
                        <i class="bi bi-eye"></i>
                        Detail
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php $booking_index++; endwhile; ?>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<nav aria-label="Page navigation" class="mt-4">
    <ul class="pagination justify-content-center">
        <!-- Previous Button -->
        <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($filter_status) ? '&status='.$filter_status : '' ?>" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
        
        <?php 
        // Calculate range of pages to show
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        // Always show first page
        if ($start_page > 1): ?>
            <li class="page-item"><a class="page-link" href="?page=1<?= !empty($filter_status) ? '&status='.$filter_status : '' ?>">1</a></li>
            <?php if ($start_page > 2): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Page Numbers -->
        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?><?= !empty($filter_status) ? '&status='.$filter_status : '' ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        
        <?php // Always show last page
        if ($end_page < $total_pages): ?>
            <?php if ($end_page < $total_pages - 1): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
            <li class="page-item"><a class="page-link" href="?page=<?= $total_pages ?><?= !empty($filter_status) ? '&status='.$filter_status : '' ?>"><?= $total_pages ?></a></li>
        <?php endif; ?>
        
        <!-- Next Button -->
        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($filter_status) ? '&status='.$filter_status : '' ?>" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    </ul>
    <div class="text-center text-muted small">
        Menampilkan <?= min($offset + 1, $total_items) ?>-<?= min($offset + $items_per_page, $total_items) ?> dari <?= $total_items ?> booking
    </div>
</nav>
<?php endif; ?>

<?php else: ?>
<div class="alert alert-info">
    <h5>Belum ada data reservasi</h5>
    <p class="mb-0">Klik tombol "+ Tambah Reservasi" untuk membuat reservasi baru.</p>
</div>
<?php endif; ?>

<!-- Delete Booking Confirmation Modals -->
<?php 
mysqli_data_seek($result, 0);
$modal_index = 0;
while ($modal_row = mysqli_fetch_assoc($result)): 
    if ($modal_row['total_bayar'] == 0 && $modal_row['status'] === 'pending'):
?>
<div class="modal fade" id="deleteBookingModal<?= $modal_index ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="mb-4">
                    <div style="width: 80px; height: 80px; background-color: #f8d7da; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="bi bi-exclamation-triangle" style="font-size: 36px; color: #842029;"></i>
                    </div>
                </div>
                <h4 class="fw-bold mb-3" style="color: #1A202C;">Hapus Reservasi?</h4>
                <p class="text-muted mb-2">Apakah Anda yakin ingin menghapus reservasi ini?</p>
                <div class="alert" style="background: #F7FAFC; border: 1px solid #E2E8F0;" >
                    <div class="fw-bold" style="color: #1A202C; font-family: 'Playfair Display', serif;"><?= htmlspecialchars($modal_row['nama_tamu']) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($modal_row['no_reservasi']) ?></small>
                </div>
                <p class="text-danger small mb-0"><i class="bi bi-info-circle me-1"></i>Tindakan ini tidak dapat dibatalkan</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                <a href="delete.php?id=<?= $modal_row['id'] ?>" class="btn btn-danger px-4">
                    <i class="bi bi-trash me-1"></i>Ya, Hapus
                </a>
            </div>
        </div>
    </div>
</div>
<?php 
    endif;
    $modal_index++;
endwhile; 
?>

<!-- Cancel Booking Confirmation Modals -->
<?php 
mysqli_data_seek($result, 0);
$cancel_index = 0;
while ($cancel_row = mysqli_fetch_assoc($result)): 
    if ($cancel_row['status'] === 'confirmed'):
        $has_dp = $cancel_row['dp_bayar'] > 0;
?>
<div class="modal fade" id="cancelBookingModal<?= $cancel_index ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="mb-4">
                    <div style="width: 80px; height: 80px; background-color: #FED7D7; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="bi bi-x-circle" style="font-size: 36px; color: #C53030;"></i>
                    </div>
                </div>
                <h4 class="fw-bold mb-3" style="color: #1A202C;">Batalkan Reservasi?</h4>
                <p class="text-muted mb-2">Apakah Anda yakin ingin membatalkan reservasi ini?</p>
                <div class="alert" style="background: #F7FAFC; border: 1px solid #E2E8F0;">
                    <div class="fw-bold" style="color: #1A202C; font-family: 'Playfair Display', serif;"><?= htmlspecialchars($cancel_row['nama_tamu']) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($cancel_row['no_reservasi']) ?></small>
                </div>
                <?php if ($has_dp): ?>
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    DP sebesar <strong>Rp <?= number_format((float)$cancel_row['dp_bayar'], 0, ',', '.') ?></strong> akan dikembalikan.
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Tutup</button>
                <a href="cancel.php?id=<?= $cancel_row['id'] ?>" class="btn btn-danger px-4">
                    <i class="bi bi-x-circle me-1"></i>Ya, Batalkan
                </a>
            </div>
        </div>
    </div>
</div>
<?php 
    endif;
    $cancel_index++;
endwhile; 
?>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>


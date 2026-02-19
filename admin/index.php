<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // atur nilai menjadi 1 jika di publish ke real public server
ini_set('session.use_strict_mode', 1);
session_start();
require_once '../lib/auth.php';
require_once '../lib/functions.php';
require_once '../config/database.php';
requireAuth();
if (getUserRole() !== 'admin') {
    redirect('../login.php');
}

// Calculate Dashboard Stats
// 1. Pendapatan Bulan Ini (Current Month Revenue - LUNAS only)
$current_month = date('m');
$current_year = date('Y');
$query_income = mysqli_query($connection, "SELECT SUM(total_bayar) as total FROM reservasi WHERE sisa_bayar = 0 AND MONTH(tanggal_checkout) = $current_month AND YEAR(tanggal_checkout) = $current_year");
$monthly_income = mysqli_fetch_assoc($query_income)['total'] ?? 0;

// 2. Tamu Aktif (Checkin / Confirmed)
$query_active = mysqli_query($connection, "SELECT COUNT(*) as count FROM reservasi WHERE status IN ('checkin', 'confirmed')");
$active_guests = mysqli_fetch_assoc($query_active)['count'] ?? 0;

// 3. Kamar Terisi & Occupancy Rate
$query_occupied = mysqli_query($connection, "SELECT COUNT(DISTINCT kamar_id) as count FROM reservasi_detail bd JOIN reservasi b ON bd.reservasi_id = b.id WHERE b.status IN ('checkin', 'confirmed')");
$occupied_rooms = mysqli_fetch_assoc($query_occupied)['count'] ?? 0;

// Total Rooms
$query_total_rooms = mysqli_query($connection, "SELECT COUNT(*) as count FROM kamar");
$total_rooms = mysqli_fetch_assoc($query_total_rooms)['count'] ?? 1; // Avoid division by zero
$occupancy_rate = ($total_rooms > 0) ? round(($occupied_rooms / $total_rooms) * 100) : 0;

// 4. Booking Pending
$query_pending = mysqli_query($connection, "SELECT COUNT(*) as count FROM reservasi WHERE status = 'pending'");
$pending_bookings = mysqli_fetch_assoc($query_pending)['count'] ?? 0;

// 5. Today's Operations - Check-In Today
$today = date('Y-m-d');
$query_checkin_today = mysqli_query($connection, "
    SELECT b.id, b.nama_tamu, GROUP_CONCAT(k.nomor_kamar SEPARATOR ', ') as rooms 
    FROM reservasi b 
    LEFT JOIN reservasi_detail bd ON b.id = bd.reservasi_id 
    LEFT JOIN kamar k ON bd.kamar_id = k.id 
    WHERE b.tanggal_checkin = '$today' AND b.status IN ('confirmed', 'pending')
    GROUP BY b.id
");
$checkin_today = [];
while ($row = mysqli_fetch_assoc($query_checkin_today)) {
    $checkin_today[] = $row;
}

// 6. Today's Operations - Check-Out Today
$query_checkout_today = mysqli_query($connection, "
    SELECT b.id, b.nama_tamu, GROUP_CONCAT(k.nomor_kamar SEPARATOR ', ') as rooms 
    FROM reservasi b 
    LEFT JOIN reservasi_detail bd ON b.id = bd.reservasi_id 
    LEFT JOIN kamar k ON bd.kamar_id = k.id 
    WHERE b.tanggal_checkout = '$today' AND b.status = 'checkin'
    GROUP BY b.id
");
$checkout_today = [];
while ($row = mysqli_fetch_assoc($query_checkout_today)) {
    $checkout_today[] = $row;
}

// Indonesian month name
$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$current_month_name = $month_names[(int)$current_month] . ' ' . $current_year;

?>
<?php include '../views/'.$THEME.'/header.php'; ?>
<?php include '../views/'.$THEME.'/sidebar.php'; ?>
<?php include '../views/'.$THEME.'/topnav.php'; ?>
<?php include '../views/'.$THEME.'/upper_block.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Dashboard Admin</h2>
    <div class="text-muted"><?= date('l, d F Y') ?></div>
</div>

<div class="row">
    <!-- Pendapatan Bulan Ini -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="dashboard-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted fw-bold">Pendapatan Bulan Ini</span>
                <div class="card-icon bg-success bg-opacity-10 text-success p-2 rounded">
                    <i class="bi bi-cash-stack fs-4"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1">Rp <?= number_format($monthly_income, 0, ',', '.') ?></h3>
            <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= $current_month_name ?></small>
        </div>
    </div>

    <!-- Tamu Aktif -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="dashboard-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted fw-bold">Tamu Aktif</span>
                <div class="card-icon bg-primary bg-opacity-10 text-primary p-2 rounded">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-0"><?= $active_guests ?></h3>
            <small class="text-muted">Tamu Check-In / Confirmed</small>
        </div>
    </div>

    <!-- Kamar Terisi with Occupancy Progress Bar -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="dashboard-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted fw-bold">Kamar Terisi</span>
                <div class="card-icon bg-info bg-opacity-10 text-info p-2 rounded">
                    <i class="bi bi-door-closed-fill fs-4"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1"><?= $occupied_rooms ?> <small class="fs-6 text-muted">/ <?= $total_rooms ?></small></h3>
            <div class="progress mb-2" style="height: 8px;">
                <div class="progress-bar bg-info" role="progressbar" style="width: <?= $occupancy_rate ?>%;" aria-valuenow="<?= $occupancy_rate ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <small class="text-info fw-semibold">Occupancy: <?= $occupancy_rate ?>%</small>
        </div>
    </div>

    <!-- Booking Pending -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="dashboard-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted fw-bold">Reservasi Pending</span>
                <div class="card-icon bg-warning bg-opacity-10 text-warning p-2 rounded">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-0"><?= $pending_bookings ?></h3>
            <small class="text-warning">Perlu Konfirmasi</small>
        </div>
    </div>
</div>

<!-- Operasional Hari Ini Section -->
<div class="row mt-2">
    <div class="col-12">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Operasional Hari Ini</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Check-In Today -->
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-primary mb-3"><i class="bi bi-box-arrow-in-right me-2"></i>Check-In Hari Ini</h6>
                            <?php if (count($checkin_today) > 0): ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($checkin_today as $ci): ?>
                                <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <span class="fw-medium"><?= htmlspecialchars($ci['nama_tamu']) ?></span>
                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($ci['rooms'] ?: 'No room') ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted mb-0 fst-italic"><i class="bi bi-info-circle me-1"></i>Tidak ada jadwal check-in hari ini</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Check-Out Today -->
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="text-success mb-3"><i class="bi bi-box-arrow-right me-2"></i>Check-Out Hari Ini</h6>
                            <?php if (count($checkout_today) > 0): ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($checkout_today as $co): ?>
                                <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <span class="fw-medium"><?= htmlspecialchars($co['nama_tamu']) ?></span>
                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($co['rooms'] ?: 'No room') ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted mb-0 fst-italic"><i class="bi bi-info-circle me-1"></i>Tidak ada jadwal check-out hari ini</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Reservasi Terbaru</h5>
                <a href="../reservasi/index.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>No Reservasi</th>
                                <th>Nama Tamu</th>
                                <th>Check-In</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $latest = mysqli_query($connection, "SELECT * FROM reservasi ORDER BY id DESC LIMIT 5");
                            if (mysqli_num_rows($latest) > 0):
                                while($row = mysqli_fetch_assoc($latest)):
                                    // Refined badge colors: Confirmed=Blue, Checkin=Green, Cancelled=Red
                                    $badge = match($row['status']) {
                                        'checkout' => 'secondary',
                                        'checkin' => 'success',
                                        'confirmed' => 'primary',
                                        'cancelled' => 'danger',
                                        default => 'warning'
                                    };
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['no_reservasi']) ?></td>
                                <td><?= htmlspecialchars($row['nama_tamu']) ?></td>
                                <td><?= date('d M Y', strtotime($row['tanggal_checkin'])) ?></td>
                                <td><span class="badge bg-<?= $badge ?>"><?= strtoupper($row['status']) ?></span></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="4" class="text-center py-3">Belum ada data reservasi</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../views/'.$THEME.'/lower_block.php'; ?>
<?php include '../views/'.$THEME.'/footer.php'; ?>


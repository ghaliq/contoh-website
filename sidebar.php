<?php
$dashboard_active = ($currentPage === 'dashboard') ? 'active' : '';
$patient_active = ($currentPage === 'patient') ? 'active' : '';
$user_management_active = ($currentPage === 'user_management') ? 'active' : '';
$stats_active = ($currentPage === 'stats') ? 'active' : '';
$profile_active = ($currentPage === 'profile') ? 'active' : '';

$dashboard_link = ($_SESSION['role'] === 'admin') ? 'dasboard-admin.php' : 'index.php';

if ($_SESSION['role'] === 'admin') {
    $sidebar_title = 'Admin Panel';
    $sidebar_icon = 'fas fa-user-shield';
} else {
    $sidebar_title = 'User Panel';
    $sidebar_icon = 'fas fa-chart-bar';
}
?>
<div id="sidebar">
    <div class="sidebar-header">
        <h2><i class="<?php echo $sidebar_icon; ?>"></i> <?php echo $sidebar_title; ?></h2>
        <small>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></small>
    </div>
    <ul class="components">
        <li>
            <a href="<?php echo $dashboard_link; ?>" class="sidebar-link <?php echo $dashboard_active; ?>" data-target="dashboard-overview">
                <i class="fas fa-tachometer-alt"></i> Dashboard Overview
            </a>
        </li>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <li>
            <a href="dasboard-admin.php#patient-data" class="sidebar-link <?php echo $patient_active; ?>" data-target="patient-data">
                <i class="fas fa-users"></i> Data Pasien
            </a>
        </li>
        <li>
            <a href="user_management.php" class="sidebar-link <?php echo $user_management_active; ?>">
                <i class="fas fa-users-cog"></i> Manajemen Pengguna
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="statistics.php" class="sidebar-link <?php echo $stats_active; ?>">
                <i class="fas fa-chart-bar"></i> Statistik Historis
            </a>
        </li>
        <li>
            <a href="profile.php" class="sidebar-link <?php echo $profile_active; ?>">
                <i class="fas fa-user-edit"></i> Kelola Profil
            </a>
        </li>
    </ul>
    <ul class="components">
        <li>
            <a href="logout.php" class="sidebar-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>
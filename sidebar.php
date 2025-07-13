<?php
// Tentukan tautan aktif berdasarkan variabel $currentPage yang akan di-include
$dashboard_active = ($currentPage === 'dashboard') ? 'active' : '';
$patient_active = ($currentPage === 'patient') ? 'active' : '';
$riskmap_active = ($currentPage === 'riskmap') ? 'active' : '';
$stats_active = ($currentPage === 'stats') ? 'active' : '';
$profile_active = ($currentPage === 'profile') ? 'active' : '';
?>
<div id="sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-user-shield"></i> Admin Panel</h2>
        <small>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></small>
    </div>
    <ul class="components">
        <li>
            <a href="dasboard-admin.php" class="sidebar-link <?php echo $dashboard_active; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard Overview
            </a>
        </li>
        <li>
            <a href="dasboard-admin.php#patient-data" class="sidebar-link <?php echo $patient_active; ?>">
                <i class="fas fa-users"></i> Data Pasien
            </a>
        </li>
        <li>
            <a href="dasboard-admin.php#dashboard-overview" class="sidebar-link <?php echo $riskmap_active; ?>">
                <i class="fas fa-map-marked-alt"></i> Peta Risiko
            </a>
        </li>
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
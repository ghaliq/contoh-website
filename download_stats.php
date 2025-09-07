<?php
include 'db.php';

// Autentikasi: Pastikan hanya user yang login yang bisa mengakses
if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak.");
}

// Ambil bulan dan tahun dari URL
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : date("n");
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date("Y");

// Buat nama file berdasarkan filter
$month_name = ($selected_month == 0) ? 'tahunan' : str_pad($selected_month, 2, '0', STR_PAD_LEFT);
$filename = "statistik_dbd_pontianak_" . $selected_year . "_" . $month_name . ".csv";

// Set header untuk download file
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Buka output stream
$output = fopen('php://output', 'w');

// Tulis header CSV
fputcsv($output, ['Kecamatan', 'Tanggal', 'Suhu (°C)', 'Kelembaban (%)', 'Tingkat Risiko']);

// Ambil data dari database
if ($selected_month == 0) {
    $historical_data_query = $conn->prepare("
        SELECT 
            k.name AS kecamatan_name,
            rdd.record_date,
            rdd.temperature,
            rdd.humidity,
            rdd.risk_level
        FROM 
            region_daily_data rdd
        JOIN 
            kecamatan k ON rdd.kecamatan_id = k.id
        WHERE 
            YEAR(rdd.record_date) = ? AND k.name != 'Pontianak Barat Daya'
        ORDER BY 
            k.name, rdd.record_date ASC
    ");
    $historical_data_query->bind_param("i", $selected_year);
} else {
    $historical_data_query = $conn->prepare("
        SELECT 
            k.name AS kecamatan_name,
            rdd.record_date,
            rdd.temperature,
            rdd.humidity,
            rdd.risk_level
        FROM 
            region_daily_data rdd
        JOIN 
            kecamatan k ON rdd.kecamatan_id = k.id
        WHERE 
            YEAR(rdd.record_date) = ? AND MONTH(rdd.record_date) = ? AND k.name != 'Pontianak Barat Daya'
        ORDER BY 
            k.name, rdd.record_date ASC
    ");
    $historical_data_query->bind_param("ii", $selected_year, $selected_month);
}

$historical_data_query->execute();
$historical_result = $historical_data_query->get_result();

// Tulis data ke CSV
while ($row = $historical_result->fetch_assoc()) {
    fputcsv($output, [
        $row['kecamatan_name'],
        $row['record_date'],
        $row['temperature'],
        $row['humidity'],
        $row['risk_level']
    ]);
}

fclose($output);
exit;
?>
<?php
include 'db.php'; // Memasukkan koneksi database dan memulai sesi

// Autentikasi: Hanya user yang login yang bisa mengakses
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Mengambil data historis dari database
// Kita akan ambil data untuk 30 hari terakhir sebagai contoh
$data_limit_days = 30; // Atau bisa disesuaikan lewat input form di masa depan
$start_date = date("Y-m-d", strtotime("-{$data_limit_days} days"));
$end_date = date("Y-m-d");

// Query dimodifikasi untuk hanya mengambil 5 kecamatan selain 'Pontianak Barat Daya'
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
        rdd.record_date BETWEEN ? AND ? AND k.name != 'Pontianak Barat Daya'
    ORDER BY 
        k.name, rdd.record_date ASC
");
$historical_data_query->bind_param("ss", $start_date, $end_date);
$historical_data_query->execute();
$historical_result = $historical_data_query->get_result();

$chart_data = []; // Akan menyimpan data yang diformat untuk Chart.js
$kecamatan_names = []; // Untuk menyimpan daftar nama kecamatan

while ($row = $historical_result->fetch_assoc()) {
    $kecamatan_name = $row['kecamatan_name'];
    $record_date = $row['record_date'];
    $risk_level = $row['risk_level'];

    if (!in_array($kecamatan_name, $kecamatan_names)) {
        $kecamatan_names[] = $kecamatan_name;
    }

    // Siapkan data untuk diagram
    $chart_data[$kecamatan_name]['dates'][] = $record_date;
    
    // Konversi tingkat risiko ke nilai numerik untuk diagram
    $risk_numeric = 0;
    switch ($risk_level) {
        case 'Tinggi': $risk_numeric = 3; break;
        case 'Sedang': $risk_numeric = 2; break;
        case 'Rendah': $risk_numeric = 1; break;
        default: $risk_numeric = 0; // 'Tidak Ada Data'
    }
    $chart_data[$kecamatan_name]['risk_levels'][] = $risk_numeric;
}

// Untuk memastikan semua kecamatan memiliki semua tanggal, bahkan jika data kosong
$all_dates = [];
foreach ($chart_data as $data) {
    foreach ($data['dates'] as $date) {
        if (!in_array($date, $all_dates)) {
            $all_dates[] = $date;
        }
    }
}
sort($all_dates); // Urutkan tanggal

// Lengkapi data untuk Chart.js agar semua dataset memiliki panjang yang sama
$final_chart_datasets = [];
foreach ($kecamatan_names as $name) {
    $risk_data = [];
    
    foreach ($all_dates as $date) {
        $index = array_search($date, $chart_data[$name]['dates'] ?? []);
        if ($index !== false) {
            $risk_data[] = $chart_data[$name]['risk_levels'][$index];
        } else {
            // Jika data tidak ada untuk tanggal ini, gunakan null atau 0
            $risk_data[] = null;
        }
    }
    $final_chart_datasets[$name] = [
        'risk_levels' => $risk_data
    ];
}

// Konversi data PHP ke JSON untuk JavaScript
$json_all_dates = json_encode($all_dates);
$json_chart_datasets = json_encode($final_chart_datasets);
$json_kecamatan_names = json_encode($kecamatan_names);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik Kerawanan DBD</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2c5530 0%, #1a7037 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
        }
        
        #sidebar {
            width: 250px;
            background: linear-gradient(180deg, #1a7037 0%, #2c5530 100%);
            padding: 20px;
            color: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            flex-shrink: 0;
        }

        #sidebar .sidebar-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        #sidebar .sidebar-header h2 {
            font-size: 1.8rem;
            margin: 0;
            font-weight: bold;
        }

        #sidebar ul.components {
            padding: 0;
            list-style: none;
            flex-grow: 1;
        }

        #sidebar ul li {
            margin-bottom: 10px;
        }

        #sidebar ul li a {
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
            width: 100%;
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }

        #sidebar ul li a:hover,
        #sidebar ul li a.active {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        /* Gaya khusus untuk tombol logout */
        #sidebar ul li a.logout-link {
            background: linear-gradient(45deg, #dc3545, #b82c3b); /* Red gradient */
            color: white; /* White text */
            padding: 10px 15px; /* Same padding as other links */
            border-radius: 8px; /* Same border radius */
            font-weight: bold; /* Make text bold */
        }

        #sidebar ul li a.logout-link:hover {
            background: linear-gradient(45deg, #b82c3b, #dc3545); /* Slightly darker/different red on hover */
            transform: translateX(5px); /* Keep the slide effect */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3); /* Add a subtle shadow */
        }

        #sidebar ul li a i {
            margin-right: 10px;
        }

        #content {
            flex-grow: 1;
            padding: 20px 15px;
            background: linear-gradient(135deg, #f0f0f0 0%, #ffffff 100%);
            overflow-y: auto;
            min-height: 100vh;
        }
        
        .main-content-area {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            border-radius: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: bold;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .btn-back {
            background: linear-gradient(45deg, #2c5530, #1a7037);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            color: white;
        }
        .chart-title {
            text-align: center;
            margin-bottom: 20px;
            color: #2c5530;
            font-weight: bold;
        }
        .chart-legend ul {
            list-style: none;
            padding: 0;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .chart-legend li {
            margin: 0 10px;
            display: flex;
            align-items: center;
        }
        .chart-legend span {
            display: inline-block;
            width: 15px;
            height: 15px;
            margin-right: 5px;
            border-radius: 3px;
        }

        /* Responsive: sidebar jadi fixed, konten geser ke kanan */
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            #sidebar {
                position: fixed;
                left: 0;
                top: 0;
                z-index: 1000;
                height: 100vh;
                width: 220px;
                box-shadow: 2px 0 10px rgba(0,0,0,0.2);
            }
            #content {
                margin-left: 220px;
                padding-top: 20px;
            }
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'stats';
    include 'sidebar.php'; 
    ?>

    <div id="content">
        <div class="main-content-area">
            <div class="container-fluid">
                <div class="header">
                    <h1><i class="fas fa-chart-bar"></i> Statistik Kerawanan DBD Historis</h1>
                    <p>Data Tingkat Risiko per Kecamatan (<?php echo $data_limit_days; ?> Hari Terakhir)</p>
                    <p><a href="<?php echo ($_SESSION['role'] === 'admin') ? 'dasboard-admin.php' : 'index.php'; ?>" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a> | Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?>! | <a href="logout.php" class="btn btn-sm btn-danger">Logout</a></p>
                </div>

                <?php if (empty($all_dates) || empty($kecamatan_names)): ?>
                    <div class="alert alert-info text-center" role="alert">
                        Belum ada data historis yang tersedia untuk ditampilkan.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($kecamatan_names as $kecamatan): ?>
                        <div class="col-lg-12">
                            <div class="chart-container">
                                <h4 class="chart-title">Risiko DBD: <?php echo htmlspecialchars($kecamatan); ?></h4>
                                <canvas id="riskChart_<?php echo str_replace(' ', '_', $kecamatan); ?>"></canvas>
                                <div class="chart-legend">
                                    <ul>
                                        <li><span style="background-color:#dc3545;"></span>Tinggi (3)</li>
                                        <li><span style="background-color:#ffc107;"></span>Sedang (2)</li>
                                        <li><span style="background-color:#28a745;"></span>Rendah (1)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        const allDates = <?php echo $json_all_dates; ?>;
        const chartDatasets = <?php echo $json_chart_datasets; ?>;
        const kecamatanNames = <?php echo $json_kecamatan_names; ?>;

        function getRiskLevelColor(value) {
            if (value === 3) return '#dc3545'; // Tinggi
            if (value === 2) return '#ffc107'; // Sedang
            if (value === 1) return '#28a745'; // Rendah
            return '#6c757d'; // Tidak Ada Data atau null
        }

        kecamatanNames.forEach(kecamatan => {
            const safeKecamatanName = kecamatan.replace(/ /g, '_');
            const data = chartDatasets[kecamatan];

            // Diagram Risiko
            const riskCtx = document.getElementById(`riskChart_${safeKecamatanName}`);
            if (riskCtx) {
                new Chart(riskCtx, {
                    type: 'line',
                    data: {
                        labels: allDates,
                        datasets: [{
                            label: 'Tingkat Risiko',
                            data: data.risk_levels,
                            borderColor: getRiskLevelColor(data.risk_levels[data.risk_levels.length - 1]),
                            backgroundColor: getRiskLevelColor(data.risk_levels[data.risk_levels.length - 1]),
                            fill: false,
                            tension: 0.1,
                            pointRadius: 5,
                            pointBackgroundColor: data.risk_levels.map(value => getRiskLevelColor(value)),
                            pointBorderColor: 'white',
                            pointBorderWidth: 2,
                            spanGaps: true
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 3.5,
                                ticks: {
                                    stepSize: 1,
                                    callback: function(value, index, values) {
                                        if (value === 3) return 'Tinggi';
                                        if (value === 2) return 'Sedang';
                                        if (value === 1) return 'Rendah';
                                        return '';
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Tingkat Risiko'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Tanggal'
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        const value = context.raw;
                                        if (value === 3) label += 'Tinggi';
                                        else if (value === 2) label += 'Sedang';
                                        else if (value === 1) label += 'Rendah';
                                        else label += 'Tidak Ada Data';
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
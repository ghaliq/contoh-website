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
        rdd.record_date BETWEEN ? AND ?
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
    $temperature = $row['temperature'];
    $humidity = $row['humidity'];
    $risk_level = $row['risk_level'];

    if (!in_array($kecamatan_name, $kecamatan_names)) {
        $kecamatan_names[] = $kecamatan_name;
    }

    // Siapkan data untuk diagram
    $chart_data[$kecamatan_name]['dates'][] = $record_date;
    $chart_data[$kecamatan_name]['temperatures'][] = $temperature;
    $chart_data[$kecamatan_name]['humidities'][] = $humidity;
    
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
    $temp_data = [];
    $humidity_data = [];
    $risk_data = [];
    
    foreach ($all_dates as $date) {
        $index = array_search($date, $chart_data[$name]['dates'] ?? []);
        if ($index !== false) {
            $temp_data[] = $chart_data[$name]['temperatures'][$index];
            $humidity_data[] = $chart_data[$name]['humidities'][$index];
            $risk_data[] = $chart_data[$name]['risk_levels'][$index];
        } else {
            // Jika data tidak ada untuk tanggal ini, gunakan null atau 0
            $temp_data[] = null;
            $humidity_data[] = null;
            $risk_data[] = null;
        }
    }
    $final_chart_datasets[$name] = [
        'temperatures' => $temp_data,
        'humidities' => $humidity_data,
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
            padding: 20px;
            color: #333;
        }
        .container-fluid {
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header">
            <h1><i class="fas fa-chart-bar"></i> Statistik Kerawanan DBD Historis</h1>
            <p>Data Suhu, Kelembaban, dan Tingkat Risiko per Kecamatan (<?php echo $data_limit_days; ?> Hari Terakhir)</p>
            <p><a href="<?php echo ($_SESSION['role'] === 'admin') ? 'dasboard-admin.php' : 'index.php'; ?>" class="btn btn-sm btn-light"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a> | Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?>! | <a href="logout.php" class="btn btn-sm btn-danger">Logout</a></p>
        </div>

        <?php if (empty($all_dates) || empty($kecamatan_names)): ?>
            <div class="alert alert-info text-center" role="alert">
                Belum ada data historis yang tersedia untuk ditampilkan.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($kecamatan_names as $kecamatan): ?>
                <div class="col-lg-6">
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
                <div class="col-lg-6">
                    <div class="chart-container">
                        <h4 class="chart-title">Suhu & Kelembaban: <?php echo htmlspecialchars($kecamatan); ?></h4>
                        <canvas id="tempHumidChart_<?php echo str_replace(' ', '_', $kecamatan); ?>"></canvas>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
                            borderColor: getRiskLevelColor(data.risk_levels[data.risk_levels.length - 1]), // Warna garis berdasarkan risiko terakhir
                            backgroundColor: getRiskLevelColor(data.risk_levels[data.risk_levels.length - 1]),
                            fill: false,
                            tension: 0.1,
                            pointRadius: 5,
                            pointBackgroundColor: data.risk_levels.map(value => getRiskLevelColor(value)),
                            pointBorderColor: 'white',
                            pointBorderWidth: 2,
                            spanGaps: true // Menghubungkan titik data yang null
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 3.5, // Maksimum 3 untuk risiko
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

            // Diagram Suhu dan Kelembaban
            const tempHumidCtx = document.getElementById(`tempHumidChart_${safeKecamatanName}`);
            if (tempHumidCtx) {
                new Chart(tempHumidCtx, {
                    type: 'line',
                    data: {
                        labels: allDates,
                        datasets: [
                            {
                                label: 'Suhu (°C)',
                                data: data.temperatures,
                                borderColor: 'rgb(255, 99, 132)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                fill: false,
                                tension: 0.1,
                                yAxisID: 'y',
                                spanGaps: true
                            },
                            {
                                label: 'Kelembaban (%)',
                                data: data.humidities,
                                borderColor: 'rgb(54, 162, 235)',
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                fill: false,
                                tension: 0.1,
                                yAxisID: 'y1',
                                spanGaps: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Suhu (°C)'
                                },
                                beginAtZero: false
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Kelembaban (%)'
                                },
                                beginAtZero: false,
                                grid: {
                                    drawOnChartArea: false, // only draw grid lines for the first y axis
                                },
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Tanggal'
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
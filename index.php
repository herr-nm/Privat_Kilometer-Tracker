<?php
/**
 * Kilometer Delta Tracker v1.5 - PKV Standard Design & Footer
 */

// 1. Konfiguration & Daten laden
$env = parse_ini_file('.env');
$vehicleRaw = explode(',', $env['VEHICLES']);
$vehicles = [];
foreach($vehicleRaw as $v) {
    list($id, $name) = explode(':', $v);
    $vehicles[$id] = $name;
}

$jsonFile = 'km_log.json';
$data = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
$activeVid = $_GET['vid'] ?? array_key_first($vehicles);

// 2. Speichern & Löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $editIdx = $_POST['edit_index'] ?? '';
    $newEntry = [
        'vid' => $_POST['vehicle_id'],
        'date' => $_POST['date'],
        'km' => (int)$_POST['km_stand']
    ];

    if ($editIdx !== '') {
        $data[$editIdx] = $newEntry;
    } else {
        $data[] = $newEntry;
    }

    usort($data, function($a, $b) { return strcmp($a['date'], $b['date']); });
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    header("Location: ?vid=" . $_POST['vehicle_id']); exit;
}

if (isset($_GET['delete'])) {
    array_splice($data, $_GET['delete'], 1);
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    header("Location: ?vid=$activeVid"); exit;
}

// 3. Berechnung der Deltas
$displayHistory = [];
$chartLabels = [];
$chartData = [];
$lastEntry = null;

foreach ($data as $index => $entry) {
    if ($entry['vid'] !== $activeVid) continue;
    $row = $entry;
    $row['index'] = $index;
    $row['delta'] = null;
    if ($lastEntry) {
        $kmDiff = $entry['km'] - $lastEntry['km'];
        $dateDiff = (strtotime($entry['date']) - strtotime($lastEntry['date'])) / (60 * 60 * 24);
        if ($dateDiff > 0) {
            $row['delta'] = $kmDiff;
            $normedKm = ($kmDiff / $dateDiff) * 30;
            $chartLabels[] = date("M Y", strtotime($entry['date']));
            $chartData[] = round($normedKm, 0);
        }
    }
    $displayHistory[] = $row;
    $lastEntry = $entry;
}
$displayHistory = array_reverse($displayHistory);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kilometer-Tracker</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { 
            --pkv-blue: #007bff; 
            --bg-gray: #f0f2f5; 
            --dark-gray: #343a40;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: var(--bg-gray); 
            margin: 0; 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
        }

        .container { 
            max-width: 1200px; 
            margin: 0 auto 30px auto; 
            padding: 0 20px; 
            flex: 1; 
            width: 100%; 
            box-sizing: border-box; 
        }

        /* Navigation Buttons Style PKV */
        .nav { margin-bottom: 20px; display: flex; gap: 5px; background: #ddd; padding: 5px; border-radius: 8px; width: fit-content; }
        .nav a { text-decoration: none; color: #555; padding: 8px 15px; border-radius: 5px; font-size: 0.9rem; transition: all 0.2s; }
        .nav a.active { background: var(--pkv-blue); color: white; }

        .content-box { 
            background: white; 
            padding: 25px; 
            border-radius: 12px; 
            box-shadow: 0 2px 12px rgba(0,0,0,0.05); 
            margin-bottom: 25px; 
        }

        h2, h3 { margin-top: 0; color: #333; }

        form { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px; 
            display: flex; 
            flex-wrap: wrap; 
            gap: 15px; 
            align-items: flex-end; 
            margin-bottom: 30px; 
            border: 1px solid #eee;
        }

        .input-group { display: flex; flex-direction: column; gap: 4px; }
        .input-group label { font-size: 0.7rem; color: #666; font-weight: bold; text-transform: uppercase; }
        
        input, button { padding: 10px; border: 1px solid #dee2e6; border-radius: 6px; font-size: 0.9rem; }
        button { background: var(--pkv-blue); color: white; border: none; cursor: pointer; font-weight: bold; }
        button:hover { background: #0056b3; }

        .chart-box { height: 350px; margin-bottom: 10px; }

        table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        th { background: #f8f9fa; padding: 12px; text-align: left; font-size: 0.75rem; color: #666; border-bottom: 2px solid #eee; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #f1f1f1; font-size: 0.9rem; }
        
        .delta { color: #28a745; font-weight: bold; }
        .btn-edit, .btn-del { text-decoration: none; margin-right: 10px; cursor: pointer; }

        /* Footer Style von der Abrechnung */
        footer { 
            background: var(--dark-gray); 
            color: #bbb; 
            padding: 30px; 
            text-align: center; 
            margin-top: 40px; 
            font-size: 0.85rem; 
        }
        footer a { color: white; text-decoration: none; border-bottom: 1px solid #555; }
    </style>
</head>
<body>

<!-- Globaler Header -->
<style>
    .main-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 30px;
        background-color: #ffffff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        font-family: 'Segoe UI', sans-serif;
        margin-bottom: 20px;
    }

    .header-logo img {
        height: 50px; /* Größe nach Bedarf anpassen */
        width: auto;
        display: block;
    }

    .header-title-center h1 {
        margin: 0;
        font-size: 1.5rem;
        color: #333;
        text-align: center;
    }

    .header-nav-right .btn-dashboard {
        text-decoration: none;
        background-color: #007bff;
        color: white;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: bold;
        transition: background 0.3s;
    }

    .header-nav-right .btn-dashboard:hover {
        background-color: #0056b3;
    }
</style>

<header class="main-header">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <div class="header-logo">
        <img src="../logo.png" alt="Logo">
    </div>
    
    <div class="header-title-center">
        <h1>Kilometer-Tracker</h1>
    </div>

    <div class="header-nav-right">
		<a href="../index.php" class="btn-dashboard"><i class="fa-solid fa-house"></i> Dashboard</a>
    </div>
</header>

<div class="container">
    <div class="content-box">
        <h3>KM-Analyse: <?= htmlspecialchars($vehicles[$activeVid]) ?></h3>

        <div class="nav">
            <?php foreach($vehicles as $id => $name): ?>
                <a href="?vid=<?= $id ?>" class="<?= ($activeVid == $id) ? 'active' : '' ?>"><?= htmlspecialchars($name) ?></a>
            <?php endforeach; ?>
        </div>

        <form method="POST" id="main-form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="vehicle_id" value="<?= $activeVid ?>">
            <input type="hidden" name="edit_index" id="edit_index" value="">
            
            <div class="input-group"><label>Datum</label><input type="date" name="date" id="form_date" value="<?= date('Y-m-d') ?>" required></div>
            <div class="input-group"><label>Gesamt-km-Stand</label><input type="number" name="km_stand" id="form_km" placeholder="z.B. 12500" required></div>
            
            <button type="submit" id="submit-btn">Speichern</button>
            <button type="button" id="cancel-btn" style="display:none; background:#6c757d;" onclick="resetForm()">Abbrechen</button>
        </form>

        <div class="chart-box">
            <canvas id="deltaChart"></canvas>
        </div>
    </div>

    <div class="content-box">
        <h2>Historie</h2>
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>KM-Stand</th>
                    <th>Differenz</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($displayHistory as $r): ?>
                <tr>
                    <td><?= date("d.m.Y", strtotime($r['date'])) ?></td>
                    <td><strong><?= number_format($r['km'], 0, ',', '.') ?> km</strong></td>
                    <td><span class="delta"><?= $r['delta'] ? "+".number_format($r['delta'], 0, ',', '.')." km" : '-' ?></span></td>
                    <td>
                        <a onclick="editEntry(<?= $r['index'] ?>, '<?= $r['date'] ?>', <?= $r['km'] ?>)" class="btn-edit">✏️</a>
                        <a href="?delete=<?= $r['index'] ?>&vid=<?= $activeVid ?>" class="btn-del" onclick="return confirm('Löschen?')">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<footer>
    <p>Kilometer-Tracker</strong> | Lizenziert unter <a href="https://www.gnu.org/licenses/agpl-3.0.de.html" target="_blank">AGPL-3.0</a> | Source: <a href="https://github.com/herr-nm/Privat_Kilometer-Tracker" target="_blank">GitHub</a></p>
</footer>

<script>
    new Chart(document.getElementById('deltaChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'KM / 30 Tage (normiert)',
                data: <?= json_encode($chartData) ?>,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { 
                y: { beginAtZero: true, title: { display: true, text: 'Kilometer / Monat' } } 
            }
        }
    });

    function editEntry(index, date, km) {
        document.getElementById('edit_index').value = index;
        document.getElementById('form_date').value = date;
        document.getElementById('form_km').value = km;
        document.getElementById('submit-btn').innerText = "Aktualisieren";
        document.getElementById('cancel-btn').style.display = "inline-block";
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    function resetForm() {
        document.getElementById('edit_index').value = "";
        document.getElementById('form_date').value = "<?= date('Y-m-d') ?>";
        document.getElementById('form_km').value = "";
        document.getElementById('submit-btn').innerText = "Speichern";
        document.getElementById('cancel-btn').style.display = "none";
    }
</script>
</body>
</html>
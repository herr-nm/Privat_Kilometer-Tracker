<?php
/**
 * Kilometer Delta Tracker v1.3 by Herr NM
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

// 3. Berechnung der Deltas (30-Tage-Norm)
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
    <title>Kilometer Delta Tracker</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .nav { margin-bottom: 20px; display: flex; gap: 10px; }
        .nav a { text-decoration: none; color: #667; padding: 8px 15px; background: #eee; border-radius: 6px; font-size: 14px; }
        .nav a.active { background: #3498db; color: white; }
        
        form { background: #f8f9fa; padding: 20px; border-radius: 8px; display: flex; gap: 15px; align-items: flex-end; margin-bottom: 30px; }
        .input-group { display: flex; flex-direction: column; gap: 5px; }
        input, button { padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
        button { background: #007bff; color: white; border: none; cursor: pointer; font-weight: bold; }
        
        .chart-box { height: 350px; margin-bottom: 40px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        .delta { font-size: 0.85em; color: #28a745; font-weight: bold; }
        .btn-edit, .btn-del { text-decoration: none; font-size: 1.2em; margin-right: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h1>KM-Analyse: <?= htmlspecialchars($vehicles[$activeVid]) ?></h1>

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

    <div class="chart-box"><canvas id="deltaChart"></canvas></div>

    <h2>Historie</h2>
    <table>
        <thead><tr><th>Datum</th><th>KM-Stand</th><th>Differenz</th><th>Aktion</th></tr></thead>
        <tbody>
            <?php foreach ($displayHistory as $r): ?>
            <tr>
                <td><?= date("d.m.Y", strtotime($r['date'])) ?></td>
                <td><?= number_format($r['km'], 0, ',', '.') ?> km</td>
                <td><span class="delta"><?= $r['delta'] ? "+".number_format($r['delta'], 0, ',', '.')." km" : '-' ?></span></td>
                <td>
                    <a href="#" class="btn-edit" onclick="editEntry(<?= $r['index'] ?>, '<?= $r['date'] ?>', <?= $r['km'] ?>)">✏️</a>
                    <a href="?delete=<?= $r['index'] ?>&vid=<?= $activeVid ?>" class="btn-del" onclick="return confirm('Löschen?')">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    // Liniendiagramm wie im Hausverbrauch[cite: 1]
    new Chart(document.getElementById('deltaChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'KM / 30 Tage (normiert)',
                data: <?= json_encode($chartData) ?>,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { 
                y: { 
                    beginAtZero: true, 
                    title: { display: true, text: 'Kilometer / Monat' } 
                } 
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
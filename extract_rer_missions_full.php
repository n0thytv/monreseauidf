<?php
/**
 * Script d'extraction asynchrone des terminus de toutes les missions RER
 * Ce script lit le fichier stop_times.txt du GTFS et extrait le premier
 * et dernier arrêt de chaque mission pour tous les RER.
 * 
 * Usage: php extract_rer_missions_full.php
 * Temps estimé: 5-15 minutes selon la machine
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

$startTime = microtime(true);
$logFile = __DIR__ . '/cache/rer_missions_extraction.log';

function logMsg($msg)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $msg\n";
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
    flush();
}

logMsg("=== STARTING RER MISSIONS EXTRACTION ===");

$cacheDir = __DIR__ . '/cache';
$gtfsDir = $cacheDir . '/gtfs/extracted';
$tripsFile = $gtfsDir . '/trips.txt';
$stopTimesFile = $gtfsDir . '/stop_times.txt';
$stopsFile = $gtfsDir . '/stops.txt';

// Vérifier les fichiers
foreach ([$tripsFile, $stopTimesFile, $stopsFile] as $file) {
    if (!file_exists($file)) {
        logMsg("ERROR: File not found: $file");
        exit(1);
    }
}

// Configuration des RER
$rerLines = [
    'C01742' => 'A',
    'C01743' => 'B',
    'C01727' => 'C',
    'C01728' => 'D',
    'C01729' => 'E'
];

// Étape 1: Charger les noms des arrêts
logMsg("Step 1: Loading stops...");
$stops = [];
$handle = fopen($stopsFile, 'r');
$header = fgetcsv($handle);
$stopIdIdx = array_search('stop_id', $header);
$stopNameIdx = array_search('stop_name', $header);
while (($row = fgetcsv($handle)) !== false) {
    if (isset($row[$stopIdIdx]) && isset($row[$stopNameIdx])) {
        $stops[$row[$stopIdIdx]] = $row[$stopNameIdx];
    }
}
fclose($handle);
logMsg("  Loaded " . count($stops) . " stops");

// Étape 2: Charger les trips RER avec leurs missions
logMsg("Step 2: Loading RER trips...");
$tripMissions = []; // trip_id => ['line_id' => ..., 'mission' => ...]
$missionCounts = [];
foreach (array_keys($rerLines) as $lineId) {
    $missionCounts[$lineId] = [];
}

$handle = fopen($tripsFile, 'r');
$header = fgetcsv($handle);
$routeIdIdx = array_search('route_id', $header);
$tripIdIdx = array_search('trip_id', $header);
$headsignIdx = array_search('trip_headsign', $header);

while (($row = fgetcsv($handle)) !== false) {
    $routeId = $row[$routeIdIdx] ?? '';
    foreach ($rerLines as $lineId => $lineName) {
        if (strpos($routeId, $lineId) !== false) {
            $mission = $row[$headsignIdx] ?? '';
            if (strlen($mission) == 4 && preg_match('/^[A-Z]{4}$/', $mission)) {
                $tripId = $row[$tripIdIdx];
                $tripMissions[$tripId] = [
                    'line_id' => $lineId,
                    'mission' => $mission
                ];
                if (!isset($missionCounts[$lineId][$mission])) {
                    $missionCounts[$lineId][$mission] = 0;
                }
                $missionCounts[$lineId][$mission]++;
            }
            break;
        }
    }
}
fclose($handle);
logMsg("  Loaded " . count($tripMissions) . " trips");

// Filtrer les missions actives (>= 20 trips)
$activeMissions = [];
foreach ($rerLines as $lineId => $lineName) {
    $activeMissions[$lineId] = [];
    foreach ($missionCounts[$lineId] as $code => $count) {
        if ($count >= 20) {
            $activeMissions[$lineId][$code] = ['count' => $count, 'first' => null, 'last' => null];
        }
    }
    logMsg("  RER $lineName: " . count($activeMissions[$lineId]) . " active missions");
}

// Étape 3: Lire stop_times pour trouver premier/dernier arrêt
logMsg("Step 3: Processing stop_times.txt (this will take several minutes)...");

$tripStops = []; // trip_id => [sequence => stop_id]
$handle = fopen($stopTimesFile, 'r');
$header = fgetcsv($handle);
$tripIdx = array_search('trip_id', $header);
$stopIdx = array_search('stop_id', $header);
$seqIdx = array_search('stop_sequence', $header);

$lineCount = 0;
$lastLog = time();

while (($row = fgetcsv($handle)) !== false) {
    $tripId = $row[$tripIdx] ?? '';

    if (isset($tripMissions[$tripId])) {
        $lineId = $tripMissions[$tripId]['line_id'];
        $mission = $tripMissions[$tripId]['mission'];

        // Seulement si c'est une mission active
        if (isset($activeMissions[$lineId][$mission])) {
            if (!isset($tripStops[$tripId])) {
                $tripStops[$tripId] = [];
            }
            $tripStops[$tripId][$row[$seqIdx]] = $row[$stopIdx];
        }
    }

    $lineCount++;
    if (time() - $lastLog >= 10) {
        logMsg("  Processed $lineCount lines...");
        $lastLog = time();
    }
}
fclose($handle);
logMsg("  Completed: $lineCount lines processed");

// Étape 4: Extraire les terminus par mission
logMsg("Step 4: Extracting terminus for each mission...");

foreach ($tripStops as $tripId => $stopsSeq) {
    if (empty($stopsSeq))
        continue;

    ksort($stopsSeq);
    $stopIds = array_values($stopsSeq);
    $firstStopId = $stopIds[0];
    $lastStopId = end($stopIds);

    $firstName = $stops[$firstStopId] ?? $firstStopId;
    $lastName = $stops[$lastStopId] ?? $lastStopId;

    $lineId = $tripMissions[$tripId]['line_id'];
    $mission = $tripMissions[$tripId]['mission'];

    if (isset($activeMissions[$lineId][$mission])) {
        // Prendre le premier terminus trouvé
        if ($activeMissions[$lineId][$mission]['first'] === null) {
            $activeMissions[$lineId][$mission]['first'] = $firstName;
            $activeMissions[$lineId][$mission]['last'] = $lastName;
        }
    }
}

// Étape 5: Sauvegarder le cache final
logMsg("Step 5: Saving cache...");

$finalCache = [];
foreach ($rerLines as $lineId => $lineName) {
    $missions = [];
    foreach ($activeMissions[$lineId] as $code => $data) {
        if ($data['first'] !== null && $data['last'] !== null) {
            $missions[] = [
                'code' => $code,
                'terminus_a' => $data['first'],
                'terminus_b' => $data['last'],
                'trips' => $data['count']
            ];
        }
    }
    // Trier par nombre de trips
    usort($missions, function ($a, $b) {
        return $b['trips'] - $a['trips']; });
    $finalCache[$lineId] = $missions;
    logMsg("  RER $lineName: " . count($missions) . " missions with terminus");
}

$outputFile = $cacheDir . '/rer_missions_full.json';
file_put_contents($outputFile, json_encode($finalCache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$elapsed = round(microtime(true) - $startTime, 2);
logMsg("=== EXTRACTION COMPLETE in {$elapsed}s ===");
logMsg("Output saved to: $outputFile");

// Créer un fichier de statut
$statusFile = $cacheDir . '/rer_missions_status.json';
file_put_contents($statusFile, json_encode([
    'status' => 'complete',
    'timestamp' => date('c'),
    'elapsed_seconds' => $elapsed,
    'missions_count' => array_sum(array_map('count', $finalCache))
], JSON_PRETTY_PRINT));

echo "\nDone!\n";

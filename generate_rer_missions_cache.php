<?php
/**
 * Script pour générer le cache des missions RER actives depuis le GTFS IDFM
 * Les missions avec >= 20 trips sont considérées comme actives
 */

$cacheDir = __DIR__ . '/cache';
$gtfsDir = $cacheDir . '/gtfs/extracted';
$tripsFile = $gtfsDir . '/trips.txt';

if (!file_exists($tripsFile)) {
    die("GTFS trips.txt not found. Please download GTFS first.\n");
}

// Configuration des RER
$rerLines = [
    'C01742' => 'A',
    'C01743' => 'B',
    'C01727' => 'C',
    'C01728' => 'D',
    'C01729' => 'E'
];

$allMissions = [];

// Lire le fichier trips.txt
$handle = fopen($tripsFile, 'r');
$header = fgetcsv($handle);
$routeIdIdx = array_search('route_id', $header);
$headsignIdx = array_search('trip_headsign', $header);

$missionCounts = [];
foreach (array_keys($rerLines) as $lineId) {
    $missionCounts[$lineId] = [];
}

while (($row = fgetcsv($handle)) !== false) {
    $routeId = $row[$routeIdIdx];
    foreach ($rerLines as $lineId => $lineName) {
        if (strpos($routeId, $lineId) !== false) {
            $mission = $row[$headsignIdx];
            if (strlen($mission) == 4 && preg_match('/^[A-Z]{4}$/', $mission)) {
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

// Filtrer les missions actives (>= 20 trips)
foreach ($rerLines as $lineId => $lineName) {
    $activeMissions = [];
    foreach ($missionCounts[$lineId] as $code => $trips) {
        if ($trips >= 20) {
            $activeMissions[] = [
                'code' => $code,
                'trips' => $trips
            ];
        }
    }
    // Trier par nombre de trips décroissant
    usort($activeMissions, function ($a, $b) {
        return $b['trips'] - $a['trips'];
    });
    $allMissions[$lineId] = $activeMissions;
}

// Sauvegarder le cache
$outputFile = $cacheDir . '/rer_missions.json';
file_put_contents($outputFile, json_encode($allMissions, JSON_PRETTY_PRINT));

echo "=== CACHE RER MISSIONS GENERATED ===\n";
foreach ($rerLines as $lineId => $lineName) {
    echo "RER $lineName ($lineId): " . count($allMissions[$lineId]) . " active missions\n";
}
echo "\nSaved to: $outputFile\n";

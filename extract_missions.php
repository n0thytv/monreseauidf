<?php
$baseDir = 'c:/wamp64/www/monreseauidf/cache/gtfs/extracted/';
$tripsFile = $baseDir . 'trips.txt';
$stopTimesFile = $baseDir . 'stop_times.txt';
$stopsFile = $baseDir . 'stops.txt';

// Charger les noms des arrêts
echo "Loading stops...\n";
$stops = [];
$handle = fopen($stopsFile, 'r');
$header = fgetcsv($handle);
$stopIdIdx = array_search('stop_id', $header);
$stopNameIdx = array_search('stop_name', $header);
while (($row = fgetcsv($handle)) !== false) {
    $stops[$row[$stopIdIdx]] = $row[$stopNameIdx];
}
fclose($handle);
echo "Loaded " . count($stops) . " stops\n";

// Charger les trips RER C
echo "Loading RER C trips...\n";
$tripMissions = [];
$handle = fopen($tripsFile, 'r');
$header = fgetcsv($handle);
$routeIdIdx = array_search('route_id', $header);
$tripIdIdx = array_search('trip_id', $header);
$headsignIdx = array_search('trip_headsign', $header);
while (($row = fgetcsv($handle)) !== false) {
    if (strpos($row[$routeIdIdx], 'C01727') !== false) {
        $mission = $row[$headsignIdx];
        if (strlen($mission) == 4 && preg_match('/^[A-Z]{4}$/', $mission)) {
            $tripMissions[$row[$tripIdIdx]] = $mission;
        }
    }
}
fclose($handle);
echo "Loaded " . count($tripMissions) . " trips\n";

// Lire stop_times pour trouver premier/dernier arrêt de chaque trip
echo "Processing stop_times (this may take a while)...\n";
$missionTermini = [];
$handle = fopen($stopTimesFile, 'r');
$header = fgetcsv($handle);
$tripIdx = array_search('trip_id', $header);
$stopIdx = array_search('stop_id', $header);
$seqIdx = array_search('stop_sequence', $header);

$tripStops = [];
$lineCount = 0;
while (($row = fgetcsv($handle)) !== false) {
    $tripId = $row[$tripIdx];
    if (isset($tripMissions[$tripId])) {
        if (!isset($tripStops[$tripId])) {
            $tripStops[$tripId] = [];
        }
        $tripStops[$tripId][$row[$seqIdx]] = $row[$stopIdx];
    }
    $lineCount++;
    if ($lineCount % 1000000 == 0) echo "  Processed $lineCount lines...\n";
}
fclose($handle);
echo "Processed $lineCount lines\n";

// Extraire les terminus par mission
foreach ($tripStops as $tripId => $stops_seq) {
    ksort($stops_seq);
    $stopIds = array_values($stops_seq);
    $first = $stops[$stopIds[0]] ?? $stopIds[0];
    $last = $stops[end($stopIds)] ?? end($stopIds);
    $mission = $tripMissions[$tripId];
    
    if (!isset($missionTermini[$mission])) {
        $missionTermini[$mission] = ['first' => $first, 'last' => $last];
    }
}

echo "\n=== MISSIONS RER C AVEC TERMINUS ===\n";
ksort($missionTermini);
foreach ($missionTermini as $code => $termini) {
    echo "$code: {$termini['first']} -> {$termini['last']}\n";
}
echo "\nTotal: " . count($missionTermini) . " missions\n";

<?php
/**
 * Mon Réseau IDF - API Horaires temps réel
 * Récupère les prochains passages via PRIM ou horaires théoriques
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$lineId = isset($_GET['line']) ? $_GET['line'] : '';
$stopId = isset($_GET['stop']) ? $_GET['stop'] : '';

if (empty($lineId)) {
    echo json_encode(['error' => 'ID de ligne requis']);
    exit;
}

// Récupérer la clé API PRIM
$apiKey = getSetting($pdo, 'idfm_api_key', '');

// Récupérer les infos de la ligne depuis le cache
$linesCache = __DIR__ . '/../cache/lignes.json';
$lineInfo = null;
if (file_exists($linesCache)) {
    $data = json_decode(file_get_contents($linesCache), true);
    if (isset($data['records'])) {
        // Nettoyer l'ID pour la comparaison (avec ou sans préfixe IDFM:)
        $cleanLineId = str_replace('IDFM:', '', $lineId);

        foreach ($data['records'] as $line) {
            $cacheId = str_replace('IDFM:', '', $line['id_line'] ?? '');
            if ($cacheId === $cleanLineId || $cacheId === $lineId) {
                $lineInfo = $line;
                break;
            }
        }
    }
}

$mode = $lineInfo ? strtolower($lineInfo['transportmode'] ?? 'metro') : 'metro';
$shortName = $lineInfo['shortname_line'] ?? null;

// Récupérer les prochains passages RÉELS depuis l'API PRIM
$realSchedule = getRealScheduleFromPRIM($lineId, $apiKey, $mode, $shortName);

/**
 * Récupère les prochains passages RÉELS depuis l'API PRIM
 * Retourne uniquement les données brutes, sans calcul ni déduction
 */
function getRealScheduleFromPRIM($lineId, $apiKey, $mode, $shortName = null) {
    global $debugPRIM;
    $debugPRIM = [];

    if (empty($apiKey)) {
        $debugPRIM['error'] = 'No API key';
        return null;
    }

    $lineCode = preg_replace('/^IDFM:/', '', $lineId);
    $debugPRIM['line_code'] = $lineCode;

    // Essayer de charger depuis le cache
    $cacheFile = __DIR__ . '/../cache/traces/' . $lineCode . '.json';
    $allStops = [];

    if (file_exists($cacheFile)) {
        $lineCache = json_decode(file_get_contents($cacheFile), true);
        if (!empty($lineCache['routes'][0]['stops'])) {
            $allStops = $lineCache['routes'][0]['stops'];
            $debugPRIM['source'] = 'cache';
        }
    }

    // Si pas de cache, récupérer les arrêts depuis l'API IDFM
    if (empty($allStops)) {
        $debugPRIM['source'] = 'api';
        $searchId = 'IDFM:' . $lineCode;

        // Utiliser le shortname si disponible (plus fiable pour les bus)
        if (!empty($shortName)) {
            $stopsUrl = "https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/arrets-lignes/records?where=shortname%3D%22" . urlencode($shortName) . "%22&limit=100";
        } else {
            $stopsUrl = "https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/arrets-lignes/records?where=id%3D%22" . urlencode($searchId) . "%22&limit=100";
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $stopsUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($response)) {
            $data = json_decode($response, true);
            if (!empty($data['results'])) {
                foreach ($data['results'] as $stop) {
                    $allStops[] = [
                        'stop_name' => $stop['stop_name'] ?? '',
                        'id' => $stop['stop_id'] ?? ''
                    ];
                }
                $debugPRIM['stops_from_api'] = count($allStops);
            }
        }
    }

    if (empty($allStops)) {
        $debugPRIM['error'] = 'No stops found';
        return null;
    }

    // Essayer plusieurs arrêts: dernier (souvent terminus), premier, et milieu
    $stopsToTry = [];
    // Dernier arrêt (souvent le terminus principal avec les départs)
    $stopsToTry[] = end($allStops);
    // Premier arrêt
    $stopsToTry[] = reset($allStops);
    // Arrêt du milieu
    if (count($allStops) > 2) {
        $stopsToTry[] = $allStops[intval(count($allStops) / 2)];
    }

    $debugPRIM['stops_tried'] = [];
    $departures = [];
    $stopName = '';
    $stopId = '';

    foreach ($stopsToTry as $stop) {
        $name = $stop['stop_name'] ?? '';
        if (!preg_match('/(\d{3,})/', $stop['id'] ?? '', $m)) continue;
        $id = $m[1];

        $debugPRIM['stops_tried'][] = ['name' => $name, 'id' => $id];

        // Appeler l'API PRIM pour cet arrêt
        $monitoringRef = 'STIF:StopPoint:Q:' . $id . ':';
        $primUrl = "https://prim.iledefrance-mobilites.fr/marketplace/stop-monitoring?MonitoringRef=" . urlencode($monitoringRef);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $primUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'apikey: ' . $apiKey
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) continue;

        $data = json_decode($response, true);

        if (isset($data['Siri']['ServiceDelivery']['StopMonitoringDelivery'])) {
            foreach ($data['Siri']['ServiceDelivery']['StopMonitoringDelivery'] as $delivery) {
                if (isset($delivery['MonitoredStopVisit'])) {
                    foreach ($delivery['MonitoredStopVisit'] as $visit) {
                        $journey = $visit['MonitoredVehicleJourney'] ?? [];
                        $lineRef = $journey['LineRef']['value'] ?? '';

                        // Filtrer par ligne
                        if (strpos($lineRef, $lineCode) === false) continue;

                        $call = $journey['MonitoredCall'] ?? [];
                        $expectedTime = $call['ExpectedDepartureTime'] ?? $call['ExpectedArrivalTime'] ?? null;
                        $destination = $journey['DestinationName'][0]['value'] ?? '';

                        if ($expectedTime) {
                            $dt = new DateTime($expectedTime);
                            $now = new DateTime();
                            $diff = $now->diff($dt);
                            $waitMinutes = ($diff->h * 60) + $diff->i;
                            if ($diff->invert) $waitMinutes = 0;

                            $departures[] = [
                                'time' => $dt->format('H:i'),
                                'datetime' => $expectedTime,
                                'destination' => $destination,
                                'wait_minutes' => $waitMinutes
                            ];
                        }
                    }
                }
            }
        }

        // Si on a trouvé des départs, utiliser cet arrêt
        if (!empty($departures)) {
            $stopName = $name;
            $stopId = $id;
            break;
        }
    }

    // Trier par heure
    usort($departures, function($a, $b) {
        return strtotime($a['datetime']) - strtotime($b['datetime']);
    });

    $debugPRIM['departures_count'] = count($departures);
    $debugPRIM['selected_stop'] = $stopName;

    if (empty($departures)) {
        $debugPRIM['error'] = 'No departures found on any stop';
        return null;
    }

    return [
        'stop_name' => $stopName,
        'departures' => $departures
    ];
}

// Construire le lien vers les horaires officiels
$shortName = $lineInfo['shortname_line'] ?? '';
$operatorName = strtolower($lineInfo['operatorname'] ?? '');
$officialUrl = null;

if (strpos($operatorName, 'ratp') !== false) {
    if ($mode === 'bus') {
        $officialUrl = 'https://www.ratp.fr/horaires?line=bus-' . urlencode($shortName);
    } elseif ($mode === 'metro') {
        $officialUrl = 'https://www.ratp.fr/horaires?line=metro-' . urlencode($shortName);
    }
} elseif (strpos($operatorName, 'sncf') !== false || $mode === 'rail') {
    $officialUrl = 'https://www.transilien.com/';
} else {
    // Autres opérateurs (Keolis, Transdev, etc.)
    $officialUrl = 'https://www.iledefrance-mobilites.fr/fiches-horaires';
}

$result = [
    'success' => $realSchedule !== null,
    'line_id' => $lineId,
    'line_name' => $lineInfo['name_line'] ?? 'Ligne',
    'shortname' => $shortName,
    'mode' => $mode,
    'operator' => $lineInfo['operatorname'] ?? 'IDFM',
    'realtime_available' => $realSchedule !== null,
    'next_departures' => $realSchedule ? $realSchedule['departures'] : [],
    'departure_stop' => $realSchedule ? $realSchedule['stop_name'] : null,
    'official_url' => $officialUrl,
    'official_idfm_url' => 'https://www.iledefrance-mobilites.fr/fiches-horaires/' . $mode . '/ligne-' . strtolower($shortName),
    'message' => $realSchedule
        ? 'Prochains passages en temps réel depuis ' . $realSchedule['stop_name']
        : 'Aucun passage prévu actuellement. Consultez les fiches horaires IDFM.',
    'debug_prim' => isset($debugPRIM) ? $debugPRIM : null
];

echo json_encode($result);

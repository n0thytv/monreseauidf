<?php
/**
 * Mon Réseau IDF - API Prochains passages RER temps réel
 * Utilise l'API PRIM pour les prochains départs RER - DONNÉES RÉELLES
 * Recherche dynamique des gares via l'API IDFM
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$stopName = isset($_GET['name']) ? trim($_GET['name']) : '';
$lineId = isset($_GET['line']) ? $_GET['line'] : '';

if (empty($stopName)) {
    echo json_encode([
        'success' => false,
        'error' => 'Nom de gare requis',
        'departures' => []
    ]);
    exit;
}

// Récupérer la clé API PRIM
$apiKey = getSetting($pdo, 'idfm_api_key', '');

if (empty($apiKey)) {
    echo json_encode([
        'success' => false,
        'error' => 'Clé API PRIM non configurée. Configurez-la dans Admin → Paramètres.',
        'departures' => []
    ]);
    exit;
}

// IDs des lignes RER officiels
$rerLineIds = [
    'A' => 'IDFM:C01742',
    'B' => 'IDFM:C01743',
    'C' => 'IDFM:C01727',
    'D' => 'IDFM:C01728',
    'E' => 'IDFM:C01729'
];

$departures = [];
$error = null;
$debug = [];

// Fonction prioritaire : rechercher une gare dans le cache trace-ligne
// Les IDs monomodalStopPlace du cache sont compatibles avec l'API PRIM (format StopArea:SP)
function searchStopInTraceCache($stopName, $lineId)
{
    global $debug;

    // Extraire le code de ligne (ex: IDFM:C01727 -> C01727)
    if (empty($lineId)) {
        return null;
    }

    $lineCode = preg_replace('/^IDFM:/', '', $lineId);
    $cacheFile = __DIR__ . '/../cache/traces/' . $lineCode . '.json';

    $debug['trace_cache_file'] = $cacheFile;
    $debug['trace_cache_exists'] = file_exists($cacheFile);

    if (!file_exists($cacheFile)) {
        return null;
    }

    $cacheData = json_decode(file_get_contents($cacheFile), true);
    if (!$cacheData || !isset($cacheData['routes'])) {
        return null;
    }

    // Nettoyer le nom de recherche
    $searchName = preg_replace('/^(Gare de |Gare |Station )/i', '', $stopName);
    $searchName = trim($searchName);
    $searchLower = strtolower($searchName);

    $debug['trace_search_name'] = $searchName;

    $foundStops = [];

    // Parcourir toutes les routes et leurs arrêts
    foreach ($cacheData['routes'] as $route) {
        foreach (($route['stops'] ?? []) as $stop) {
            $stopNameClean = strtolower($stop['stop_name'] ?? '');

            // Correspondance exacte ou partielle
            if (
                $stopNameClean === $searchLower ||
                strpos($stopNameClean, $searchLower) !== false ||
                strpos($searchLower, $stopNameClean) !== false
            ) {

                // Extraire l'ID monomodalStopPlace
                $stopId = $stop['stop_id'] ?? $stop['id'] ?? '';

                // Extraire le numéro de l'ID monomodalStopPlace (ex: IDFM:monomodalStopPlace:43121 -> 43121)
                if (preg_match('/monomodalStopPlace:(\d+)/', $stopId, $m)) {
                    $foundStops[] = [
                        'name' => $stop['stop_name'],
                        'id' => $m[1],
                        'route' => $route['name'] ?? ''
                    ];
                }
            }
        }
    }

    if (!empty($foundStops)) {
        // Prioriser les correspondances exactes sur les partielles
        // Trier par longueur de nom (les plus courts d'abord) pour prioriser "Dourdan" avant "Dourdan la Forêt"
        usort($foundStops, function ($a, $b) use ($searchLower) {
            $aExact = (strtolower($a['name']) === $searchLower) ? 0 : 1;
            $bExact = (strtolower($b['name']) === $searchLower) ? 0 : 1;

            // Prioriser les correspondances exactes
            if ($aExact !== $bExact) {
                return $aExact - $bExact;
            }

            // Sinon, prioriser les noms les plus courts (plus proches de la recherche)
            return strlen($a['name']) - strlen($b['name']);
        });

        $debug['trace_found_stop'] = $foundStops[0]['name'];
        $debug['trace_found_id'] = $foundStops[0]['id'];
        $debug['trace_found_route'] = $foundStops[0]['route'];
        $debug['trace_found_all'] = count($foundStops);
        return $foundStops[0]['id'];
    }

    return null;
}

// Fonction pour rechercher une gare dynamiquement via l'API IDFM
function searchRerStopId($stopName, $lineId = null)
{
    global $debug;

    // Nettoyer le nom (enlever "Gare de", "Gare", "Station", etc.)
    $cleanName = preg_replace('/^(Gare de |Gare |Station )/i', '', $stopName);
    $cleanName = trim($cleanName);

    $debug['clean_name'] = $cleanName;

    // Encoder pour l'URL
    $encodedName = urlencode($cleanName);

    // Construire la requête - recherche dans arrets-lignes
    $searchUrl = "https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/arrets-lignes/records?where=stop_name%20LIKE%20%22" . $encodedName . "%22&limit=50";

    $debug['search_url'] = $searchUrl;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $searchUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'MonReseauIDF/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $debug['search_http'] = $httpCode;

    if ($httpCode === 200 && !empty($response)) {
        $data = json_decode($response, true);
        if (isset($data['results']) && !empty($data['results'])) {
            $debug['results_count'] = count($data['results']);

            // Listes de modes ferroviaires et noms de lignes ferroviaires
            $railModes = ['rail', 'rer', 'train', 'rapidtransit', 'suburban'];
            $railLineNames = ['A', 'B', 'C', 'D', 'E', 'H', 'J', 'K', 'L', 'N', 'P', 'R', 'U', 'TER', 'OUIGO'];

            // Extraire le code de ligne si fourni (ex: IDFM:C01727 -> C01727)
            $targetLineCode = null;
            if (!empty($lineId)) {
                $targetLineCode = preg_replace('/^IDFM:/', '', $lineId);
            }

            // Première passe: chercher un arrêt RER/rail par mode ou nom de ligne
            foreach ($data['results'] as $result) {
                $mode = strtolower($result['transportmode'] ?? '');
                $resultStopName = strtolower($result['stop_name'] ?? '');
                $routeLongName = strtoupper($result['route_long_name'] ?? '');
                $searchLower = strtolower($cleanName);

                // Déterminer si c'est une ligne ferroviaire
                $isRailLine = in_array($mode, $railModes);

                // Si le mode est vide, vérifier le nom de la ligne
                if (empty($mode)) {
                    foreach ($railLineNames as $railName) {
                        if (
                            $routeLongName === $railName ||
                            strpos($routeLongName, 'TER') !== false ||
                            strpos($routeLongName, 'RER') !== false ||
                            strpos($routeLongName, 'TRANSILIEN') !== false
                        ) {
                            $isRailLine = true;
                            break;
                        }
                    }
                }

                // Vérifier si c'est une ligne ferroviaire
                if ($isRailLine) {
                    // Vérifier si le nom correspond
                    if (
                        $resultStopName === $searchLower ||
                        strpos($resultStopName, $searchLower) !== false ||
                        strpos($searchLower, $resultStopName) !== false
                    ) {
                        $stopId = $result['stop_id'] ?? null;
                        if ($stopId) {
                            // Extraire juste le numéro
                            if (preg_match('/(\d{5,})/', $stopId, $matches)) {
                                $debug['found_stop'] = $result['stop_name'];
                                $debug['found_mode'] = $mode ?: $routeLongName;
                                $debug['found_line'] = $routeLongName;
                                return $matches[1];
                            }
                            // Sinon retourner l'ID complet
                            $debug['found_stop'] = $result['stop_name'];
                            return $stopId;
                        }
                    }
                }
            }

            // Deuxième passe: si pas trouvé, chercher sans filtre de mode mais avec correspondance exacte
            foreach ($data['results'] as $result) {
                $resultStopName = $result['stop_name'] ?? '';
                $resultStopNameLower = strtolower($resultStopName);
                $searchLower = strtolower($cleanName);

                // Correspondance exacte ou "Gare de X" pour X
                if (
                    $resultStopNameLower === $searchLower ||
                    $resultStopNameLower === 'gare de ' . $searchLower ||
                    $resultStopNameLower === 'gare ' . $searchLower
                ) {
                    $stopId = $result['stop_id'] ?? null;
                    if ($stopId) {
                        if (preg_match('/(\d{5,})/', $stopId, $matches)) {
                            $debug['found_stop_fallback'] = $resultStopName;
                            $debug['found_line_fallback'] = $result['route_long_name'] ?? '';
                            return $matches[1];
                        }
                        return $stopId;
                    }
                }
            }

            // Troisième passe: prendre le premier résultat qui contient le nom
            foreach ($data['results'] as $result) {
                $resultStopName = strtolower($result['stop_name'] ?? '');
                $searchLower = strtolower($cleanName);

                if (
                    strpos($resultStopName, $searchLower) !== false
                ) {
                    $stopId = $result['stop_id'] ?? null;
                    if ($stopId) {
                        if (preg_match('/(\d{5,})/', $stopId, $matches)) {
                            $debug['found_stop_last_resort'] = $result['stop_name'];
                            return $matches[1];
                        }
                        return $stopId;
                    }
                }
            }
        }
    }

    return null;
}

// Chercher l'ID de la gare : d'abord dans le cache trace-ligne (IDs monomodalStopPlace)
// puis fallback sur l'API IDFM si le cache ne fonctionne pas
$stopId = searchStopInTraceCache($stopName, $lineId);
$debug['source'] = $stopId ? 'trace_cache' : null;

if (!$stopId) {
    // Fallback sur l'API IDFM
    $stopId = searchRerStopId($stopName, $lineId);
    if ($stopId) {
        $debug['source'] = 'idfm_api';
    }
}

$debug['found_stop_id'] = $stopId;

if (!$stopId) {
    echo json_encode([
        'success' => false,
        'error' => 'Gare "' . htmlspecialchars($stopName) . '" non trouvée. Vérifiez l\'orthographe.',
        'departures' => [],
        'hint' => 'Essayez avec le nom exact (ex: "Dourdan" au lieu de "Gare de Dourdan")',
        'debug' => isset($_GET['debug']) ? $debug : null
    ]);
    exit;
}

// Construire le MonitoringRef au format PRIM
// Format StopArea:SP requis pour les données SNCF (RER/TER) depuis mars 2025
$monitoringRef = 'STIF:StopArea:SP:' . $stopId . ':';

$debug['monitoring_ref'] = $monitoringRef;

// Appeler l'API stop-monitoring PRIM
$primUrl = "https://prim.iledefrance-mobilites.fr/marketplace/stop-monitoring?MonitoringRef=" . urlencode($monitoringRef);

$debug['prim_url'] = $primUrl;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $primUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'apikey: ' . $apiKey
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$debug['prim_http'] = $httpCode;

if ($httpCode === 200 && !empty($response)) {
    $data = json_decode($response, true);

    if (isset($data['Siri']['ServiceDelivery']['StopMonitoringDelivery'])) {
        $deliveries = $data['Siri']['ServiceDelivery']['StopMonitoringDelivery'];
        foreach ($deliveries as $delivery) {
            if (isset($delivery['MonitoredStopVisit'])) {
                foreach ($delivery['MonitoredStopVisit'] as $visit) {
                    $journey = $visit['MonitoredVehicleJourney'] ?? [];
                    $call = $journey['MonitoredCall'] ?? [];

                    // Extraire la mission (code train RER - 4 lettres)
                    $vehicleRef = $journey['VehicleJourneyName'][0]['value'] ?? '';
                    $mission = '';
                    if (preg_match('/^[A-Z]{4}$/', $vehicleRef)) {
                        $mission = $vehicleRef;
                    }

                    $expectedTime = $call['ExpectedDepartureTime'] ?? $call['ExpectedArrivalTime'] ?? null;
                    $waitMinutes = null;
                    if ($expectedTime) {
                        $expected = new DateTime($expectedTime);
                        $now = new DateTime();
                        $diff = $now->diff($expected);
                        $waitMinutes = $diff->i + ($diff->h * 60);
                        if ($diff->invert)
                            $waitMinutes = 0;
                    }

                    // Récupérer les infos de la ligne
                    $lineRef = $journey['LineRef']['value'] ?? '';
                    $lineName = $journey['PublishedLineName'][0]['value'] ?? '';

                    // Si une ligne est spécifiée, filtrer
                    if (!empty($lineId)) {
                        // Extraire le code de ligne de lineId (ex: C01727 de IDFM:C01727)
                        $lineCode = preg_replace('/^IDFM:/', '', $lineId);
                        if (!empty($lineRef) && strpos($lineRef, $lineCode) === false) {
                            continue;
                        }
                    }

                    $departures[] = [
                        'line_name' => $lineName,
                        'line_ref' => $lineRef,
                        'mission' => $mission,
                        'direction' => $journey['DestinationName'][0]['value'] ?? '',
                        'destination' => $journey['DirectionName'][0]['value'] ?? $journey['DestinationName'][0]['value'] ?? '',
                        'expected_time' => $expectedTime,
                        'wait_minutes' => $waitMinutes,
                        'status' => $call['DepartureStatus'] ?? 'onTime',
                        'platform' => $call['ArrivalPlatformName']['value'] ?? null
                    ];
                }
            }
        }

        // Trier par temps d'attente
        usort($departures, function ($a, $b) {
            return ($a['wait_minutes'] ?? 999) - ($b['wait_minutes'] ?? 999);
        });
    }

    if (empty($departures)) {
        $error = "Aucun train prévu. Le service peut être interrompu ou la gare fermée.";
    }
} elseif ($httpCode === 401 || $httpCode === 403) {
    $error = "Clé API invalide ou expirée.";
} elseif ($httpCode === 404) {
    $error = "Gare non disponible dans l'API temps réel.";
} elseif ($httpCode === 429) {
    $error = "Quota API dépassé. Réessayez plus tard.";
} else {
    $error = "Erreur API (HTTP $httpCode)";
    if ($curlError)
        $error .= " - " . $curlError;
}

echo json_encode([
    'success' => count($departures) > 0,
    'stop_name' => $stopName,
    'stop_id' => $stopId,
    'line_id' => $lineId,
    'timestamp' => date('c'),
    'departures' => $departures,
    'count' => count($departures),
    'is_realtime' => true,
    'error' => $error,
    'debug' => isset($_GET['debug']) ? $debug : null
]);

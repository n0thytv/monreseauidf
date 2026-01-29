<?php
/**
 * Mon Réseau IDF - API Prochains passages Bus temps réel
 * Utilise l'API PRIM pour les prochains départs Bus - DONNÉES RÉELLES
 * Recherche dynamique des arrêts via l'API IDFM
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
        'error' => 'Nom d\'arrêt requis',
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

$departures = [];
$error = null;
$debug = [];
$stopId = null;

// Fonction prioritaire : rechercher un arrêt dans le cache trace-ligne
// Les IDs du cache sont compatibles avec l'API PRIM
function searchStopInTraceCache($stopName, $lineId)
{
    global $debug;

    // Extraire le code de ligne (ex: IDFM:C00170 -> C00170)
    if (empty($lineId)) {
        return null;
    }

    $lineCode = preg_replace('/^IDFM:/', '', $lineId);
    $cacheFile = __DIR__ . '/../cache/traces/' . $lineCode . '.json';

    $debug['trace_cache_file'] = basename($cacheFile);
    $debug['trace_cache_exists'] = file_exists($cacheFile);

    if (!file_exists($cacheFile)) {
        return null;
    }

    $cacheData = json_decode(file_get_contents($cacheFile), true);
    if (!$cacheData || !isset($cacheData['routes'])) {
        return null;
    }

    // Nettoyer le nom de recherche
    $searchName = preg_replace('/^(Gare de |Gare |Station |Arrêt )/i', '', $stopName);
    $searchName = trim($searchName);
    $searchLower = strtolower($searchName);
    $originalLower = strtolower($stopName);

    $debug['trace_search_name'] = $searchName;

    $foundStops = [];

    // Parcourir toutes les routes et leurs arrêts
    foreach ($cacheData['routes'] as $route) {
        foreach (($route['stops'] ?? []) as $stop) {
            $stopNameClean = strtolower($stop['stop_name'] ?? '');

            // Correspondance exacte ou partielle
            if (
                $stopNameClean === $originalLower ||
                $stopNameClean === $searchLower ||
                strpos($stopNameClean, $searchLower) !== false ||
                strpos($searchLower, $stopNameClean) !== false
            ) {

                // Extraire l'ID - peut être au format IDFM:XXXXX ou monomodalStopPlace:XXXXX
                $stopId = $stop['stop_id'] ?? $stop['id'] ?? '';

                // Extraire le numéro (3 chiffres ou plus - certains arrêts ont des IDs courts comme IDFM:2753)
                if (preg_match('/(\d{3,})/', $stopId, $m)) {
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
        // Prioriser les correspondances exactes
        usort($foundStops, function ($a, $b) use ($originalLower, $searchLower) {
            $aExact = (strtolower($a['name']) === $originalLower || strtolower($a['name']) === $searchLower) ? 0 : 1;
            $bExact = (strtolower($b['name']) === $originalLower || strtolower($b['name']) === $searchLower) ? 0 : 1;

            if ($aExact !== $bExact) {
                return $aExact - $bExact;
            }
            return strlen($a['name']) - strlen($b['name']);
        });

        $debug['trace_found_stop'] = $foundStops[0]['name'];
        $debug['trace_found_id'] = $foundStops[0]['id'];
        return $foundStops[0]['id'];
    }

    return null;
}

// Fonction pour chercher un arrêt via l'API IDFM
function searchStopId($stopName, $lineId = null)
{
    global $debug;

    // Encoder le nom pour l'URL
    $encodedName = urlencode($stopName);

    // Construire la requête de recherche
    $searchUrl = "https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/arrets-lignes/records?where=stop_name%20LIKE%20%22" . $encodedName . "%22&limit=20";

    if (!empty($lineId)) {
        // Ajouter le filtre de ligne si spécifié
        $searchUrl .= "&refine=id_line:" . urlencode($lineId);
    }

    $debug['search_url'] = $searchUrl;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $searchUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'MonReseauIDF/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $debug['search_http_code'] = $httpCode;

    if ($httpCode === 200 && !empty($response)) {
        $data = json_decode($response, true);
        if (isset($data['results']) && !empty($data['results'])) {
            // Chercher une correspondance exacte d'abord
            foreach ($data['results'] as $result) {
                if (mb_strtolower($result['stop_name'] ?? '') === mb_strtolower($stopName)) {
                    return $result['stop_id'] ?? null;
                }
            }
            // Sinon prendre le premier résultat
            return $data['results'][0]['stop_id'] ?? null;
        }
    }

    return null;
}

// Chercher l'ID de l'arrêt : d'abord dans le cache trace-ligne (IDs compatibles PRIM)
// puis fallback sur l'API IDFM si le cache ne fonctionne pas
$stopId = searchStopInTraceCache($stopName, $lineId);
$debug['source'] = $stopId ? 'trace_cache' : null;

if (!$stopId) {
    // Fallback sur l'API IDFM
    $stopId = searchStopId($stopName, $lineId);
    if ($stopId) {
        $debug['source'] = 'idfm_api';
    }
}

// Si toujours pas trouvé, essayer une recherche plus large sans le filtre de ligne
if (!$stopId && !empty($lineId)) {
    $debug['retry_without_line'] = true;
    $stopId = searchStopId($stopName);
    if ($stopId) {
        $debug['source'] = 'idfm_api_no_filter';
    }
}

// Si l'ID contient déjà STIF ou IDFM, extraire juste le numéro
if ($stopId) {
    if (preg_match('/(\d{5,})/', $stopId, $matches)) {
        $stopId = $matches[1];
    }
}

$debug['found_stop_id'] = $stopId;

if (!$stopId) {
    // Dernier recours : essayer avec un MonitoringRef générique basé sur le nom
    // Format alternatif pour certains arrêts de bus
    echo json_encode([
        'success' => false,
        'error' => 'Arrêt "' . htmlspecialchars($stopName) . '" non trouvé. Vérifiez l\'orthographe ou essayez un arrêt proche.',
        'departures' => [],
        'hint' => 'Essayez de sélectionner un autre arrêt de la liste.'
    ]);
    exit;
}

// Construire le MonitoringRef au format PRIM
// Pour les bus, plusieurs formats peuvent fonctionner
$monitoringRef = 'STIF:StopPoint:Q:' . $stopId . ':';

$debug['stop_id'] = $stopId;
$debug['monitoring_ref'] = $monitoringRef;

// Appeler l'API stop-monitoring
$primUrl = "https://prim.iledefrance-mobilites.fr/marketplace/stop-monitoring?MonitoringRef=" . urlencode($monitoringRef);

$debug['api_url'] = $primUrl;

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

$debug['http_code'] = $httpCode;

// Stocker la réponse brute pour debug
if (isset($_GET['debug'])) {
    $debug['raw_response_sample'] = substr($response, 0, 2000);
}

if ($httpCode === 200 && !empty($response)) {
    $data = json_decode($response, true);

    if (isset($data['Siri']['ServiceDelivery']['StopMonitoringDelivery'])) {
        $deliveries = $data['Siri']['ServiceDelivery']['StopMonitoringDelivery'];
        foreach ($deliveries as $delivery) {
            if (isset($delivery['MonitoredStopVisit'])) {
                foreach ($delivery['MonitoredStopVisit'] as $visit) {
                    $journey = $visit['MonitoredVehicleJourney'] ?? [];
                    $call = $journey['MonitoredCall'] ?? [];

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

                    // Filtrer par ligne si spécifié
                    $lineRef = $journey['LineRef']['value'] ?? '';
                    $lineName = $journey['PublishedLineName'][0]['value'] ?? '';

                    // Si le nom de ligne est vide, essayer de l'extraire du cache ou du lineRef
                    if (empty($lineName) && !empty($lineRef)) {
                        // Extraire le code (ex: STIF:Line::C00170: -> C00170)
                        if (preg_match('/Line::(\w+):/', $lineRef, $m)) {
                            // Chercher le nom court dans le cache lignes
                            $lignesCache = __DIR__ . '/../cache/lignes.json';
                            if (file_exists($lignesCache)) {
                                $lignesData = json_decode(file_get_contents($lignesCache), true);
                                foreach (($lignesData['records'] ?? []) as $ligne) {
                                    if (($ligne['id_line'] ?? '') === $m[1]) {
                                        $lineName = $ligne['shortname_line'] ?? $ligne['name_line'] ?? '';
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    // Si une ligne est spécifiée, ne garder que les départs de cette ligne
                    if (!empty($lineId)) {
                        // Extraire le code de ligne (ex: IDFM:C00170 -> C00170)
                        $lineCode = preg_replace('/^IDFM:/', '', $lineId);

                        // Vérifier si le lineRef contient le code de ligne ou si le nom correspond
                        $matchLine = (strpos($lineRef, $lineCode) !== false) ||
                            ($lineName === preg_replace('/^C\d+/', '', $lineCode)) ||
                            (strtolower($lineName) === strtolower($lineCode));
                        if (!$matchLine) {
                            continue; // Ignorer ce départ car ce n'est pas la bonne ligne
                        }
                    }

                    // Récupérer toutes les infos de direction disponibles
                    $destinationName = $journey['DestinationName'][0]['value'] ?? '';
                    $directionName = $journey['DirectionName'][0]['value'] ?? '';
                    $directionRef = $journey['DirectionRef']['value'] ?? '';

                    // Debug: stocker les infos de direction
                    if (isset($_GET['debug'])) {
                        $debug['direction_info'][] = [
                            'DestinationName' => $destinationName,
                            'DirectionName' => $directionName,
                            'DirectionRef' => $directionRef
                        ];
                    }

                    // Utiliser DestinationName pour la direction (c'est le terminus)
                    $direction = $destinationName;

                    // Pour les lignes en boucle: si la destination = arrêt actuel, chercher un meilleur nom
                    // depuis le cache de la ligne (utiliser le terminus opposé)
                    if (!empty($lineId) && strtolower(trim($direction)) === strtolower(trim($stopName))) {
                        $lineCode = preg_replace('/^IDFM:/', '', $lineId);
                        $lineCacheFile = __DIR__ . '/../cache/traces/' . $lineCode . '.json';

                        if (file_exists($lineCacheFile)) {
                            $lineCache = json_decode(file_get_contents($lineCacheFile), true);
                            if (!empty($lineCache['routes'])) {
                                // Chercher un terminus différent de l'arrêt actuel
                                foreach ($lineCache['routes'] as $route) {
                                    $termA = $route['terminus_a'] ?? '';
                                    $termB = $route['terminus_b'] ?? '';

                                    // Prendre le terminus qui n'est pas l'arrêt actuel
                                    if (!empty($termA) && strtolower($termA) !== strtolower($stopName)) {
                                        $direction = 'via ' . $termA;
                                        break;
                                    } elseif (!empty($termB) && strtolower($termB) !== strtolower($stopName)) {
                                        $direction = 'via ' . $termB;
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    // Fallback sur DirectionName si c'est plus qu'une lettre et direction vide
                    if (strlen($directionName) > 1 && empty($direction)) {
                        $direction = $directionName;
                    }

                    $departures[] = [
                        'line_name' => $lineName,
                        'line_ref' => $lineRef,
                        'direction' => $direction,
                        'destination' => $direction, // Même valeur pour éviter confusion
                        'expected_time' => $expectedTime,
                        'wait_minutes' => $waitMinutes,
                        'status' => $call['DepartureStatus'] ?? 'onTime',
                        'vehicle_at_stop' => $call['VehicleAtStop'] ?? false
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
        $error = "Aucun bus prévu pour le moment. Le service peut être interrompu ou l'arrêt non desservi à cette heure.";
    }
} elseif ($httpCode === 401 || $httpCode === 403) {
    $error = "Clé API invalide ou expirée.";
} elseif ($httpCode === 404) {
    $error = "Arrêt non disponible dans l'API temps réel.";
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

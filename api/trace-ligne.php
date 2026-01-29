<?php
/**
 * Mon Réseau IDF - API Tracé d'une ligne
 * Récupère le tracé géographique et les arrêts d'une ligne
 * Gère les lignes à branches multiples (RER, Transilien, etc.)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$lineId = isset($_GET['id']) ? $_GET['id'] : '';
$debug = isset($_GET['debug']);

if (empty($lineId)) {
    echo json_encode(['error' => 'ID de ligne requis', 'data' => null]);
    exit;
}

// Cache
$cacheDir = __DIR__ . '/../cache/traces';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . '/' . preg_replace('/[^a-zA-Z0-9]/', '_', $lineId) . '.json';
$cacheDuration = 3600 * 24;

if (!$debug && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheDuration) {
    echo file_get_contents($cacheFile);
    exit;
}

$traces = [];
$stops = [];
$branches = [];
$errors = [];

// Récupérer le shortname et le mode de la ligne depuis le cache
$shortName = null;
$lineMode = null;
$lineInfo = null;
$linesCache = __DIR__ . '/../cache/lignes.json';
if (file_exists($linesCache)) {
    $linesData = json_decode(file_get_contents($linesCache), true);
    if (isset($linesData['records'])) {
        // Nettoyer l'ID pour la comparaison (avec ou sans préfixe IDFM:)
        $cleanLineId = str_replace('IDFM:', '', $lineId);

        foreach ($linesData['records'] as $line) {
            $cacheId = str_replace('IDFM:', '', $line['id_line'] ?? '');
            if ($cacheId === $cleanLineId || $cacheId === $lineId) {
                $shortName = $line['shortname_line'] ?? null;
                $lineMode = strtolower($line['transportmode'] ?? 'bus');
                $lineInfo = $line;
                break;
            }
        }
    }
}


if ($debug) {
    $errors[] = "Line shortname: " . ($shortName ?? 'not found') . ", mode: " . ($lineMode ?? 'unknown');
}

// Déterminer si c'est une ligne à branches potentielles
$isMultiBranchLine = in_array($lineMode, ['rer', 'rail', 'train', 'transilien']);

// 1. Récupérer le tracé (GeoJSON) via le dataset général (plus fiable)
// S'assurer que l'ID a le format IDFM:XXXX
$searchId = (strpos($lineId, 'IDFM:') === 0) ? $lineId : 'IDFM:' . $lineId;
$traceUrl = "https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/traces-des-lignes-de-transport-en-commun-idfm/records?limit=50&refine=route_id%3A" . urlencode($searchId);

if ($debug)
    $errors[] = "Trace URL: " . $traceUrl;

if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $traceUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MonReseauIDF/1.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($debug)
        $errors[] = "Trace API: HTTP $httpCode";

    curl_close($ch);

    if ($httpCode === 200 && !empty($response)) {
        $data = json_decode($response, true);
        if (isset($data['results']) && !empty($data['results'])) {
            // Fusionner tous les tracés en un seul MultiLineString
            $mergedCoords = [];
            $baseProps = null;

            foreach ($data['results'] as $trace) {
                if (!$baseProps)
                    $baseProps = $trace;

                // Adapter selon le dataset
                $shapeObj = $trace['geo_shape'] ?? $trace['shape'] ?? null;

                if (isset($shapeObj['geometry'])) {
                    $g = $shapeObj['geometry'];
                    if ($g['type'] === 'LineString') {
                        $mergedCoords[] = $g['coordinates'];
                    } elseif ($g['type'] === 'MultiLineString') {
                        foreach ($g['coordinates'] as $seg) {
                            $mergedCoords[] = $seg;
                        }
                    }
                }
            }

            // Créer un objet trace unifié
            if ($baseProps && !empty($mergedCoords)) {
                $unifiedTrace = $baseProps;
                // Normaliser la structure shape
                $unifiedTrace['shape'] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'MultiLineString',
                        'coordinates' => $mergedCoords
                    ],
                    'properties' => []
                ];

                $traces[] = [
                    'id' => $unifiedTrace['route_id'] ?? $lineId,
                    'route_id' => $unifiedTrace['route_id'] ?? $lineId,
                    'shape' => $unifiedTrace['shape'],
                    'color' => $unifiedTrace['route_color'] ?? '000000',
                    'operatorname' => $unifiedTrace['operatorname'] ?? '',
                    'shortname' => $unifiedTrace['route_short_name'] ?? ''
                ];
            }
        }
    }
    if ($debug)
        $errors[] = "Traces merged total segments: " . count($mergedCoords ?? []);
}

// 2. Récupérer les arrêts depuis l'API IDFM
// Pour les métros, RER et trains, on utilise l'ID de ligne (plus précis) car le shortname "C" peut matcher des bus
$isRailMode = ($lineMode === 'metro' || $lineMode === 'rer' || $lineMode === 'rail' || $lineMode === 'tram');

if ($isRailMode) {

    // Nettoyer l'ID
    $cleanId = str_replace('IDFM:', '', $lineId);
    $searchId = 'IDFM:' . $cleanId;

    // Essai principal : Utiliser WHERE avec égalité stricte et LIMIT 100 (pagination nécessaire)
    $baseUrl = "https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/arrets-lignes/records?where=id%3D%22" . urlencode($searchId) . "%22";
} else {
    // Pour les autres modes
    $stopsShortName = $shortName ?? $lineId;
    $baseUrl = "https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/arrets-lignes/records?where=shortname%3D%22" . urlencode($stopsShortName) . "%22";
}

$rawStops = [];
$offset = 0;
$limit = 100;
$totalCount = 0;
$maxPages = 6; // Sécurité : max 600 arrêts

if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MonReseauIDF/1.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

    for ($page = 0; $page < $maxPages; $page++) {
        $stopsUrl = $baseUrl . "&limit=$limit&offset=$offset";
        if ($debug || true)
            $errors[] = "Page $page URL: $stopsUrl";

        curl_setopt($ch, CURLOPT_URL, $stopsUrl);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($debug || true)
            $errors[] = "Page $page HTTP: $httpCode";

        if ($httpCode !== 200 || empty($response)) {
            break;
        }

        $data = json_decode($response, true);
        if (empty($data['results'])) {
            break;
        }

        foreach ($data['results'] as $stop) {
            $lat = $stop['stop_lat'] ?? $stop['pointgeo']['lat'] ?? null;
            $lon = $stop['stop_lon'] ?? $stop['pointgeo']['lon'] ?? null;
            $rawStops[] = [
                'id' => $stop['stop_id'] ?? $stop['id'] ?? '',
                'route_id' => $stop['id'] ?? '',
                'stop_name' => $stop['stop_name'] ?? 'Station',
                'stop_lat' => $lat,
                'stop_lon' => $lon,
                'commune' => $stop['nom_commune'] ?? ''
            ];
        }

        $totalCount = $data['total_count'] ?? 0;
        $offset += $limit;

        if (count($rawStops) >= $totalCount) {
            break;
        }
    }
    curl_close($ch);
}
$errors[] = "Stops found total: " . count($rawStops);

// 3. Organiser les données - grouper par route pour les lignes multi-trajets
$routeStops = [];
foreach ($rawStops as $stop) {
    $routeId = $stop['route_id'];
    if (!isset($routeStops[$routeId])) {
        $routeStops[$routeId] = [];
    }
    $routeStops[$routeId][] = $stop;
}

/**
 * Ordonne les arrêts le long d'un tracé GeoJSON
 * Calcule la position de chaque arrêt sur la polyline et les trie
 */
function orderStopsAlongRoute($stops, $shape)
{
    if (empty($stops) || empty($shape)) {
        return $stops;
    }

    // Extraire les coordonnées du tracé
    $lineCoords = [];
    if (isset($shape['geometry']['coordinates'])) {
        $coords = $shape['geometry']['coordinates'];
        // Gérer MultiLineString et LineString
        if ($shape['geometry']['type'] === 'MultiLineString') {
            // IMPORTANT: Pour les bus, le MultiLineString peut contenir ALLER et RETOUR
            // On prend UNIQUEMENT le premier segment (aller)
            // Les autres segments représentent souvent le trajet retour
            if (!empty($coords[0])) {
                foreach ($coords[0] as $coord) {
                    $lineCoords[] = ['lon' => $coord[0], 'lat' => $coord[1]];
                }
            }
        } elseif ($shape['geometry']['type'] === 'LineString') {
            foreach ($coords as $coord) {
                $lineCoords[] = ['lon' => $coord[0], 'lat' => $coord[1]];
            }
        }
    }

    if (empty($lineCoords)) {
        return $stops;
    }

    // Calculer la distance cumulative le long du tracé
    $cumulativeDistances = [0];
    for ($i = 1; $i < count($lineCoords); $i++) {
        $dist = haversineDistance(
            $lineCoords[$i - 1]['lat'],
            $lineCoords[$i - 1]['lon'],
            $lineCoords[$i]['lat'],
            $lineCoords[$i]['lon']
        );
        $cumulativeDistances[$i] = $cumulativeDistances[$i - 1] + $dist;
    }

    // Pour chaque arrêt, trouver sa position le long du tracé
    $stopsWithPosition = [];
    foreach ($stops as $stop) {
        $stopLat = floatval($stop['stop_lat']);
        $stopLon = floatval($stop['stop_lon']);

        if (!$stopLat || !$stopLon) {
            $stopsWithPosition[] = ['stop' => $stop, 'position' => PHP_FLOAT_MAX];
            continue;
        }

        $minDist = PHP_FLOAT_MAX;
        $bestPosition = 0;

        // Trouver le segment le plus proche
        for ($i = 0; $i < count($lineCoords) - 1; $i++) {
            $p1 = $lineCoords[$i];
            $p2 = $lineCoords[$i + 1];

            // Projeter l'arrêt sur le segment
            $projection = projectPointOnSegment(
                $stopLat,
                $stopLon,
                $p1['lat'],
                $p1['lon'],
                $p2['lat'],
                $p2['lon']
            );

            $dist = haversineDistance($stopLat, $stopLon, $projection['lat'], $projection['lon']);

            if ($dist < $minDist) {
                $minDist = $dist;
                // Position = distance cumulative jusqu'au début du segment + fraction du segment
                $segmentLength = $cumulativeDistances[$i + 1] - $cumulativeDistances[$i];
                $fractionAlongSegment = $projection['fraction'];
                $bestPosition = $cumulativeDistances[$i] + ($fractionAlongSegment * $segmentLength);
            }
        }

        $stopsWithPosition[] = ['stop' => $stop, 'position' => $bestPosition];
    }

    // Trier par position le long du tracé
    usort($stopsWithPosition, function ($a, $b) {
        return $a['position'] <=> $b['position'];
    });

    // Retourner les arrêts ordonnés
    return array_map(function ($item) {
        return $item['stop'];
    }, $stopsWithPosition);
}

/**
 * Calcule la distance en km entre deux points (formule Haversine)
 */
function haversineDistance($lat1, $lon1, $lat2, $lon2)
{
    $R = 6371; // Rayon de la Terre en km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

/**
 * Projette un point sur un segment et retourne la position projetée
 */
function projectPointOnSegment($pLat, $pLon, $aLat, $aLon, $bLat, $bLon)
{
    // Convertir en coordonnées plates approximatives
    $cosLat = cos(deg2rad($pLat));
    $ax = $aLon * $cosLat;
    $ay = $aLat;
    $bx = $bLon * $cosLat;
    $by = $bLat;
    $px = $pLon * $cosLat;
    $py = $pLat;

    // Vecteur du segment
    $dx = $bx - $ax;
    $dy = $by - $ay;

    // Si le segment est un point
    $segLengthSq = $dx * $dx + $dy * $dy;
    if ($segLengthSq < 0.0000001) {
        return ['lat' => $aLat, 'lon' => $aLon, 'fraction' => 0];
    }

    // Paramètre t de la projection (0 = point A, 1 = point B)
    $t = (($px - $ax) * $dx + ($py - $ay) * $dy) / $segLengthSq;

    // Limiter t entre 0 et 1 (projection sur le segment, pas la droite)
    $t = max(0, min(1, $t));

    // Point projeté
    $projLon = ($ax + $t * $dx) / $cosLat;
    $projLat = $ay + $t * $dy;

    return ['lat' => $projLat, 'lon' => $projLon, 'fraction' => $t];
}

/**
 * Trouve l'arrêt le plus proche d'une coordonnée GPS
 */
function findClosestStop($stops, $lat, $lon)
{
    if (empty($stops))
        return null;

    $closestStop = null;
    $minDist = PHP_FLOAT_MAX;

    foreach ($stops as $stop) {
        $stopLat = floatval($stop['stop_lat'] ?? 0);
        $stopLon = floatval($stop['stop_lon'] ?? 0);

        if ($stopLat && $stopLon) {
            $dist = haversineDistance($lat, $lon, $stopLat, $stopLon);
            if ($dist < $minDist) {
                $minDist = $dist;
                $closestStop = $stop;
            }
        }
    }

    return $closestStop;
}

/**
 * Normalise un nom d'arrêt pour comparer et dédupliquer
 * - Enlève les accents
 * - Met en minuscules
 * - Enlève les mots comme "de", "la", "le", "les", "du"
 * - Enlève les tirets et espaces multiples
 */
function normalizeStopName($name)
{
    if (empty($name))
        return '';

    // Enlever les accents
    $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

    // Mettre en minuscules
    $name = strtolower($name);

    // Enlever les caractères spéciaux sauf lettres et chiffres
    $name = preg_replace('/[^a-z0-9\s]/', ' ', $name);

    // Enlever les mots communs qui créent des variantes
    $name = preg_replace('/\b(de|du|la|le|les|l|d|gare)\b/', '', $name);

    // Normaliser les espaces
    $name = preg_replace('/\s+/', '', $name);

    return trim($name);
}

// Créer les routes/branches avec leurs arrêts
$routes = [];
foreach ($traces as $index => $trace) {
    $routeId = $trace['route_id'];
    $traceStops = $routeStops[$routeId] ?? [];

    // Si pas de stops spécifiques pour cette route, utiliser tous les stops
    if (empty($traceStops)) {
        $traceStops = $rawStops;
    }

    // Dédupliquer les stops de cette route par nom
    $uniqueRouteStops = [];
    $seenNames = [];
    foreach ($traceStops as $stop) {
        if (!isset($seenNames[$stop['stop_name']])) {
            $seenNames[$stop['stop_name']] = true;
            $uniqueRouteStops[] = $stop;
        }
    }

    // IMPORTANT: Ordonner les arrêts le long du tracé
    if (!empty($trace['shape']) && !empty($uniqueRouteStops)) {
        $uniqueRouteStops = orderStopsAlongRoute($uniqueRouteStops, $trace['shape']);
    }

    if (empty($uniqueRouteStops) && !empty($trace['shape'])) {
        // Si pas de stops mais un tracé, créer quand même la route
        $routes[] = [
            'id' => $index + 1,
            'route_id' => $routeId,
            'name' => $trace['route_name'] ?: "Trajet " . ($index + 1),
            'terminus_a' => '',
            'terminus_b' => '',
            'stops' => [],
            'stops_count' => 0,
            'shape' => $trace['shape'],
            'color' => $trace['color']
        ];
    } elseif (!empty($uniqueRouteStops)) {
        $firstStop = reset($uniqueRouteStops);
        $lastStop = end($uniqueRouteStops);

        $routes[] = [
            'id' => $index + 1,
            'route_id' => $routeId,
            'name' => $firstStop['stop_name'] . ' ↔ ' . $lastStop['stop_name'],
            'terminus_a' => $firstStop['stop_name'],
            'terminus_b' => $lastStop['stop_name'],
            'stops' => $uniqueRouteStops,
            'stops_count' => count($uniqueRouteStops),
            'shape' => $trace['shape'],
            'color' => $trace['color']
        ];
    }
}

// Tous les stops uniques pour l'affichage global (dédupliquer par nom)
$uniqueStops = [];
$seenStopNames = [];
foreach ($rawStops as $stop) {
    if (!isset($seenStopNames[$stop['stop_name']])) {
        $seenStopNames[$stop['stop_name']] = true;
        $uniqueStops[] = $stop;
    }
}
$stops = $uniqueStops;

// 4. Si pas d'arrêts, utiliser les données statiques pour les métros RATP
if (empty($stops) && strpos($lineId, 'C013') === 0) {
    $metroStations = getMetroStations($lineId);
    if (!empty($metroStations)) {
        $stops = $metroStations;
    }
}

// Pour les métros : traitement simplifié (pas de multi-routes)
$isMetro = ($lineMode === 'metro');

if ($isMetro) {
    // Métro = ligne simple
    $singleTrace = !empty($traces) ? $traces[0] : null;
    $routes = [];

    if (!empty($stops)) {
        $firstStop = reset($stops);
        $lastStop = end($stops);
        $routes = [
            [
                'id' => 1,
                'route_id' => $singleTrace['route_id'] ?? $lineId,
                'name' => ($firstStop['stop_name'] ?? 'Terminus A') . ' ↔ ' . ($lastStop['stop_name'] ?? 'Terminus B'),
                'terminus_a' => $firstStop['stop_name'] ?? '',
                'terminus_b' => $lastStop['stop_name'] ?? '',
                'stops' => $stops,
                'stops_count' => count($stops),
                'shape' => $singleTrace['shape'] ?? null,
                'color' => $singleTrace['color'] ?? null
            ]
        ];
    }
    $isMultiRoute = false;
} elseif ($lineMode === 'bus' || $lineMode === 'tram') {
    // TRAITEMENT SPÉCIAL POUR LES BUS ET TRAMS
    // Gère les lignes classiques (aller-retour) et les lignes en boucle

    $singleTrace = !empty($traces) ? $traces[0] : null;
    $shape = $singleTrace['shape'] ?? null;
    $routes = [];

    // Dédupliquer les arrêts par nom normalisé (enlève les variations mineures)
    $normalizedStops = [];
    $seenNormalized = [];
    foreach ($stops as $stop) {
        $normalizedName = normalizeStopName($stop['stop_name']);
        if (!isset($seenNormalized[$normalizedName])) {
            $seenNormalized[$normalizedName] = true;
            $normalizedStops[] = $stop;
        }
    }
    $stops = $normalizedStops;

    // Ordonner les stops le long du tracé complet
    if (!empty($shape) && !empty($stops)) {
        $orderedStops = orderStopsAlongRoute($stops, $shape);
    } else {
        $orderedStops = $stops;
    }

    if (!empty($orderedStops)) {
        $firstStop = reset($orderedStops);
        $lastStop = end($orderedStops);

        // Détecter si c'est une ligne en boucle
        // Méthode 1: le premier et dernier arrêt ont le même nom
        $isLoop = (normalizeStopName($firstStop['stop_name']) === normalizeStopName($lastStop['stop_name']));

        // Méthode 2: le premier et dernier arrêt sont géographiquement proches (< 1km)
        if (!$isLoop && count($orderedStops) >= 5) {
            $firstLat = floatval($firstStop['stop_lat'] ?? 0);
            $firstLon = floatval($firstStop['stop_lon'] ?? 0);
            $lastLat = floatval($lastStop['stop_lat'] ?? 0);
            $lastLon = floatval($lastStop['stop_lon'] ?? 0);

            if ($firstLat && $firstLon && $lastLat && $lastLon) {
                $distance = haversineDistance($firstLat, $firstLon, $lastLat, $lastLon);
                // Si distance < 1km entre premier et dernier arrêt, c'est probablement une boucle
                if ($distance < 1.0) {
                    $isLoop = true;
                }
            }
        }

        // Méthode 3: vérifier dans les données brutes (avant déduplication) si un arrêt se répète
        if (!$isLoop && count($rawStops) >= 5) {
            $rawNames = array_map(function ($s) {
                return normalizeStopName($s['stop_name']);
            }, $rawStops);
            $counts = array_count_values($rawNames);
            foreach ($counts as $count) {
                if ($count > 1) {
                    $isLoop = true;
                    break;
                }
            }
        }

        if ($isLoop) {
            // LIGNE EN BOUCLE : créer une seule route "Boucle via [arrêt opposé]"

            // Trouver le terminus principal (gare en priorité, puis mairie, etc.)
            $mainTerminus = null;
            // Chercher par priorité : gare d'abord (le plus important), puis les autres
            $keywordGroups = [
                ['gare'],  // Priorité 1 : les gares
                ['mairie', 'centre commercial'],  // Priorité 2
                ['station', 'hôpital', 'hopital']  // Priorité 3
            ];

            foreach ($keywordGroups as $keywords) {
                foreach ($orderedStops as $stop) {
                    $nameLower = strtolower($stop['stop_name']);
                    foreach ($keywords as $keyword) {
                        if (strpos($nameLower, $keyword) !== false) {
                            $mainTerminus = $stop;
                            break 3;  // Sortir des 3 boucles
                        }
                    }
                }
            }

            // Si pas de terminus principal trouvé, prendre le premier arrêt
            if (!$mainTerminus) {
                $mainTerminus = $firstStop;
            }

            // Trouver l'arrêt opposé (le plus loin géographiquement du terminus principal)
            $maxDistance = 0;
            $oppositeStop = null;
            $mainLat = floatval($mainTerminus['stop_lat'] ?? 0);
            $mainLon = floatval($mainTerminus['stop_lon'] ?? 0);

            if ($mainLat && $mainLon) {
                foreach ($orderedStops as $stop) {
                    $stopLat = floatval($stop['stop_lat'] ?? 0);
                    $stopLon = floatval($stop['stop_lon'] ?? 0);
                    if ($stopLat && $stopLon) {
                        $dist = haversineDistance($mainLat, $mainLon, $stopLat, $stopLon);
                        if ($dist > $maxDistance) {
                            $maxDistance = $dist;
                            $oppositeStop = $stop;
                        }
                    }
                }
            }

            if (!$oppositeStop) {
                $middleIndex = intval(count($orderedStops) / 2);
                $oppositeStop = $orderedStops[$middleIndex];
            }

            $routes = [
                [
                    'id' => 1,
                    'route_id' => ($singleTrace['route_id'] ?? $lineId) . '_boucle',
                    'name' => $mainTerminus['stop_name'] . ' ↔ ' . $oppositeStop['stop_name'],
                    'terminus_a' => $mainTerminus['stop_name'],
                    'terminus_b' => $oppositeStop['stop_name'],
                    'is_loop' => true,
                    'stops' => $orderedStops,
                    'stops_count' => count($orderedStops),
                    'shape' => $shape,
                    'color' => $singleTrace['color'] ?? null
                ]
            ];
            $isMultiRoute = false;
        } else {
            // LIGNE CLASSIQUE : créer 2 directions (aller et retour)
            $routes = [
                [
                    'id' => 1,
                    'route_id' => ($singleTrace['route_id'] ?? $lineId) . '_aller',
                    'name' => $firstStop['stop_name'] . ' → ' . $lastStop['stop_name'],
                    'terminus_a' => $firstStop['stop_name'],
                    'terminus_b' => $lastStop['stop_name'],
                    'stops' => $orderedStops,
                    'stops_count' => count($orderedStops),
                    'shape' => $shape,
                    'color' => $singleTrace['color'] ?? null
                ],
                [
                    'id' => 2,
                    'route_id' => ($singleTrace['route_id'] ?? $lineId) . '_retour',
                    'name' => $lastStop['stop_name'] . ' → ' . $firstStop['stop_name'],
                    'terminus_a' => $lastStop['stop_name'],
                    'terminus_b' => $firstStop['stop_name'],
                    'stops' => array_reverse($orderedStops),
                    'stops_count' => count($orderedStops),
                    'shape' => $shape,
                    'color' => $singleTrace['color'] ?? null
                ]
            ];
            $isMultiRoute = true;
        }
    } else {
        $routes = [];
        $isMultiRoute = false;
    }
} elseif ($lineMode === 'rer') {
    // TRAITEMENT SPÉCIAL POUR LES RER avec MISSIONS COMMERCIALES
    // Le RER C utilise des missions commerciales prédéfinies (SNCF)
    // Les autres RER utilisent la détection automatique de branches

    $singleTrace = !empty($traces) ? $traces[0] : null;
    $shape = $singleTrace['shape'] ?? null;
    $routes = [];

    // Dédupliquer les arrêts par nom normalisé
    $normalizedStops = [];
    $seenNormalized = [];
    foreach ($stops as $stop) {
        $normalizedName = normalizeStopName($stop['stop_name']);
        if (!isset($seenNormalized[$normalizedName])) {
            $seenNormalized[$normalizedName] = true;
            $normalizedStops[] = $stop;
        }
    }
    $stops = $normalizedStops;

    // === MISSIONS COMMERCIALES DEPUIS LE CACHE GTFS ===
    // Chargement dynamique des missions extraites du GTFS IDFM
    $commercialMissions = [];

    // Extraire l'ID de ligne sans préfixe IDFM:
    $cleanLineId = str_replace('IDFM:', '', $lineId);

    // Charger le cache des missions RER si disponible
    $missionsCacheFile = dirname(__DIR__) . '/cache/rer_missions_full.json';
    if (file_exists($missionsCacheFile)) {
        $missionsCache = json_decode(file_get_contents($missionsCacheFile), true);

        // Chercher les missions pour cette ligne RER
        if (isset($missionsCache[$cleanLineId])) {
            foreach ($missionsCache[$cleanLineId] as $mission) {
                $commercialMissions[] = [
                    'terminus_a' => $mission['terminus_a'],
                    'terminus_b' => $mission['terminus_b'],
                    'code' => $mission['code']
                ];
            }
            if ($debug)
                $errors[] = "Loaded " . count($commercialMissions) . " missions from GTFS cache for line $cleanLineId";
        }
    } else {
        if ($debug)
            $errors[] = "Missions cache not found: $missionsCacheFile";
    }

    // Si des missions commerciales sont définies, les utiliser
    if (!empty($commercialMissions)) {
        $routeId = 1;

        // Indexer les arrêts par nom pour recherche rapide
        $stopsByName = [];
        foreach ($stops as $stop) {
            $stopsByName[$stop['stop_name']] = $stop;
        }

        // Créer une copie pour recherche approchée
        $stopNames = array_keys($stopsByName);

        foreach ($commercialMissions as $mission) {
            // Trouver les arrêts terminus (correspondance améliorée)
            $terminusA = null;
            $terminusB = null;

            // Fonction de recherche améliorée avec normalisation et correspondance flexible
            $findTerminus = function ($searchName) use ($stopNames, $stopsByName) {
                if (empty($searchName))
                    return null;

                // Fonction de normalisation pour la comparaison
                $normalize = function ($str) {
                    $str = mb_strtolower($str, 'UTF-8');
                    // Supprimer accents
                    $str = preg_replace('/[àáâãäå]/u', 'a', $str);
                    $str = preg_replace('/[èéêë]/u', 'e', $str);
                    $str = preg_replace('/[ìíîï]/u', 'i', $str);
                    $str = preg_replace('/[òóôõö]/u', 'o', $str);
                    $str = preg_replace('/[ùúûü]/u', 'u', $str);
                    $str = preg_replace('/[ÿý]/u', 'y', $str);
                    $str = preg_replace('/[ç]/u', 'c', $str);
                    $str = preg_replace('/[ñ]/u', 'n', $str);
                    // Supprimer caractères spéciaux
                    $str = preg_replace('/[^a-z0-9\s]/u', ' ', $str);
                    $str = preg_replace('/\s+/', ' ', $str);
                    return trim($str);
                };

                $searchNorm = $normalize($searchName);
                $searchWords = array_filter(explode(' ', $searchNorm), fn($w) => strlen($w) >= 3);

                // 1. Correspondance exacte
                if (isset($stopsByName[$searchName])) {
                    return $stopsByName[$searchName];
                }

                // 2. Correspondance exacte normalisée
                foreach ($stopNames as $name) {
                    if ($normalize($name) === $searchNorm) {
                        return $stopsByName[$name];
                    }
                }

                // 3. Le nom normalisé de l'arrêt contient le terme recherché normalisé
                foreach ($stopNames as $name) {
                    if (stripos($normalize($name), $searchNorm) !== false) {
                        return $stopsByName[$name];
                    }
                }

                // 4. Le nom normalisé de l'arrêt commence par le terme recherché
                foreach ($stopNames as $name) {
                    if (strpos($normalize($name), $searchNorm) === 0) {
                        return $stopsByName[$name];
                    }
                }

                // 5. Correspondance par mots clés (tous les mots de la recherche présents)
                if (count($searchWords) >= 1) {
                    foreach ($stopNames as $name) {
                        $nameNorm = $normalize($name);
                        $allFound = true;
                        foreach ($searchWords as $word) {
                            if (stripos($nameNorm, $word) === false) {
                                $allFound = false;
                                break;
                            }
                        }
                        if ($allFound) {
                            return $stopsByName[$name];
                        }
                    }
                }

                // 6. Correspondance par premier mot significatif (>= 4 lettres)
                $searchFirst = reset($searchWords);
                if ($searchFirst && strlen($searchFirst) >= 4) {
                    foreach ($stopNames as $name) {
                        $nameNorm = $normalize($name);
                        if (strpos($nameNorm, $searchFirst) === 0) {
                            return $stopsByName[$name];
                        }
                    }
                    // 6b. Le premier mot est contenu dans le nom
                    foreach ($stopNames as $name) {
                        $nameNorm = $normalize($name);
                        if (strpos($nameNorm, $searchFirst) !== false) {
                            return $stopsByName[$name];
                        }
                    }
                }

                return null;
            };

            $terminusA = $findTerminus($mission['terminus_a']);
            $terminusB = $findTerminus($mission['terminus_b']);

            if (!$terminusA || !$terminusB) {
                if ($debug)
                    $errors[] = "Mission {$mission['code']}: terminus not found (A: {$mission['terminus_a']}, B: {$mission['terminus_b']})";
                continue;
            }

            // Calculer les arrêts entre les deux terminus
            // On utilise le tracé complet pour ordonner tous les arrêts
            if (!empty($shape)) {
                $orderedAllStops = orderStopsAlongRoute($stops, $shape);
            } else {
                $orderedAllStops = $stops;
            }

            // Trouver les positions des terminus dans la liste ordonnée
            $posA = -1;
            $posB = -1;
            foreach ($orderedAllStops as $idx => $stop) {
                if ($stop['stop_name'] === $terminusA['stop_name'])
                    $posA = $idx;
                if ($stop['stop_name'] === $terminusB['stop_name'])
                    $posB = $idx;
            }

            if ($posA === -1 || $posB === -1) {
                if ($debug)
                    $errors[] = "Mission {$mission['code']}: positions not found";
                continue;
            }

            // Extraire les arrêts entre les deux terminus
            $startPos = min($posA, $posB);
            $endPos = max($posA, $posB);
            $missionStops = array_slice($orderedAllStops, $startPos, $endPos - $startPos + 1);

            // Si le terminus A était après B, inverser l'ordre
            if ($posA > $posB) {
                $missionStops = array_reverse($missionStops);
            }

            if (count($missionStops) < 3) {
                if ($debug)
                    $errors[] = "Mission {$mission['code']}: too few stops (" . count($missionStops) . ")";
                continue;
            }

            $routes[] = [
                'id' => $routeId,
                'route_id' => ($singleTrace['route_id'] ?? $lineId) . '_' . strtolower($mission['code']),
                'name' => $terminusA['stop_name'] . ' ↔ ' . $terminusB['stop_name'],
                'terminus_a' => $terminusA['stop_name'],
                'terminus_b' => $terminusB['stop_name'],
                'mission_code' => $mission['code'],
                'stops' => $missionStops,
                'stops_count' => count($missionStops),
                'shape' => $shape, // Utiliser le tracé complet pour chaque mission
                'color' => $singleTrace['color'] ?? null
            ];
            $routeId++;

            if ($debug)
                $errors[] = "Mission {$mission['code']}: {$terminusA['stop_name']} ↔ {$terminusB['stop_name']} (" . count($missionStops) . " stops)";
        }
    }

    // === DÉTECTION AUTOMATIQUE POUR LES AUTRES RER ===
    // Si pas de missions commerciales définies, utiliser l'algorithme de segments
    if (empty($routes) && $shape && isset($shape['geometry']['type']) && $shape['geometry']['type'] === 'MultiLineString') {
        $segments = $shape['geometry']['coordinates'];

        if ($debug)
            $errors[] = "Using automatic branch detection - Segments found: " . count($segments);

        $branchesRaw = [];

        foreach ($segments as $segmentIndex => $segmentCoords) {
            $cnt = count($segmentCoords);
            if ($cnt < 3)
                continue;

            $startCoord = $segmentCoords[0];
            $endCoord = $segmentCoords[$cnt - 1];

            $startKey = round($startCoord[0], 2) . '_' . round($startCoord[1], 2);
            $endKey = round($endCoord[0], 2) . '_' . round($endCoord[1], 2);
            $pairKey = ($startKey < $endKey) ? "$startKey|$endKey" : "$endKey|$startKey";

            $segmentLength = $cnt;

            if (!isset($branchesRaw[$pairKey]) || $branchesRaw[$pairKey]['length'] < $segmentLength) {
                $branchesRaw[$pairKey] = [
                    'segment_index' => $segmentIndex,
                    'coords' => $segmentCoords,
                    'length' => $segmentLength,
                    'start_coord' => $startCoord,
                    'end_coord' => $endCoord
                ];
            }
        }

        uasort($branchesRaw, function ($a, $b) {
            return $b['length'] - $a['length'];
        });
        $branchesRaw = array_slice($branchesRaw, 0, 15, true);

        $routeId = 1;
        foreach ($branchesRaw as $pairKey => $branch) {
            $branchShape = [
                'type' => 'Feature',
                'geometry' => ['type' => 'LineString', 'coordinates' => $branch['coords']],
                'properties' => []
            ];

            $startStop = findClosestStop($rawStops, $branch['start_coord'][1], $branch['start_coord'][0]);
            $endStop = findClosestStop($rawStops, $branch['end_coord'][1], $branch['end_coord'][0]);

            $branchStops = [];
            $maxDistance = 2.0;

            foreach ($stops as $stop) {
                $stopLat = floatval($stop['stop_lat']);
                $stopLon = floatval($stop['stop_lon']);
                if (!$stopLat || !$stopLon)
                    continue;

                $minDistToLine = PHP_FLOAT_MAX;
                foreach ($branch['coords'] as $coord) {
                    $dist = haversineDistance($stopLat, $stopLon, $coord[1], $coord[0]);
                    if ($dist < $minDistToLine)
                        $minDistToLine = $dist;
                }

                if ($minDistToLine <= $maxDistance)
                    $branchStops[] = $stop;
            }

            if (!empty($branchStops)) {
                $branchStops = orderStopsAlongRoute($branchStops, $branchShape);
            }

            if (!empty($branchStops) && count($branchStops) >= 3) {
                $firstStop = reset($branchStops);
                $lastStop = end($branchStops);
                $startName = $startStop ? $startStop['stop_name'] : $firstStop['stop_name'];
                $endName = $endStop ? $endStop['stop_name'] : $lastStop['stop_name'];

                $routes[] = [
                    'id' => $routeId,
                    'route_id' => ($singleTrace['route_id'] ?? $lineId) . '_br' . $routeId,
                    'name' => $startName . ' → ' . $endName,
                    'terminus_a' => $startName,
                    'terminus_b' => $endName,
                    'stops' => $branchStops,
                    'stops_count' => count($branchStops),
                    'shape' => $branchShape,
                    'color' => $singleTrace['color'] ?? null
                ];
                $routeId++;
            }
        }
    }

    // Fallback si pas de branches extraites
    if (empty($routes) && !empty($stops)) {
        if (!empty($shape)) {
            $orderedStops = orderStopsAlongRoute($stops, $shape);
        } else {
            $orderedStops = $stops;
        }

        if (!empty($orderedStops)) {
            $firstStop = reset($orderedStops);
            $lastStop = end($orderedStops);

            $routes = [
                [
                    'id' => 1,
                    'route_id' => $singleTrace['route_id'] ?? $lineId,
                    'name' => 'Ligne RER ' . ($shortName ?? $lineId),
                    'terminus_a' => $firstStop['stop_name'],
                    'terminus_b' => $lastStop['stop_name'],
                    'stops' => $orderedStops,
                    'stops_count' => count($orderedStops),
                    'shape' => $shape,
                    'color' => $singleTrace['color'] ?? null
                ]
            ];
        }
    }

    $isMultiRoute = count($routes) > 1;
} elseif ($lineMode === 'rail') {
    // TRAITEMENT SPÉCIAL POUR LES TRANSILIENS avec BRANCHES MULTIPLES
    // Même logique améliorée que les RER

    $singleTrace = !empty($traces) ? $traces[0] : null;
    $shape = $singleTrace['shape'] ?? null;
    $routes = [];

    // Dédupliquer les arrêts par nom normalisé
    $normalizedStops = [];
    $seenNormalized = [];
    foreach ($stops as $stop) {
        $normalizedName = normalizeStopName($stop['stop_name']);
        if (!isset($seenNormalized[$normalizedName])) {
            $seenNormalized[$normalizedName] = true;
            $normalizedStops[] = $stop;
        }
    }
    $stops = $normalizedStops;

    // Extraire les branches uniques du MultiLineString
    if ($shape && isset($shape['geometry']['type']) && $shape['geometry']['type'] === 'MultiLineString') {
        $segments = $shape['geometry']['coordinates'];

        $branchesRaw = [];
        foreach ($segments as $segmentIndex => $segmentCoords) {
            if (count($segmentCoords) < 3)
                continue;

            $startCoord = $segmentCoords[0];
            $endCoord = $segmentCoords[count($segmentCoords) - 1];

            // Clés basées sur coordonnées arrondies
            $startKey = round($startCoord[0], 2) . '_' . round($startCoord[1], 2);
            $endKey = round($endCoord[0], 2) . '_' . round($endCoord[1], 2);
            $pairKey = ($startKey < $endKey) ? "$startKey|$endKey" : "$endKey|$startKey";

            $segmentLength = count($segmentCoords);

            if (!isset($branchesRaw[$pairKey]) || $branchesRaw[$pairKey]['length'] < $segmentLength) {
                $branchesRaw[$pairKey] = [
                    'segment_index' => $segmentIndex,
                    'coords' => $segmentCoords,
                    'length' => $segmentLength,
                    'start_coord' => $startCoord,
                    'end_coord' => $endCoord
                ];
            }
        }

        // Trier par longueur et limiter
        uasort($branchesRaw, function ($a, $b) {
            return $b['length'] - $a['length'];
        });
        $branchesRaw = array_slice($branchesRaw, 0, 15, true);

        $routeId = 1;
        foreach ($branchesRaw as $pairKey => $branch) {
            $branchShape = [
                'type' => 'Feature',
                'geometry' => ['type' => 'LineString', 'coordinates' => $branch['coords']],
                'properties' => []
            ];

            $startStop = findClosestStop($rawStops, $branch['start_coord'][1], $branch['start_coord'][0]);
            $endStop = findClosestStop($rawStops, $branch['end_coord'][1], $branch['end_coord'][0]);

            // Filtrer les arrêts proches du tracé
            $branchStops = [];
            $maxDistance = 2.0;
            foreach ($stops as $stop) {
                $stopLat = floatval($stop['stop_lat']);
                $stopLon = floatval($stop['stop_lon']);
                if (!$stopLat || !$stopLon)
                    continue;

                $minDistToLine = PHP_FLOAT_MAX;
                foreach ($branch['coords'] as $coord) {
                    $dist = haversineDistance($stopLat, $stopLon, $coord[1], $coord[0]);
                    if ($dist < $minDistToLine)
                        $minDistToLine = $dist;
                }
                if ($minDistToLine <= $maxDistance)
                    $branchStops[] = $stop;
            }

            if (!empty($branchStops)) {
                $branchStops = orderStopsAlongRoute($branchStops, $branchShape);
            }

            if (!empty($branchStops) && count($branchStops) >= 3) {
                $firstStop = reset($branchStops);
                $lastStop = end($branchStops);
                $startName = $startStop ? $startStop['stop_name'] : $firstStop['stop_name'];
                $endName = $endStop ? $endStop['stop_name'] : $lastStop['stop_name'];

                $routes[] = [
                    'id' => $routeId,
                    'route_id' => ($singleTrace['route_id'] ?? $lineId) . '_br' . $routeId,
                    'name' => $startName . ' → ' . $endName,
                    'terminus_a' => $startName,
                    'terminus_b' => $endName,
                    'stops' => $branchStops,
                    'stops_count' => count($branchStops),
                    'shape' => $branchShape,
                    'color' => $singleTrace['color'] ?? null
                ];
                $routeId++;
            }
        }
    }

    // Fallback
    if (empty($routes) && !empty($stops)) {
        if (!empty($shape)) {
            $orderedStops = orderStopsAlongRoute($stops, $shape);
        } else {
            $orderedStops = $stops;
        }

        if (!empty($orderedStops)) {
            $firstStop = reset($orderedStops);
            $lastStop = end($orderedStops);

            $routes = [
                [
                    'id' => 1,
                    'route_id' => $singleTrace['route_id'] ?? $lineId,
                    'name' => 'Ligne ' . ($shortName ?? $lineId),
                    'terminus_a' => $firstStop['stop_name'],
                    'terminus_b' => $lastStop['stop_name'],
                    'stops' => $orderedStops,
                    'stops_count' => count($orderedStops),
                    'shape' => $shape,
                    'color' => $singleTrace['color'] ?? null
                ]
            ];
        }
    }

    $isMultiRoute = count($routes) > 1;
} else {
    // Pour les autres modes : garder le système original
    $isMultiRoute = count($routes) > 1;

    // Si pas de routes mais des stops, créer une route simple OU traiter les branches si on a les données
    if (empty($routes) && !empty($stops)) {

        // Fusionner tous les tracés disponibles pour avoir la géométrie complète
        $mergedCoordinates = [];
        $baseTrace = null;

        if (!empty($traces)) {
            $baseTrace = $traces[0];
            foreach ($traces as $traceItem) {
                if (!isset($traceItem['shape']['geometry']['coordinates']))
                    continue;

                $type = $traceItem['shape']['geometry']['type'];
                $coords = $traceItem['shape']['geometry']['coordinates'];

                if ($type === 'LineString') {
                    $mergedCoordinates[] = $coords;
                } elseif ($type === 'MultiLineString') {
                    foreach ($coords as $segment) {
                        $mergedCoordinates[] = $segment;
                    }
                }
            }
        }

        $singleTrace = $baseTrace;
        // Reconstruire une forme unifiée
        if ($singleTrace) {
            $singleTrace['shape']['geometry']['type'] = 'MultiLineString';
            $singleTrace['shape']['geometry']['coordinates'] = $mergedCoordinates;
        }

        // Si c'est un RER ou un Train, on tente de séparer les branches maintenant qu'on a tout fusionné
        if ($isRailMode && !empty($mergedCoordinates)) {
            // ... Code de séparation des branches existant mais qui utilisera maintenant $mergedCoordinates ...
            // En fait, le code de séparation est DANS le bloc if ($isMultiRoute) qui n'est pas activé ici ?
            // Non, le code de séparation était ligne 173+ (non visible ici).
            // Ah, non, le code de séparation est plus haut.
            // Je dois m'assurer que ce bloc de fusion de traces se fait AVANT l'analyse des branches.
            // L'analyse des branches commence ligne 487 (dans trace-ligne.php étape 489).
            // Mais là je suis à la fin du fichier (fallback).

            // Attendez. Le code que je remplace ici est le FALLBACK "Si pas de routes".
            // Si je veux que l'analyse des branches (qui est plus haut) marche, il faut que je fusionne les traces AU DEBUT, juste après leur récupération (étape 1).

            // Je ne dois PAS faire cette modif ici à la fin.
            // Je dois la faire vers la ligne 107 (après récup traces).

        }

        // Fallback simple si l'analyse n'a rien donné
        $firstStop = reset($stops);
        $lastStop = end($stops);
        $routes = [
            [
                'id' => 1,
                'route_id' => $singleTrace['route_id'] ?? $lineId,
                'name' => ($firstStop['stop_name'] ?? 'Terminus A') . ' ↔ ' . ($lastStop['stop_name'] ?? 'Terminus B'),
                'terminus_a' => $firstStop['stop_name'] ?? '',
                'terminus_b' => $lastStop['stop_name'] ?? '',
                'stops' => $stops,
                'stops_count' => count($stops),
                'shape' => $singleTrace['shape'] ?? null,
                'color' => $singleTrace['color'] ?? null
            ]
        ];
        $isMultiRoute = false;
    }
}

// --- Filtrage des sous-parcours (Service partiels & Doublons sens) ---
// On ne garde que les routes dont les terminus sont inclus dans une route plus longue retenue.
// Cette méthode via terminus est plus robuste que l'inclusion totale des arrêts,
// car elle gère les cas où la mission courte est Omnibus (tous arrêts) et la longue est Directe (saute des arrêts intermédiaires).
// EXCEPTION: Les routes avec un mission_code (missions commerciales des RER) ne sont PAS filtrées
// car elles représentent des services distincts (ex: JICK, VEGA, ELBA...)

// Vérifier si on utilise des missions commerciales (toutes les routes ont un mission_code)
$hasCommercialMissions = !empty($routes) && isset($routes[0]['mission_code']);

if ($hasCommercialMissions) {
    // Pour les missions commerciales, on garde TOUTES les routes sans filtrage
    // car chaque mission représente un service distinct
    if ($debug)
        $errors[] = "Skipping sub-route filter for commercial missions (" . count($routes) . " routes)";

    // Réindexer proprement (1, 2, 3...)
    $idx = 1;
    foreach ($routes as $k => $r) {
        $routes[$k]['id'] = $idx++;
    }
} else {
    // Filtrage standard pour les lignes sans missions commerciales
    // Trier les routes par nombre d'arrêts décroissant (les plus longues d'abord)
    usort($routes, function ($a, $b) {
        return count($b['stops']) - count($a['stops']);
    });

    $filteredRoutes = [];
    foreach ($routes as $candidate) {
        $cStops = $candidate['stops'];
        if (count($cStops) < 2)
            continue;

        // Utilisation de reset/end pour être sûr d'avoir le premier et dernier élément peu importe les clés
        $firstStop = reset($cStops);
        $lastStop = end($cStops);
        $termA = $firstStop['id'];
        $termB = $lastStop['id'];

        $isSubset = false;

        foreach ($filteredRoutes as $kept) {
            $keptStopIds = array_column($kept['stops'], 'id');

            // Vérifier si les DEUX terminus de la candidate sont présents dans la route conservée
            if (in_array($termA, $keptStopIds) && in_array($termB, $keptStopIds)) {
                $isSubset = true;
                break;
            }
        }

        if (!$isSubset) {
            $filteredRoutes[] = $candidate;
        }
    }

    // Réindexer proprement (1, 2, 3...)
    foreach ($filteredRoutes as $k => $r) {
        $filteredRoutes[$k]['id'] = $k + 1;
    }

    $routes = $filteredRoutes;
}
// -----------------------------------------------------

// 5. Formater la réponse JSON
// (Le résultat est construit ci-dessous)

if ($debug) {
    $errors[] = "Routes before filtering: " . count($routes);
    $errors[] = "Routes after filtering: " . (isset($filteredRoutes) ? count($filteredRoutes) : count($routes) . " (no filtering)");
}


// Construire le résultat
$result = [
    'success' => true,
    'line_id' => $lineId,
    'shortname' => $shortName,
    'mode' => $lineMode,
    'is_metro' => $isMetro,
    'is_multi_route' => $isMultiRoute,
    'routes' => $routes,
    'stops' => $stops,
    'stops_count' => count($stops)
];

$result['debug_info'] = $errors;
$result['stops_found_count'] = count($stops);
$result['routes_count'] = count($routes);

if ($debug) {
    $result['debug'] = $errors;
}

// Sauvegarder en cache seulement si on a des données
if (!$debug && (count($stops) > 0 || count($traces) > 0)) {
    file_put_contents($cacheFile, json_encode($result));
}

echo json_encode($result);

// Stations de métro statiques (fallback)
function getMetroStations($lineId)
{
    $stations = [
        // Métro 1
        'C01371' => [
            ['stop_name' => 'La Défense', 'stop_lat' => 48.8919, 'stop_lon' => 2.2380],
            ['stop_name' => 'Esplanade de La Défense', 'stop_lat' => 48.8875, 'stop_lon' => 2.2505],
            ['stop_name' => 'Pont de Neuilly', 'stop_lat' => 48.8848, 'stop_lon' => 2.2590],
            ['stop_name' => 'Les Sablons', 'stop_lat' => 48.8811, 'stop_lon' => 2.2716],
            ['stop_name' => 'Porte Maillot', 'stop_lat' => 48.8780, 'stop_lon' => 2.2822],
            ['stop_name' => 'Argentine', 'stop_lat' => 48.8756, 'stop_lon' => 2.2893],
            ['stop_name' => 'Charles de Gaulle - Étoile', 'stop_lat' => 48.8738, 'stop_lon' => 2.2950],
            ['stop_name' => 'George V', 'stop_lat' => 48.8720, 'stop_lon' => 2.3006],
            ['stop_name' => 'Franklin D. Roosevelt', 'stop_lat' => 48.8689, 'stop_lon' => 2.3096],
            ['stop_name' => 'Champs-Élysées - Clemenceau', 'stop_lat' => 48.8677, 'stop_lon' => 2.3141],
            ['stop_name' => 'Concorde', 'stop_lat' => 48.8656, 'stop_lon' => 2.3211],
            ['stop_name' => 'Tuileries', 'stop_lat' => 48.8643, 'stop_lon' => 2.3295],
            ['stop_name' => 'Palais Royal - Musée du Louvre', 'stop_lat' => 48.8622, 'stop_lon' => 2.3367],
            ['stop_name' => 'Louvre - Rivoli', 'stop_lat' => 48.8608, 'stop_lon' => 2.3407],
            ['stop_name' => 'Châtelet', 'stop_lat' => 48.8584, 'stop_lon' => 2.3473],
            ['stop_name' => 'Hôtel de Ville', 'stop_lat' => 48.8574, 'stop_lon' => 2.3516],
            ['stop_name' => 'Saint-Paul', 'stop_lat' => 48.8551, 'stop_lon' => 2.3608],
            ['stop_name' => 'Bastille', 'stop_lat' => 48.8531, 'stop_lon' => 2.3693],
            ['stop_name' => 'Gare de Lyon', 'stop_lat' => 48.8448, 'stop_lon' => 2.3735],
            ['stop_name' => 'Reuilly - Diderot', 'stop_lat' => 48.8472, 'stop_lon' => 2.3865],
            ['stop_name' => 'Nation', 'stop_lat' => 48.8484, 'stop_lon' => 2.3959],
            ['stop_name' => 'Porte de Vincennes', 'stop_lat' => 48.8471, 'stop_lon' => 2.4107],
            ['stop_name' => 'Saint-Mandé', 'stop_lat' => 48.8463, 'stop_lon' => 2.4187],
            ['stop_name' => 'Bérault', 'stop_lat' => 48.8454, 'stop_lon' => 2.4282],
            ['stop_name' => 'Château de Vincennes', 'stop_lat' => 48.8443, 'stop_lon' => 2.4404],
        ],
        // Métro 2
        'C01372' => [
            ['stop_name' => 'Porte Dauphine', 'stop_lat' => 48.8718, 'stop_lon' => 2.2770],
            ['stop_name' => 'Victor Hugo', 'stop_lat' => 48.8696, 'stop_lon' => 2.2857],
            ['stop_name' => 'Charles de Gaulle - Étoile', 'stop_lat' => 48.8738, 'stop_lon' => 2.2950],
            ['stop_name' => 'Ternes', 'stop_lat' => 48.8782, 'stop_lon' => 2.2985],
            ['stop_name' => 'Courcelles', 'stop_lat' => 48.8797, 'stop_lon' => 2.3036],
            ['stop_name' => 'Monceau', 'stop_lat' => 48.8804, 'stop_lon' => 2.3093],
            ['stop_name' => 'Villiers', 'stop_lat' => 48.8815, 'stop_lon' => 2.3154],
            ['stop_name' => 'Rome', 'stop_lat' => 48.8823, 'stop_lon' => 2.3218],
            ['stop_name' => 'Place de Clichy', 'stop_lat' => 48.8836, 'stop_lon' => 2.3275],
            ['stop_name' => 'Blanche', 'stop_lat' => 48.8839, 'stop_lon' => 2.3325],
            ['stop_name' => 'Pigalle', 'stop_lat' => 48.8821, 'stop_lon' => 2.3374],
            ['stop_name' => 'Anvers', 'stop_lat' => 48.8829, 'stop_lon' => 2.3444],
            ['stop_name' => 'Barbès - Rochechouart', 'stop_lat' => 48.8839, 'stop_lon' => 2.3495],
            ['stop_name' => 'La Chapelle', 'stop_lat' => 48.8844, 'stop_lon' => 2.3593],
            ['stop_name' => 'Stalingrad', 'stop_lat' => 48.8841, 'stop_lon' => 2.3686],
            ['stop_name' => 'Jaurès', 'stop_lat' => 48.8820, 'stop_lon' => 2.3706],
            ['stop_name' => 'Colonel Fabien', 'stop_lat' => 48.8772, 'stop_lon' => 2.3702],
            ['stop_name' => 'Belleville', 'stop_lat' => 48.8720, 'stop_lon' => 2.3769],
            ['stop_name' => 'Couronnes', 'stop_lat' => 48.8694, 'stop_lon' => 2.3803],
            ['stop_name' => 'Ménilmontant', 'stop_lat' => 48.8666, 'stop_lon' => 2.3836],
            ['stop_name' => 'Père Lachaise', 'stop_lat' => 48.8623, 'stop_lon' => 2.3869],
            ['stop_name' => 'Philippe Auguste', 'stop_lat' => 48.8582, 'stop_lon' => 2.3897],
            ['stop_name' => 'Alexandre Dumas', 'stop_lat' => 48.8562, 'stop_lon' => 2.3945],
            ['stop_name' => 'Avron', 'stop_lat' => 48.8517, 'stop_lon' => 2.3984],
            ['stop_name' => 'Nation', 'stop_lat' => 48.8484, 'stop_lon' => 2.3959],
        ],
        // Métro 3
        'C01373' => [
            ['stop_name' => 'Pont de Levallois - Bécon', 'stop_lat' => 48.8978, 'stop_lon' => 2.2808],
            ['stop_name' => 'Anatole France', 'stop_lat' => 48.8923, 'stop_lon' => 2.2856],
            ['stop_name' => 'Louise Michel', 'stop_lat' => 48.8889, 'stop_lon' => 2.2881],
            ['stop_name' => 'Porte de Champerret', 'stop_lat' => 48.8857, 'stop_lon' => 2.2932],
            ['stop_name' => 'Pereire', 'stop_lat' => 48.8847, 'stop_lon' => 2.2985],
            ['stop_name' => 'Wagram', 'stop_lat' => 48.8840, 'stop_lon' => 2.3047],
            ['stop_name' => 'Malesherbes', 'stop_lat' => 48.8829, 'stop_lon' => 2.3107],
            ['stop_name' => 'Villiers', 'stop_lat' => 48.8815, 'stop_lon' => 2.3154],
            ['stop_name' => 'Europe', 'stop_lat' => 48.8787, 'stop_lon' => 2.3221],
            ['stop_name' => 'Saint-Lazare', 'stop_lat' => 48.8753, 'stop_lon' => 2.3250],
            ['stop_name' => 'Havre - Caumartin', 'stop_lat' => 48.8735, 'stop_lon' => 2.3283],
            ['stop_name' => 'Opéra', 'stop_lat' => 48.8706, 'stop_lon' => 2.3316],
            ['stop_name' => 'Quatre-Septembre', 'stop_lat' => 48.8695, 'stop_lon' => 2.3368],
            ['stop_name' => 'Bourse', 'stop_lat' => 48.8686, 'stop_lon' => 2.3412],
            ['stop_name' => 'Sentier', 'stop_lat' => 48.8675, 'stop_lon' => 2.3478],
            ['stop_name' => 'Réaumur - Sébastopol', 'stop_lat' => 48.8660, 'stop_lon' => 2.3525],
            ['stop_name' => 'Arts et Métiers', 'stop_lat' => 48.8651, 'stop_lon' => 2.3567],
            ['stop_name' => 'Temple', 'stop_lat' => 48.8665, 'stop_lon' => 2.3618],
            ['stop_name' => 'République', 'stop_lat' => 48.8674, 'stop_lon' => 2.3638],
            ['stop_name' => 'Parmentier', 'stop_lat' => 48.8653, 'stop_lon' => 2.3750],
            ['stop_name' => 'Rue Saint-Maur', 'stop_lat' => 48.8640, 'stop_lon' => 2.3803],
            ['stop_name' => 'Père Lachaise', 'stop_lat' => 48.8623, 'stop_lon' => 2.3869],
            ['stop_name' => 'Gambetta', 'stop_lat' => 48.8650, 'stop_lon' => 2.3984],
            ['stop_name' => 'Porte de Bagnolet', 'stop_lat' => 48.8635, 'stop_lon' => 2.4094],
            ['stop_name' => 'Gallieni', 'stop_lat' => 48.8634, 'stop_lon' => 2.4159],
        ],
        // Métro 3bis
        'C01386' => [
            ['stop_name' => 'Gambetta', 'stop_lat' => 48.8650, 'stop_lon' => 2.3984],
            ['stop_name' => 'Pelleport', 'stop_lat' => 48.8684, 'stop_lon' => 2.4014],
            ['stop_name' => 'Saint-Fargeau', 'stop_lat' => 48.8718, 'stop_lon' => 2.4040],
            ['stop_name' => 'Porte des Lilas', 'stop_lat' => 48.8769, 'stop_lon' => 2.4065],
        ],
        // Métro 7bis
        'C01387' => [
            ['stop_name' => 'Louis Blanc', 'stop_lat' => 48.8814, 'stop_lon' => 2.3650],
            ['stop_name' => 'Jaurès', 'stop_lat' => 48.8820, 'stop_lon' => 2.3706],
            ['stop_name' => 'Bolivar', 'stop_lat' => 48.8807, 'stop_lon' => 2.3743],
            ['stop_name' => 'Buttes Chaumont', 'stop_lat' => 48.8784, 'stop_lon' => 2.3819],
            ['stop_name' => 'Botzaris', 'stop_lat' => 48.8795, 'stop_lon' => 2.3891],
            ['stop_name' => 'Place des Fêtes', 'stop_lat' => 48.8768, 'stop_lon' => 2.3930],
            ['stop_name' => 'Pré Saint-Gervais', 'stop_lat' => 48.8802, 'stop_lon' => 2.3986],
            ['stop_name' => 'Danube', 'stop_lat' => 48.8819, 'stop_lon' => 2.3931],
        ],
        // Métro 4
        'C01374' => [
            ['stop_name' => 'Porte de Clignancourt', 'stop_lat' => 48.8976, 'stop_lon' => 2.3444],
            ['stop_name' => 'Simplon', 'stop_lat' => 48.8940, 'stop_lon' => 2.3479],
            ['stop_name' => 'Marcadet - Poissonniers', 'stop_lat' => 48.8916, 'stop_lon' => 2.3496],
            ['stop_name' => 'Château Rouge', 'stop_lat' => 48.8874, 'stop_lon' => 2.3497],
            ['stop_name' => 'Barbès - Rochechouart', 'stop_lat' => 48.8839, 'stop_lon' => 2.3495],
            ['stop_name' => 'Gare du Nord', 'stop_lat' => 48.8799, 'stop_lon' => 2.3558],
            ['stop_name' => 'Gare de l\'Est', 'stop_lat' => 48.8768, 'stop_lon' => 2.3585],
            ['stop_name' => 'Châtelet', 'stop_lat' => 48.8584, 'stop_lon' => 2.3473],
            ['stop_name' => 'Cité', 'stop_lat' => 48.8554, 'stop_lon' => 2.3468],
            ['stop_name' => 'Saint-Michel', 'stop_lat' => 48.8531, 'stop_lon' => 2.3442],
            ['stop_name' => 'Odéon', 'stop_lat' => 48.8516, 'stop_lon' => 2.3389],
            ['stop_name' => 'Montparnasse - Bienvenüe', 'stop_lat' => 48.8427, 'stop_lon' => 2.3219],
            ['stop_name' => 'Denfert-Rochereau', 'stop_lat' => 48.8336, 'stop_lon' => 2.3325],
            ['stop_name' => 'Alésia', 'stop_lat' => 48.8280, 'stop_lon' => 2.3268],
            ['stop_name' => 'Porte d\'Orléans', 'stop_lat' => 48.8227, 'stop_lon' => 2.3253],
            ['stop_name' => 'Mairie de Montrouge', 'stop_lat' => 48.8185, 'stop_lon' => 2.3194],
        ],
        // Métro 5-14 (abrégé pour la lisibilité)
        'C01375' => [['stop_name' => 'Bobigny - Pablo Picasso', 'stop_lat' => 48.9067, 'stop_lon' => 2.4499], ['stop_name' => 'Place d\'Italie', 'stop_lat' => 48.8310, 'stop_lon' => 2.3555]],
        'C01376' => [['stop_name' => 'Charles de Gaulle - Étoile', 'stop_lat' => 48.8738, 'stop_lon' => 2.2950], ['stop_name' => 'Nation', 'stop_lat' => 48.8484, 'stop_lon' => 2.3959]],
        'C01377' => [['stop_name' => 'La Courneuve - 8 Mai 1945', 'stop_lat' => 48.9209, 'stop_lon' => 2.4102], ['stop_name' => 'Villejuif - Louis Aragon', 'stop_lat' => 48.7878, 'stop_lon' => 2.3678]],
        'C01378' => [['stop_name' => 'Balard', 'stop_lat' => 48.8363, 'stop_lon' => 2.2781], ['stop_name' => 'Créteil - Préfecture', 'stop_lat' => 48.7792, 'stop_lon' => 2.4599]],
        'C01379' => [['stop_name' => 'Pont de Sèvres', 'stop_lat' => 48.8299, 'stop_lon' => 2.2308], ['stop_name' => 'Mairie de Montreuil', 'stop_lat' => 48.8620, 'stop_lon' => 2.4417]],
        'C01380' => [['stop_name' => 'Boulogne - Pont de Saint-Cloud', 'stop_lat' => 48.8412, 'stop_lon' => 2.2287], ['stop_name' => 'Gare d\'Austerlitz', 'stop_lat' => 48.8421, 'stop_lon' => 2.3647]],
        'C01381' => [['stop_name' => 'Châtelet', 'stop_lat' => 48.8584, 'stop_lon' => 2.3473], ['stop_name' => 'Mairie des Lilas', 'stop_lat' => 48.8798, 'stop_lon' => 2.4169]],
        'C01382' => [['stop_name' => 'Front Populaire', 'stop_lat' => 48.9067, 'stop_lon' => 2.3654], ['stop_name' => 'Mairie d\'Issy', 'stop_lat' => 48.8243, 'stop_lon' => 2.2739]],
        'C01383' => [['stop_name' => 'Les Courtilles', 'stop_lat' => 48.9305, 'stop_lon' => 2.2856], ['stop_name' => 'Châtillon - Montrouge', 'stop_lat' => 48.8102, 'stop_lon' => 2.3016]],
        'C01384' => [['stop_name' => 'Saint-Denis Pleyel', 'stop_lat' => 48.9315, 'stop_lon' => 2.3479], ['stop_name' => 'Aéroport d\'Orly', 'stop_lat' => 48.7262, 'stop_lon' => 2.3598]],
    ];

    return $stations[$lineId] ?? [];
}

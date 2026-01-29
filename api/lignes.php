<?php
/**
 * Mon Réseau IDF - API Lignes de transport
 * Utilise l'API Open Data IDFM avec CACHE local
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Cache configuration
$cacheFile = __DIR__ . '/../cache/lignes.json';
$cacheDir = dirname($cacheFile);
$cacheDuration = 3600 * 6; // 6 heures de cache

// Créer le dossier cache si nécessaire
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Forcer refresh du cache si demandé
$forceRefresh = isset($_GET['refresh']);

// Vérifier si le cache est valide
if (!$forceRefresh && file_exists($cacheFile)) {
    $cacheAge = time() - filemtime($cacheFile);
    if ($cacheAge < $cacheDuration) {
        // Retourner le cache
        $cached = file_get_contents($cacheFile);
        $data = json_decode($cached, true);
        if ($data && isset($data['records'])) {
            $data['source'] = 'Cache local (âge: ' . round($cacheAge / 60) . ' min)';
            $data['cached'] = true;
            echo json_encode($data);
            exit;
        }
    }
}

// Récupérer la clé API
$apiKey = getSetting($pdo, 'idfm_api_key', '');

// Fonction pour récupérer les lignes avec pagination
function fetchAllLines($apiKey)
{
    $allRecords = [];
    $offset = 0;
    $limit = 100;
    $maxIterations = 50;

    for ($i = 0; $i < $maxIterations; $i++) {
        $apiUrl = "https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/referentiel-des-lignes/records?limit={$limit}&offset={$offset}";

        if (!function_exists('curl_init')) {
            break;
        }

        $ch = curl_init();
        $headers = [
            'Accept: application/json',
            'User-Agent: MonReseauIDF/1.0'
        ];
        if (!empty($apiKey)) {
            $headers[] = 'apikey: ' . $apiKey;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            break;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            break;
        }

        $records = isset($data['results']) ? $data['results'] : [];
        if (empty($records)) {
            break;
        }

        $allRecords = array_merge($allRecords, $records);
        $offset += $limit;

        if (count($records) < $limit) {
            break;
        }

        usleep(50000); // 50ms pause
    }

    return $allRecords;
}

// Récupérer depuis l'API
$records = fetchAllLines($apiKey);

// Si on a des données, dédupliquer et les mettre en cache
if (!empty($records)) {
    // Dédupliquer les lignes par id_line
    $uniqueRecords = [];
    $seenIds = [];
    foreach ($records as $record) {
        $lineId = $record['id_line'] ?? '';
        if (!empty($lineId) && !in_array($lineId, $seenIds)) {
            $seenIds[] = $lineId;
            $uniqueRecords[] = $record;
        }
    }
    $records = $uniqueRecords;

    // Normaliser le mode de transport pour distinguer RER et Transilien
    // Les RER ont des IDs spécifiques (A, B, C, D, E)
    $rerLineIds = ['C01742', 'C01743', 'C01727', 'C01728', 'C01729'];
    // Les Transiliens ont transportsubmode = 'suburbanRailway'

    foreach ($records as &$record) {
        $lineId = $record['id_line'] ?? '';
        $transportmode = strtolower($record['transportmode'] ?? '');
        $transportsubmode = strtolower($record['transportsubmode'] ?? '');

        // Classification des lignes ferroviaires
        if ($transportmode === 'rail') {
            if (in_array($lineId, $rerLineIds)) {
                // C'est un RER
                $record['transportmode'] = 'rer';
            } elseif ($transportsubmode === 'suburbanrailway') {
                // C'est un Transilien
                $record['transportmode'] = 'rail';
            } elseif ($transportsubmode === 'railshuttle') {
                // Navettes (CDG VAL, ORLYVAL)
                $record['transportmode'] = 'cable';
            } elseif ($transportsubmode === 'regionalrail') {
                // TER - on les exclut (hors IDF)
                continue;
            }
            // Sinon on garde 'rail' (Transilien par défaut)
        }
    }
    unset($record); // Libérer la référence

    $response = [
        'success' => true,
        'count' => count($records),
        'source' => 'API IDFM Open Data (fraîchement récupéré)',
        'cached' => false,
        'records' => $records
    ];

    // Sauvegarder dans le cache
    file_put_contents($cacheFile, json_encode($response));

    echo json_encode($response);
    exit;
}

// Fallback minimal si l'API échoue
echo json_encode([
    'success' => false,
    'count' => 0,
    'source' => 'Erreur - API indisponible',
    'records' => []
]);

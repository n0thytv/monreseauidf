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

// Données d'horaires théoriques pour le métro (quand l'API temps réel n'est pas dispo)
function getTheoreticalSchedules($lineId, $mode)
{
    // Horaires de base du métro parisien
    $schedules = [
        'weekday' => [
            'first_train' => '05:30',
            'last_train' => '00:30',
            'frequency_peak' => '2-3 min',
            'frequency_offpeak' => '4-6 min',
            'frequency_night' => '8-10 min'
        ],
        'saturday' => [
            'first_train' => '05:30',
            'last_train' => '01:00',
            'frequency_peak' => '3-4 min',
            'frequency_offpeak' => '5-7 min',
            'frequency_night' => '8-10 min'
        ],
        'sunday' => [
            'first_train' => '06:00',
            'last_train' => '00:30',
            'frequency_peak' => '4-5 min',
            'frequency_offpeak' => '6-8 min',
            'frequency_night' => '10-12 min'
        ],
        'friday_saturday_night' => [
            'last_train' => '02:15',
            'note' => 'Service prolongé les nuits de vendredi à samedi et samedi à dimanche'
        ]
    ];

    // Adapter selon le mode
    if ($mode === 'rer') {
        $schedules['weekday']['first_train'] = '05:00';
        $schedules['weekday']['frequency_peak'] = '4-8 min';
        $schedules['weekday']['frequency_offpeak'] = '10-15 min';
    } elseif ($mode === 'rail') {
        // TER et Transilien
        $schedules['weekday']['first_train'] = '05:30';
        $schedules['weekday']['last_train'] = '23:30';
        $schedules['weekday']['frequency_peak'] = '15-30 min';
        $schedules['weekday']['frequency_offpeak'] = '30-60 min';
        $schedules['weekday']['frequency_night'] = 'Service réduit';
        $schedules['saturday']['first_train'] = '06:00';
        $schedules['saturday']['last_train'] = '23:00';
        $schedules['sunday']['first_train'] = '07:00';
        $schedules['sunday']['last_train'] = '22:30';
    } elseif ($mode === 'bus') {
        $schedules['weekday']['first_train'] = '06:00';
        $schedules['weekday']['last_train'] = '21:00';
        $schedules['weekday']['frequency_peak'] = '15-30 min';
        $schedules['weekday']['frequency_offpeak'] = '30-60 min';
        $schedules['weekday']['frequency_night'] = 'Service réduit ou inexistant';
        $schedules['saturday']['first_train'] = '07:00';
        $schedules['saturday']['last_train'] = '20:00';
        $schedules['saturday']['frequency_peak'] = '20-40 min';
        $schedules['saturday']['frequency_offpeak'] = '30-60 min';
        $schedules['saturday']['frequency_night'] = 'Service réduit ou inexistant';
        $schedules['sunday']['first_train'] = '08:00';
        $schedules['sunday']['last_train'] = '19:00';
        $schedules['sunday']['frequency_peak'] = '30-60 min';
        $schedules['sunday']['frequency_offpeak'] = '60 min ou plus';
        $schedules['sunday']['frequency_night'] = 'Pas de service';
    } elseif ($mode === 'tram') {
        $schedules['weekday']['frequency_peak'] = '4-6 min';
        $schedules['weekday']['frequency_offpeak'] = '8-10 min';
    }

    return $schedules;
}

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
$schedules = getTheoreticalSchedules($lineId, $mode);

// Récupérer les horaires réels depuis l'API PRIM pour calculer la fréquence
$realSchedule = getRealScheduleFromPRIM($lineId, $apiKey, $mode);
$debugSchedule = $realSchedule; // Pour debug
if ($realSchedule) {
    // Remplacer les valeurs théoriques par les valeurs réelles pour tous les jours
    if (!empty($realSchedule['frequency'])) {
        $schedules['weekday']['frequency_peak'] = $realSchedule['frequency'];
        $schedules['weekday']['frequency_offpeak'] = $realSchedule['frequency'];
        $schedules['saturday']['frequency_peak'] = $realSchedule['frequency'];
        $schedules['saturday']['frequency_offpeak'] = $realSchedule['frequency'];
        $schedules['sunday']['frequency_peak'] = $realSchedule['frequency'];
        $schedules['sunday']['frequency_offpeak'] = $realSchedule['frequency'];
    }
    if (!empty($realSchedule['first_departure'])) {
        $schedules['weekday']['first_train'] = $realSchedule['first_departure'];
        $schedules['saturday']['first_train'] = $realSchedule['first_departure'];
        $schedules['sunday']['first_train'] = $realSchedule['first_departure'];
    }
    if (!empty($realSchedule['last_departure'])) {
        $schedules['weekday']['last_train'] = $realSchedule['last_departure'];
        $schedules['saturday']['last_train'] = $realSchedule['last_departure'];
        $schedules['sunday']['last_train'] = $realSchedule['last_departure'];
    }
}

/**
 * Récupère les horaires réels depuis l'API PRIM
 * Essaie plusieurs arrêts de la ligne pour trouver des départs
 */
function getRealScheduleFromPRIM($lineId, $apiKey, $mode) {
    global $debugPRIM;
    $debugPRIM = [];

    if (empty($apiKey)) {
        $debugPRIM['error'] = 'No API key';
        return null;
    }

    // Charger le cache de la ligne pour trouver des arrêts
    $lineCode = preg_replace('/^IDFM:/', '', $lineId);
    $cacheFile = __DIR__ . '/../cache/traces/' . $lineCode . '.json';
    $debugPRIM['cache_file'] = basename($cacheFile);
    $debugPRIM['line_code'] = $lineCode;

    if (!file_exists($cacheFile)) {
        $debugPRIM['error'] = 'Cache file not found';
        return null;
    }

    $lineCache = json_decode(file_get_contents($cacheFile), true);
    if (empty($lineCache['routes'][0]['stops'])) {
        $debugPRIM['error'] = 'No stops in cache';
        return null;
    }

    $allStops = $lineCache['routes'][0]['stops'] ?? [];
    $debugPRIM['total_stops'] = count($allStops);

    // Essayer plusieurs arrêts (terminus + milieu + fin)
    $stopsToTry = [];
    if (count($allStops) > 0) {
        $stopsToTry[] = $allStops[0]; // Premier arrêt (terminus)
    }
    if (count($allStops) > 2) {
        $stopsToTry[] = $allStops[intval(count($allStops) / 2)]; // Arrêt du milieu
    }
    if (count($allStops) > 1) {
        $stopsToTry[] = $allStops[count($allStops) - 1]; // Dernier arrêt
    }

    $debugPRIM['stops_tried'] = [];
    $allDepartures = [];

    foreach ($stopsToTry as $stop) {
        $stopName = $stop['stop_name'] ?? 'unknown';
        $stopIdRaw = $stop['id'] ?? '';

        if (!preg_match('/(\d{3,})/', $stopIdRaw, $m)) {
            continue;
        }
        $stopId = $m[1];

        $debugPRIM['stops_tried'][] = ['name' => $stopName, 'id' => $stopId];

        // Appeler l'API PRIM
        $monitoringRef = 'STIF:StopPoint:Q:' . $stopId . ':';
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

        if ($httpCode !== 200 || empty($response)) {
            continue;
        }

        $data = json_decode($response, true);

        if (isset($data['Siri']['ServiceDelivery']['StopMonitoringDelivery'])) {
            foreach ($data['Siri']['ServiceDelivery']['StopMonitoringDelivery'] as $delivery) {
                if (isset($delivery['MonitoredStopVisit'])) {
                    foreach ($delivery['MonitoredStopVisit'] as $visit) {
                        $journey = $visit['MonitoredVehicleJourney'] ?? [];
                        $lineRef = $journey['LineRef']['value'] ?? '';

                        // Debug premier lineRef trouvé
                        if (!isset($debugPRIM['first_lineRef']) && !empty($lineRef)) {
                            $debugPRIM['first_lineRef'] = $lineRef;
                        }

                        // Collecter tous les lineRef uniques pour debug
                        if (!empty($lineRef) && !in_array($lineRef, $debugPRIM['all_lineRefs'] ?? [])) {
                            $debugPRIM['all_lineRefs'][] = $lineRef;
                        }

                        // Filtrer par ligne - vérifier si le lineRef contient le code
                        if (strpos($lineRef, $lineCode) === false) continue;

                        $call = $journey['MonitoredCall'] ?? [];
                        $expectedTime = $call['ExpectedDepartureTime'] ?? $call['ExpectedArrivalTime'] ?? null;

                        if ($expectedTime) {
                            $allDepartures[] = new DateTime($expectedTime);
                        }
                    }
                }
            }
        }

        // Si on a assez de départs, pas besoin d'essayer d'autres arrêts
        if (count($allDepartures) >= 3) {
            break;
        }
    }

    $debugPRIM['departures_count'] = count($allDepartures);

    if (count($allDepartures) < 2) {
        $debugPRIM['error'] = 'Not enough departures found: ' . count($allDepartures);
        return null;
    }

    // Supprimer les doublons (même heure exacte)
    $uniqueDepartures = [];
    $seenTimes = [];
    foreach ($allDepartures as $dep) {
        $timeStr = $dep->format('Y-m-d H:i');
        if (!in_array($timeStr, $seenTimes)) {
            $seenTimes[] = $timeStr;
            $uniqueDepartures[] = $dep;
        }
    }

    // Trier par heure
    usort($uniqueDepartures, function($a, $b) {
        return $a <=> $b;
    });

    $debugPRIM['unique_departures'] = count($uniqueDepartures);

    if (count($uniqueDepartures) < 2) {
        $debugPRIM['error'] = 'Not enough unique departures: ' . count($uniqueDepartures);
        return null;
    }

    // Calculer la fréquence moyenne
    $intervals = [];
    for ($i = 1; $i < count($uniqueDepartures); $i++) {
        $diff = $uniqueDepartures[$i]->getTimestamp() - $uniqueDepartures[$i-1]->getTimestamp();
        $intervals[] = $diff / 60; // en minutes
    }

    $avgInterval = array_sum($intervals) / count($intervals);

    // Formater la fréquence
    if ($avgInterval < 10) {
        $frequency = round($avgInterval) . ' min';
    } elseif ($avgInterval < 30) {
        $frequency = round($avgInterval / 5) * 5 . ' min';
    } else {
        $frequency = round($avgInterval) . ' min (~' . round($avgInterval / 60, 1) . 'h)';
    }

    return [
        'frequency' => $frequency,
        'first_departure' => null, // On ne peut pas déterminer le premier départ de la journée
        'last_departure' => null,  // On ne peut pas déterminer le dernier départ de la journée
        'current_frequency' => $frequency,
        'next_departures' => array_map(function($d) { return $d->format('H:i'); }, array_slice($uniqueDepartures, 0, 5))
    ];
}

// Récupérer les informations de service
$serviceInfo = [
    'metro' => [
        'service_hours' => 'De 5h30 à 0h30 (1h15 le week-end)',
        'frequency_info' => 'Un métro toutes les 2 à 8 minutes selon l\'heure',
        'accessibility' => 'Ligne à accessibilité variable selon les stations',
        'tips' => [
            'Évitez les heures de pointe (8h-9h30 et 17h30-19h30)',
            'Le week-end, service prolongé jusqu\'à 2h15',
            'Consultez l\'état du trafic avant de partir'
        ]
    ],
    'rer' => [
        'service_hours' => 'De 5h00 à 0h30',
        'frequency_info' => 'Un train toutes les 4 à 15 minutes',
        'accessibility' => 'Stations avec ascenseurs sur la plupart des gares',
        'tips' => [
            'Vérifiez la mission du train (toutes les gares ne sont pas desservies)',
            'Attention aux travaux fréquents le week-end',
            'Les RER A et B sont en correspondance à Châtelet'
        ]
    ],
    'bus' => [
        'service_hours' => 'De 6h00 à 21h00 (variable)',
        'frequency_info' => 'Un bus toutes les 8 à 20 minutes',
        'accessibility' => 'Bus accessibles aux personnes à mobilité réduite',
        'tips' => [
            'Validez votre titre de transport à la montée',
            'Les Noctiliens circulent la nuit (0h30-5h30)',
            'Téléchargez l\'appli pour le suivi temps réel'
        ]
    ],
    'tram' => [
        'service_hours' => 'De 5h30 à 0h30',
        'frequency_info' => 'Un tramway toutes les 4 à 10 minutes',
        'accessibility' => 'Toutes les stations sont accessibles',
        'tips' => [
            'Priorité aux piétons aux passages',
            'Validez à l\'intérieur du tram'
        ]
    ],
    'rail' => [
        'service_hours' => 'De 5h30 à 23h30 (variable selon la ligne)',
        'frequency_info' => 'Un train toutes les 15 à 60 minutes selon l\'heure',
        'accessibility' => 'Accessibilité variable selon les gares',
        'tips' => [
            'Consultez les horaires spécifiques sur SNCF Connect',
            'Attention aux travaux fréquents le week-end',
            'Vérifiez que votre titre de transport est valide pour la zone',
            'Certains trains peuvent être supprimés ou modifiés'
        ]
    ]
];

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
    'success' => true,
    'line_id' => $lineId,
    'line_name' => $lineInfo['name_line'] ?? 'Ligne',
    'shortname' => $shortName,
    'mode' => $mode,
    'operator' => $lineInfo['operatorname'] ?? 'IDFM',
    'theoretical_schedules' => $schedules,
    'service_info' => $serviceInfo[$mode] ?? $serviceInfo['metro'],
    'peak_hours' => [
        'morning' => '07:30 - 09:30',
        'evening' => '17:00 - 19:30'
    ],
    'realtime_available' => $realSchedule !== null,
    'realtime_schedule' => $realSchedule,
    'is_indicative' => $realSchedule === null,
    'official_url' => $officialUrl,
    'official_idfm_url' => 'https://www.iledefrance-mobilites.fr/fiches-horaires/' . $mode . '/ligne-' . strtolower($shortName),
    'message' => $realSchedule
        ? 'Fréquence calculée à partir des prochains passages réels.'
        : 'Horaires indicatifs moyens. Aucun passage prévu actuellement ou ligne à faible fréquence. Consultez les fiches horaires officielles IDFM pour cette ligne.',
    'debug_prim' => isset($debugPRIM) ? $debugPRIM : null
];

// Si on a une clé API et un arrêt, essayer de récupérer le temps réel
if (!empty($apiKey) && !empty($stopId)) {
    // L'API PRIM prochains passages nécessite un ID d'arrêt spécifique
    // Format: StopPoint:IDFM:XXXXX
    $primUrl = "https://prim.iledefrance-mobilites.fr/marketplace/stop-monitoring?MonitoringRef=STIF:StopPoint:Q:" . urlencode($stopId);

    if (function_exists('curl_init')) {
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

        if ($httpCode === 200 && !empty($response)) {
            $data = json_decode($response, true);
            if (isset($data['Siri'])) {
                $result['realtime_available'] = true;
                $result['realtime_data'] = $data;
            }
        }
    }
}

echo json_encode($result);

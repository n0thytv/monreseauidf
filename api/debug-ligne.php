<?php
/**
 * Script de debug pour analyser l'API IDFM
 */
header('Content-Type: application/json');

$lineShortName = $_GET['line'] ?? '9103';

// 1. Récupérer les traces
$traceUrl = "https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/traces-des-lignes-de-transport-en-commun-idfm/records?where=route_short_name%3D%22" . urlencode($lineShortName) . "%22&limit=20";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $traceUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'MonReseauIDF/1.0'
]);
$traceResponse = curl_exec($ch);
curl_close($ch);

$traceData = json_decode($traceResponse, true);
$traces = [];

if (isset($traceData['results'])) {
    foreach ($traceData['results'] as $trace) {
        $traces[] = [
            'route_id' => $trace['route_id'] ?? '',
            'route_long_name' => $trace['route_long_name'] ?? '',
            'route_short_name' => $trace['route_short_name'] ?? ''
        ];
    }
}

// 2. Récupérer les arrêts
$stopsUrl = "https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/arrets-lignes/records?where=shortname%3D%22" . urlencode($lineShortName) . "%22&limit=100";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $stopsUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'MonReseauIDF/1.0'
]);
$stopsResponse = curl_exec($ch);
curl_close($ch);

$stopsData = json_decode($stopsResponse, true);
$stops = [];

if (isset($stopsData['results'])) {
    foreach ($stopsData['results'] as $stop) {
        $stops[] = [
            'id' => $stop['id'] ?? '',
            'stop_id' => $stop['stop_id'] ?? '',
            'stop_name' => $stop['stop_name'] ?? '',
            'route_id' => $stop['route_id'] ?? '',
            'route_long_name' => $stop['route_long_name'] ?? '',
            'direction' => $stop['direction'] ?? ''
        ];
    }
}

echo json_encode([
    'line' => $lineShortName,
    'traces_url' => $traceUrl,
    'traces_count' => count($traces),
    'traces' => $traces,
    'stops_url' => $stopsUrl,
    'stops_count' => count($stops),
    'stops' => $stops
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

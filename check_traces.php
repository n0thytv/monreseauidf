<?php
$url = 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/traces-des-lignes-de-transport-en-commun-idfm/records?limit=20&refine=route_id%3AIDFM%3AC01727';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (isset($data['results'][0])) {
    echo "=== CHAMPS DISPONIBLES ===\n";
    echo implode(", ", array_keys($data['results'][0])) . "\n\n";

    echo "=== RECORDS RER C ===\n";
    foreach ($data['results'] as $i => $rec) {
        echo "[$i] ";
        echo "route_name: " . ($rec['route_name'] ?? 'N/A') . " | ";
        echo "route_long_name: " . ($rec['route_long_name'] ?? 'N/A') . " | ";
        echo "headsign: " . ($rec['headsign'] ?? 'N/A') . " | ";
        echo "direction_id: " . ($rec['direction_id'] ?? 'N/A') . "\n";
    }
} else {
    echo "Pas de résultats ou erreur\n";
    print_r($data);
}

<?php
/**
 * Mon Réseau IDF - API Points de vente
 * Utilise les données locales du fichier points-de-vente.json
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Chemin vers le fichier JSON local
$jsonFile = __DIR__ . '/../pdv/points-de-vente.json';

if (!file_exists($jsonFile)) {
    echo json_encode([
        'error' => 'Fichier points-de-vente.json non trouvé',
        'records' => []
    ]);
    exit;
}

$jsonContent = file_get_contents($jsonFile);
$data = json_decode($jsonContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'error' => 'Erreur de décodage JSON: ' . json_last_error_msg(),
        'records' => []
    ]);
    exit;
}

// Retourner tous les points (1404 au total)
echo json_encode([
    'success' => true,
    'count' => count($data),
    'source' => 'Données locales IDFM',
    'records' => $data
]);

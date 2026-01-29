<?php
/**
 * Script de debug pour analyser les branches RER C
 * Objectif: Comprendre pourquoi certaines missions comme "Dourdan-Invalides" ne s'affichent pas
 */
header('Content-Type: text/html; charset=UTF-8');

echo "<h1>Debug RER C Branches</h1>\n";

// Appeler l'API trace-ligne pour le RER C
$lineId = 'IDFM:C01727';
$apiUrl = "http://localhost/monreseauidf/api/trace-ligne.php?id=" . urlencode($lineId) . "&debug=1";

echo "<h2>1. Appel API: $apiUrl</h2>\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Code: $httpCode</p>\n";

if ($httpCode !== 200 || empty($response)) {
    echo "<p style='color:red'>Erreur: Pas de réponse de l'API</p>\n";
    exit;
}

$data = json_decode($response, true);
if (!$data) {
    echo "<p style='color:red'>Erreur: JSON invalide</p>\n";
    echo "<pre>" . htmlspecialchars(substr($response, 0, 2000)) . "</pre>\n";
    exit;
}

// Afficher les infos de base
echo "<h2>2. Informations de base</h2>\n";
echo "<ul>\n";
echo "<li><strong>Mode:</strong> " . ($data['mode'] ?? 'N/A') . "</li>\n";
echo "<li><strong>Shortname:</strong> " . ($data['shortname'] ?? 'N/A') . "</li>\n";
echo "<li><strong>Nombre total d'arrêts:</strong> " . ($data['stops_count'] ?? 0) . "</li>\n";
echo "<li><strong>Nombre de routes:</strong> " . count($data['routes'] ?? []) . "</li>\n";
echo "<li><strong>Multi-route:</strong> " . ($data['is_multi_route'] ? 'Oui' : 'Non') . "</li>\n";
echo "</ul>\n";

// Afficher les routes
echo "<h2>3. Routes/Branches détectées</h2>\n";
$routes = $data['routes'] ?? [];
if (empty($routes)) {
    echo "<p style='color:orange'>Aucune route détectée!</p>\n";
} else {
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>\n";
    echo "<tr><th>#</th><th>Nom</th><th>Terminus A</th><th>Terminus B</th><th>Nb Arrêts</th></tr>\n";
    foreach ($routes as $route) {
        echo "<tr>\n";
        echo "<td>" . ($route['id'] ?? '') . "</td>\n";
        echo "<td>" . htmlspecialchars($route['name'] ?? '') . "</td>\n";
        echo "<td>" . htmlspecialchars($route['terminus_a'] ?? '') . "</td>\n";
        echo "<td>" . htmlspecialchars($route['terminus_b'] ?? '') . "</td>\n";
        echo "<td>" . ($route['stops_count'] ?? 0) . "</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

// Afficher les infos de debug
echo "<h2>4. Messages de debug</h2>\n";
$debugInfo = $data['debug_info'] ?? [];
if (!empty($debugInfo)) {
    echo "<pre style='background:#f0f0f0; padding:10px; max-height:400px; overflow:auto'>\n";
    foreach ($debugInfo as $msg) {
        echo htmlspecialchars($msg) . "\n";
    }
    echo "</pre>\n";
}

// Missions commerciales connues du RER C
echo "<h2>5. Analyse: Missions commerciales attendues</h2>\n";
$expectedMissions = [
    'ELBA' => ['terminus_a' => 'Pontoise', 'terminus_b' => 'Massy - Palaiseau'],
    'GOTA' => ['terminus_a' => 'Montigny - Beauchamp', 'terminus_b' => 'Versailles-Château'],
    'KRIN' => ['terminus_a' => 'Pontoise', 'terminus_b' => 'Versailles-Château'],
    'CIME' => ['terminus_a' => 'Argenteuil', 'terminus_b' => 'Massy - Palaiseau'],
    'DEBA' => ['terminus_a' => 'Dourdan - La Forêt (ou St-Martin)', 'terminus_b' => 'Paris Austerlitz / Invalides'],
    'SARA' => ['terminus_a' => 'Saint-Quentin-en-Yvelines', 'terminus_b' => 'Juvisy'],
];

echo "<p>Les missions commerciales principales du RER C sont:</p>\n";
echo "<ul>\n";
foreach ($expectedMissions as $code => $mission) {
    echo "<li><strong>$code:</strong> {$mission['terminus_a']} ↔ {$mission['terminus_b']}</li>\n";
}
echo "</ul>\n";

// Vérifier si les terminus attendus sont présents dans les arrêts
echo "<h2>6. Vérification des terminus dans les données</h2>\n";
$allStops = $data['stops'] ?? [];
$stopNames = array_column($allStops, 'stop_name');

$keyTermini = [
    'Pontoise',
    'Versailles-Château Rive Gauche',
    'Massy - Palaiseau',
    'Montigny - Beauchamp',
    'Argenteuil',
    'Dourdan - La Forêt',
    'Saint-Martin d\'Étampes',
    'Invalides',
    'Austerlitz',
    'Juvisy',
    'Saint-Quentin-en-Yvelines'
];

echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>\n";
echo "<tr><th>Terminus attendu</th><th>Présent dans les données?</th><th>Correspondance trouvée</th></tr>\n";
foreach ($keyTermini as $terminus) {
    $found = false;
    $match = '';
    foreach ($stopNames as $stopName) {
        if (stripos($stopName, explode(' ', $terminus)[0]) !== false) {
            $found = true;
            $match = $stopName;
            break;
        }
    }
    $color = $found ? 'green' : 'red';
    echo "<tr>\n";
    echo "<td>$terminus</td>\n";
    echo "<td style='color:$color'>" . ($found ? '✓ Oui' : '✗ Non') . "</td>\n";
    echo "<td>" . htmlspecialchars($match) . "</td>\n";
    echo "</tr>\n";
}
echo "</table>\n";

echo "<h2>7. Problème potentiel identifié</h2>\n";
echo "<div style='background:#fff3cd; border:1px solid #ffc107; padding:15px; border-radius:5px'>\n";
echo "<p><strong>Analyse:</strong></p>\n";
echo "<p>Le code actuel détecte les branches basées sur les <em>segments géométriques</em> du MultiLineString. ";
echo "Chaque segment représente un tronçon physique de la ligne, PAS une mission commerciale.</p>\n";
echo "<p>Par exemple, un segment peut aller de Versailles à Juvisy, mais la mission DEBA (Dourdan → Invalides) ";
echo "n'existe pas comme segment géométrique unique - elle traverse plusieurs segments.</p>\n";
echo "<p><strong>Solution possible:</strong> Au lieu de baser les branches sur les segments, ";
echo "définir les missions commerciales manuellement en listant les terminus connus du RER C.</p>\n";
echo "</div>\n";

// Liste des arrêts pour référence
echo "<h2>8. Liste complète des arrêts (première 50)</h2>\n";
echo "<p>Total: " . count($stopNames) . " arrêts</p>\n";
echo "<pre style='background:#f0f0f0; padding:10px; max-height:300px; overflow:auto'>\n";
$i = 0;
foreach ($stopNames as $name) {
    $i++;
    echo "$i. $name\n";
    if ($i >= 50) {
        echo "... (tronqué)\n";
        break;
    }
}
echo "</pre>\n";
?>
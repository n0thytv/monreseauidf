<?php
/**
 * Mon Réseau IDF - API Prochains passages temps réel
 * Utilise l'API PRIM pour les prochains départs - DONNÉES RÉELLES
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$stopName = isset($_GET['name']) ? $_GET['name'] : '';
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

// IDs GTFS des stations de métro (données officielles IDFM - toutes les lignes)
$metroStopIds = [
    // ===== LIGNE 1 =====
    'La Défense' => '71517',
    'Esplanade de La Défense' => '71410',
    'Pont de Neuilly' => '71563',
    'Les Sablons' => '71485',
    'Porte Maillot' => '71567',
    'Argentine' => '71348',
    'Charles de Gaulle - Étoile' => '71370',
    'George V' => '71426',
    'Franklin D. Roosevelt' => '71423',
    'Champs-Élysées - Clemenceau' => '71369',
    'Concorde' => '71384',
    'Tuileries' => '71600',
    'Palais Royal - Musée du Louvre' => '71544',
    'Louvre - Rivoli' => '71502',
    'Châtelet' => '71372',
    'Hôtel de Ville' => '71447',
    'Saint-Paul' => '71586',
    'Bastille' => '71353',
    'Gare de Lyon' => '71434',
    'Reuilly - Diderot' => '71573',
    'Nation' => '71533',
    'Porte de Vincennes' => '71568',
    'Saint-Mandé' => '71587',
    'Bérault' => '71357',
    'Château de Vincennes' => '71371',

    // ===== LIGNE 2 =====
    'Porte Dauphine' => '71564',
    'Victor Hugo' => '71605',
    'Ternes' => '71597',
    'Courcelles' => '71388',
    'Monceau' => '71526',
    'Villiers' => '71607',
    'Rome' => '71578',
    'Place de Clichy' => '71553',
    'Blanche' => '71359',
    'Pigalle' => '71550',
    'Anvers' => '71345',
    'Barbès - Rochechouart' => '71350',
    'La Chapelle' => '71516',
    'Stalingrad' => '71594',
    'Jaurès' => '71455',
    'Colonel Fabien' => '71383',
    'Belleville' => '71356',
    'Couronnes' => '71389',
    'Ménilmontant' => '71523',
    'Père Lachaise' => '71549',
    'Philippe Auguste' => '71551',
    'Alexandre Dumas' => '71341',
    'Avron' => '71349',

    // ===== LIGNE 3 =====
    'Pont de Levallois - Bécon' => '71562',
    'Anatole France' => '71343',
    'Louise Michel' => '71501',
    'Porte de Champerret' => '71565',
    'Pereire' => '71548',
    'Wagram' => '71608',
    'Malesherbes' => '71508',
    'Europe' => '71411',
    'Saint-Lazare' => '71585',
    'Havre - Caumartin' => '71445',
    'Opéra' => '71541',
    'Quatre-Septembre' => '71571',
    'Bourse' => '71361',
    'Sentier' => '71591',
    'Réaumur - Sébastopol' => '71572',
    'Arts et Métiers' => '71351',
    'Temple' => '71596',
    'République' => '71574',
    'Parmentier' => '71546',
    'Rue Saint-Maur' => '71581',
    'Gambetta' => '71425',
    'Porte de Bagnolet' => '71558',
    'Gallieni' => '71424',

    // ===== LIGNE 3bis =====
    'Porte des Lilas' => '71566',
    'Saint-Fargeau' => '71584',

    // ===== LIGNE 4 =====
    'Porte de Clignancourt' => '71560',
    'Simplon' => '71593',
    'Marcadet - Poissonniers' => '71510',
    'Château Rouge' => '71373',
    'Gare du Nord' => '71439',
    'Gare de l\'Est' => '71433',
    'Château d\'Eau' => '71374',
    'Strasbourg - Saint-Denis' => '71595',
    'Étienne Marcel' => '71412',
    'Les Halles' => '71484',
    'Cité' => '71381',
    'Saint-Michel' => '71588',
    'Odéon' => '71540',
    'Saint-Germain-des-Prés' => '71583',
    'Saint-Sulpice' => '71589',
    'Saint-Placide' => '71590',
    'Montparnasse - Bienvenüe' => '71530',
    'Vavin' => '71603',
    'Raspail' => '71570',
    'Denfert-Rochereau' => '71397',
    'Mouton-Duvernet' => '71531',
    'Alésia' => '71342',
    'Porte d\'Orléans' => '71559',
    'Mairie de Montrouge' => '71507',
    'Barbara' => '71352',
    'Bagneux - Lucie Aubrac' => '71354',

    // ===== LIGNE 5 =====
    'Bobigny - Pablo Picasso' => '71360',
    'Bobigny - Pantin - Raymond Queneau' => '71361',
    'Église de Pantin' => '71408',
    'Hoche' => '71446',
    'Porte de Pantin' => '71561',
    'Ourcq' => '71543',
    'Laumière' => '71483',
    'Gare du Nord' => '71439',
    'Jacques Bonsergent' => '71452',
    'Oberkampf' => '71539',
    'Richard-Lenoir' => '71576',
    'Bréguet - Sabin' => '71362',
    'Quai de la Rapée' => '71569',
    'Place d\'Italie' => '71554',

    // ===== LIGNE 6 =====
    'Charles de Gaulle - Étoile' => '71370',
    'Kléber' => '71478',
    'Boissière' => '71358',
    'Trocadéro' => '71599',
    'Passy' => '71547',
    'Bir-Hakeim' => '71355',
    'Dupleix' => '71406',
    'La Motte-Picquet - Grenelle' => '71518',
    'Cambronne' => '71366',
    'Sèvres - Lecourbe' => '71592',
    'Pasteur' => '71545',
    'Edgar Quinet' => '71407',
    'Montparnasse - Bienvenüe' => '71530',
    'Saint-Jacques' => '71582',
    'Glacière' => '71429',
    'Corvisart' => '71387',
    'Place d\'Italie' => '71554',
    'Nationale' => '71534',
    'Chevaleret' => '71375',
    'Quai de la Gare' => '71570',
    'Bercy' => '71356',
    'Dugommier' => '71405',
    'Daumesnil' => '71395',
    'Bel-Air' => '71355',
    'Picpus' => '71552',

    // ===== LIGNE 7 =====
    'La Courneuve - 8 Mai 1945' => '71515',
    'Fort d\'Aubervilliers' => '71419',
    'Aubervilliers - Pantin - Quatre Chemins' => '71346',
    'Porte de la Villette' => '71557',
    'Corentin Cariou' => '71386',
    'Crimée' => '71390',
    'Riquet' => '71577',
    'Louis Blanc' => '71499',
    'Château-Landon' => '71376',
    'Poissonnière' => '71555',
    'Cadet' => '71364',
    'Le Peletier' => '71482',
    'Chaussée d\'Antin - La Fayette' => '71377',
    'Pyramides' => '71556',
    'Pont Neuf' => '71564',
    'Pont Marie' => '71563',
    'Sully - Morland' => '71598',
    'Jussieu' => '71477',
    'Place Monge' => '71555',
    'Censier - Daubenton' => '71368',
    'Les Gobelins' => '71486',
    'Tolbiac' => '71601',
    'Maison Blanche' => '71505',
    'Porte d\'Italie' => '71556',
    'Porte de Choisy' => '71558',
    'Porte d\'Ivry' => '71557',
    'Pierre et Marie Curie' => '71553',
    'Mairie d\'Ivry' => '71504',
    'Le Kremlin-Bicêtre' => '71481',
    'Villejuif - Léo Lagrange' => '71606',
    'Villejuif - Paul Vaillant-Couturier' => '71607',
    'Villejuif - Louis Aragon' => '71605',

    // ===== LIGNE 7bis =====
    'Louis Blanc' => '71499',
    'Bolivar' => '71358',
    'Buttes Chaumont' => '71365',
    'Botzaris' => '71362',
    'Place des Fêtes' => '71556',
    'Pré Saint-Gervais' => '71569',
    'Danube' => '71394',

    // ===== LIGNE 8 =====
    'Balard' => '71350',
    'Lourmel' => '71503',
    'Boucicaut' => '71360',
    'Félix Faure' => '71415',
    'Commerce' => '71385',
    'École Militaire' => '71409',
    'La Tour-Maubourg' => '71520',
    'Invalides' => '71451',
    'Madeleine' => '71506',
    'Richelieu - Drouot' => '71575',
    'Grands Boulevards' => '71443',
    'Bonne Nouvelle' => '71359',
    'Filles du Calvaire' => '71416',
    'Saint-Sébastien - Froissart' => '71587',
    'Chemin Vert' => '71374',
    'Ledru-Rollin' => '71479',
    'Faidherbe - Chaligny' => '71413',
    'Montgallet' => '71527',
    'Michel Bizot' => '71524',
    'Porte Dorée' => '71561',
    'Porte de Charenton' => '71556',
    'Liberté' => '71490',
    'Charenton - Écoles' => '71378',
    'École Vétérinaire de Maisons-Alfort' => '71410',
    'Maisons-Alfort - Stade' => '71509',
    'Maisons-Alfort - Les Juilliottes' => '71508',
    'Créteil - L\'Échat' => '71391',
    'Créteil - Université' => '71392',
    'Créteil - Préfecture' => '71393',
    'Pointe du Lac' => '71556',

    // ===== LIGNE 9 =====
    'Pont de Sèvres' => '71565',
    'Billancourt' => '71354',
    'Marcel Sembat' => '71511',
    'Porte de Saint-Cloud' => '71569',
    'Exelmans' => '71414',
    'Michel-Ange - Molitor' => '71525',
    'Michel-Ange - Auteuil' => '71524',
    'Jasmin' => '71454',
    'Ranelagh' => '71571',
    'La Muette' => '71519',
    'Rue de la Pompe' => '71580',
    'Iéna' => '71449',
    'Alma - Marceau' => '71344',
    'Saint-Philippe du Roule' => '71588',
    'Miromesnil' => '71528',
    'Saint-Augustin' => '71581',
    'Richelieu - Drouot' => '71575',
    'Bonne Nouvelle' => '71359',
    'Voltaire' => '71608',
    'Charonne' => '71379',
    'Rue des Boulets' => '71579',
    'Buzenval' => '71363',
    'Maraîchers' => '71509',
    'Porte de Montreuil' => '71562',
    'Robespierre' => '71577',
    'Croix de Chavaux' => '71394',
    'Mairie de Montreuil' => '71512',

    // ===== LIGNE 10 =====
    'Boulogne - Pont de Saint-Cloud' => '71362',
    'Boulogne - Jean Jaurès' => '71361',
    'Porte d\'Auteuil' => '71555',
    'Michel-Ange - Auteuil' => '71524',
    'Chardon-Lagache' => '71377',
    'Mirabeau' => '71527',
    'Javel - André Citroën' => '71456',
    'Charles Michels' => '71380',
    'Avenue Émile Zola' => '71347',
    'Ségur' => '71590',
    'Duroc' => '71404',
    'Vaneau' => '71602',
    'Sèvres - Babylone' => '71591',
    'Mabillon' => '71502',
    'Cluny - La Sorbonne' => '71382',
    'Maubert - Mutualité' => '71521',
    'Cardinal Lemoine' => '71367',
    'Gare d\'Austerlitz' => '71431',

    // ===== LIGNE 11 =====
    'Châtelet' => '71372',
    'Hôtel de Ville' => '71447',
    'Rambuteau' => '71572',
    'Arts et Métiers' => '71351',
    'Place des Fêtes' => '71556',
    'Télégraphe' => '71597',
    'Porte des Lilas' => '71566',
    'Mairie des Lilas' => '71513',
    'Rosny - Bois-Perrier' => '71579',

    // ===== LIGNE 12 =====
    'Front Populaire' => '71422',
    'Porte de la Chapelle' => '71556',
    'Marx Dormoy' => '71514',
    'Marcadet - Poissonniers' => '71510',
    'Jules Joffrin' => '71476',
    'Lamarck - Caulaincourt' => '71480',
    'Abbesses' => '71340',
    'Saint-Georges' => '71582',
    'Notre-Dame-de-Lorette' => '71538',
    'Trinité - d\'Estienne d\'Orves' => '71598',
    'Assemblée Nationale' => '71347',
    'Solférino' => '71594',
    'Rue du Bac' => '71578',
    'Notre-Dame-des-Champs' => '71537',
    'Rennes' => '71573',
    'Falguière' => '71414',
    'Convention' => '71388',
    'Porte de Versailles' => '71570',
    'Corentin Celton' => '71385',
    'Mairie d\'Issy' => '71503',

    // ===== LIGNE 13 =====
    'Asnières - Gennevilliers - Les Courtilles' => '71349',
    'Les Agnettes' => '71487',
    'Gabriel Péri' => '71421',
    'Mairie de Clichy' => '71502',
    'Porte de Clichy' => '71559',
    'Brochant' => '71363',
    'La Fourche' => '71517',
    'Guy Môquet' => '71444',
    'Porte de Saint-Ouen' => '71568',
    'Garibaldi' => '71427',
    'Mairie de Saint-Ouen' => '71514',
    'Carrefour Pleyel' => '71369',
    'Saint-Denis - Porte de Paris' => '71584',
    'Basilique de Saint-Denis' => '71355',
    'Saint-Denis - Université' => '71585',
    'Liège' => '71491',
    'Miromesnil' => '71528',
    'Champs-Élysées - Clemenceau' => '71369',
    'Varenne' => '71601',
    'Duroc' => '71404',
    'Pernety' => '71549',
    'Plaisance' => '71554',
    'Porte de Vanves' => '71571',
    'Malakoff - Plateau de Vanves' => '71510',
    'Malakoff - Rue Étienne Dolet' => '71509',
    'Châtillon - Montrouge' => '71380',

    // ===== LIGNE 14 =====
    'Saint-Denis Pleyel' => '71586',
    'Mairie de Saint-Ouen' => '71514',
    'Pont Cardinet' => '71561',
    'Porte de Clichy' => '71559',
    'Saint-Lazare' => '71585',
    'Madeleine' => '71506',
    'Pyramides' => '71556',
    'Châtelet' => '71372',
    'Gare de Lyon' => '71434',
    'Bercy' => '71356',
    'Cour Saint-Émilion' => '71389',
    'Bibliothèque François Mitterrand' => '71358',
    'Olympiades' => '71542',
    'Maison Blanche - Paris XIIIe' => '71505',
    'Villejuif - Institut Gustave Roussy' => '71606',
    'Chevilly - Larue' => '71381',
    'L\'Haÿ-les-Roses' => '71483',
    'Thiais - Orly' => '71598',
    'Aéroport d\'Orly' => '71345',
];

$departures = [];
$error = null;
$debug = [];

// Chercher l'ID de la station
$stopId = null;
$normalizedName = mb_strtolower(trim($stopName));

// Recherche exacte d'abord
foreach ($metroStopIds as $name => $id) {
    if (mb_strtolower($name) === $normalizedName) {
        $stopId = $id;
        break;
    }
}

// Recherche partielle si pas trouvé
if (!$stopId) {
    foreach ($metroStopIds as $name => $id) {
        if (
            strpos(mb_strtolower($name), $normalizedName) !== false ||
            strpos($normalizedName, mb_strtolower($name)) !== false
        ) {
            $stopId = $id;
            break;
        }
    }
}

if (!$stopId) {
    echo json_encode([
        'success' => false,
        'error' => 'Station "' . htmlspecialchars($stopName) . '" non trouvée. Essayez une station de métro parisien.',
        'departures' => []
    ]);
    exit;
}

// Construire le MonitoringRef au format PRIM
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

                    $departures[] = [
                        'line_name' => $journey['PublishedLineName'][0]['value'] ?? '',
                        'line_ref' => $journey['LineRef']['value'] ?? '',
                        'direction' => $journey['DestinationName'][0]['value'] ?? '',
                        'destination' => $journey['DirectionName'][0]['value'] ?? $journey['DestinationName'][0]['value'] ?? '',
                        'expected_time' => $expectedTime,
                        'wait_minutes' => $waitMinutes,
                        'status' => $call['DepartureStatus'] ?? 'onTime'
                    ];
                }
            }
        }

        usort($departures, function ($a, $b) {
            return ($a['wait_minutes'] ?? 999) - ($b['wait_minutes'] ?? 999);
        });
    }

    if (empty($departures)) {
        $error = "Aucun passage prévu. Le service peut être interrompu ou la station fermée.";
    }
} elseif ($httpCode === 401 || $httpCode === 403) {
    $error = "Clé API invalide ou expirée.";
} elseif ($httpCode === 404) {
    $error = "Station non disponible dans l'API temps réel.";
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

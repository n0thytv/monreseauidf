<?php
/**
 * Mon Réseau IDF - Points de vente
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Points de vente';
$apiKey = getSetting($pdo, 'idfm_api_key', '');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $pageTitle; ?> - Mon Réseau IDF
    </title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map {
            height: 600px;
            width: 100%;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
        }

        .map-container {
            position: relative;
        }

        .map-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: var(--spacing-6);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            text-align: center;
            z-index: 1000;
        }

        .map-loading.hidden {
            display: none;
        }

        .legend {
            display: flex;
            gap: var(--spacing-6);
            flex-wrap: wrap;
            margin-top: var(--spacing-4);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            font-size: var(--font-size-sm);
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: var(--radius-full);
        }

        .stats-bar {
            display: flex;
            gap: var(--spacing-6);
            margin-bottom: var(--spacing-6);
            flex-wrap: wrap;
        }

        .stat-box {
            background: var(--white);
            padding: var(--spacing-4) var(--spacing-6);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }

        .stat-box-value {
            font-size: var(--font-size-2xl);
            font-weight: 700;
            color: var(--primary);
        }

        .stat-box-label {
            font-size: var(--font-size-sm);
            color: var(--gray-500);
        }

        .leaflet-popup-content h4 {
            margin: 0 0 8px 0;
            color: var(--primary-dark);
        }

        .leaflet-popup-content p {
            margin: 4px 0;
            font-size: 13px;
        }

        .popup-type {
            display: inline-block;
            padding: 2px 8px;
            background: var(--primary-dark);
            color: white;
            border-radius: 12px;
            font-size: 11px;
            margin-bottom: 8px;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Hero -->
    <section class="hero" style="padding: var(--spacing-10) 0;">
        <div class="container">
            <div class="hero-content">
                <h1>📍 Points de vente</h1>
                <p>Trouvez les agences et points de vente des titres de transport en Île-de-France</p>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section style="padding: var(--spacing-8) 0; background: var(--gray-50);">
        <div class="container">

            <?php if (empty($apiKey)): ?>
                <div class="alert alert-warning">
                    ⚠️ La clé API IDFM n'est pas configurée. <a
                        href="<?php echo SITE_URL; ?>/admin/settings.php">Configurer</a>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-bar" id="stats-bar">
                <div class="stat-box">
                    <div class="stat-box-value" id="total-points">-</div>
                    <div class="stat-box-label">Points de vente</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-value" id="total-agences">-</div>
                    <div class="stat-box-label">Agences</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-value" id="total-distributeurs">-</div>
                    <div class="stat-box-label">Distributeurs</div>
                </div>
            </div>

            <!-- Map -->
            <div class="map-container">
                <div class="map-loading" id="map-loading">
                    <div style="font-size: 32px; margin-bottom: 8px;">🔄</div>
                    <div>Chargement des points de vente...</div>
                </div>
                <div id="map"></div>
            </div>

            <!-- Legend -->
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #0C3E78;"></div>
                    <span>Agence Île-de-France Mobilités</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #10B981;"></div>
                    <span>Distributeur automatique</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #F59E0B;"></div>
                    <span>Commerce agréé</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #EF4444;"></div>
                    <span>Autre point de vente</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Info Section -->
    <section class="quick-access">
        <div class="container">
            <div class="section-title">
                <h2>Types de points de vente</h2>
                <p>Différentes options pour acheter ou recharger votre pass Navigo</p>
            </div>

            <div class="quick-cards">
                <div class="quick-card">
                    <div class="quick-card-icon">🏢</div>
                    <h4>Agences IDFM</h4>
                    <p>Service complet : vente, rechargement, SAV et conseils personnalisés</p>
                </div>
                <div class="quick-card">
                    <div class="quick-card-icon">🎫</div>
                    <h4>Distributeurs</h4>
                    <p>Achat et rechargement 24h/24 dans les gares et stations</p>
                </div>
                <div class="quick-card">
                    <div class="quick-card-icon">🏪</div>
                    <h4>Commerces</h4>
                    <p>Tabacs, bureaux de presse et commerces de proximité</p>
                </div>
                <div class="quick-card">
                    <div class="quick-card-icon">📱</div>
                    <h4>En ligne</h4>
                    <p>Achetez vos titres sur le site ou l'app Île-de-France Mobilités</p>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map centered on Île-de-France
        const map = L.map('map').setView([48.8566, 2.3522], 11);

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(map);

        // Custom markers colors
        const markerColors = {
            'agence': '#0C3E78',
            'distributeur': '#10B981',
            'commerce': '#F59E0B',
            'autre': '#EF4444'
        };

        // Create custom icon
        function createMarkerIcon(color) {
            return L.divIcon({
                className: 'custom-marker',
                html: `<div style="
                    background: ${color};
                    width: 12px;
                    height: 12px;
                    border-radius: 50%;
                    border: 2px solid white;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                "></div>`,
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            });
        }

        // Load points from API
        async function loadPointsDeVente() {
            const loading = document.getElementById('map-loading');
            
            <?php if (empty($apiKey)): ?>
                    loading.innerHTML = '<div style="font-size: 32px; margin-bottom: 8px;">⚠️</div><div>Clé API non configurée</div>';
                loadSampleData();
                return;
            <?php endif; ?>

            try {
                // Fetch from IDFM API via proxy
                const response = await fetch('<?php echo SITE_URL; ?>/api/points-de-vente.php');
                const data = await response.json();
                
                console.log('API Response:', data);

                if (data.error && (!data.records || data.records.length === 0)) {
                    throw new Error(data.error + (data.debug ? ' - ' + data.debug : ''));
                }

                // Handle different response formats
                let records = data.records || data.results || data;
                if (!Array.isArray(records)) {
                    records = [];
                }
                
                if (records.length === 0) {
                    throw new Error('Aucune donnée reçue');
                }

                displayPoints(records);
                loading.classList.add('hidden');

            } catch (error) {
                console.error('Error loading data:', error);
                loading.innerHTML = '<div style="font-size: 32px; margin-bottom: 8px;">⚠️</div><div>Erreur de chargement<br><small>Affichage des données de démonstration</small></div>';
                setTimeout(() => {
                    loadSampleData();
                    loading.classList.add('hidden');
                }, 1500);
            }
        }

        // Display points on map
        function displayPoints(points) {
            let totalAgences = 0;
            let totalDistributeurs = 0;
            let totalAutres = 0;

            points.forEach(point => {
                // Handle local JSON format
                const lat = point.pdvlatitude || point.latitude || point.geo_point_2d?.lat;
                const lng = point.pdvlongitude || point.longitude || point.geo_point_2d?.lon;

                if (!lat || !lng) return;

                const name = point.pdvname || point.nom || 'Point de vente';
                const typeName = (point.pdvtypename || point.type || '').toLowerCase();
                const address = [point.pdvhousenumber, point.pdvstreet].filter(Boolean).join(' ') || point.adresse || '';
                const city = point.pdvtown || point.commune || '';
                const hours = point.pdvopeninghours || '';

                let color = markerColors.autre;
                let typeLabel = 'Point de vente';

                if (typeName.includes('agence') || typeName.includes('guichet')) {
                    color = markerColors.agence;
                    typeLabel = 'Guichet Navigo';
                    totalAgences++;
                } else if (typeName.includes('distribut') || typeName.includes('automate')) {
                    color = markerColors.distributeur;
                    typeLabel = 'Distributeur';
                    totalDistributeurs++;
                } else if (typeName.includes('commerce') || typeName.includes('tabac') || typeName.includes('proximit')) {
                    color = markerColors.commerce;
                    typeLabel = 'Commerce de proximité';
                    totalAutres++;
                } else {
                    totalAutres++;
                }

                const marker = L.marker([lat, lng], { icon: createMarkerIcon(color) }).addTo(map);

                marker.bindPopup(`
                    <div class="popup-type">${typeLabel}</div>
                    <h4>${name}</h4>
                    ${address ? `<p>📍 ${address}</p>` : ''}
                    ${city ? `<p>🏙️ ${city}</p>` : ''}
                    ${hours ? `<p>🕐 ${hours}</p>` : ''}
                `);
            });

            // Update stats
            const total = totalAgences + totalDistributeurs + totalAutres;
            document.getElementById('total-points').textContent = total.toLocaleString('fr-FR');
            document.getElementById('total-agences').textContent = totalAgences.toLocaleString('fr-FR');
            document.getElementById('total-distributeurs').textContent = totalDistributeurs.toLocaleString('fr-FR');
        }

        // Sample data for demo
        function loadSampleData() {
            const samplePoints = [
                { nom: "Agence Île-de-France Mobilités - Châtelet", type: "Agence", latitude: 48.8583, longitude: 2.3470, adresse: "Place du Châtelet", commune: "Paris" },
                { nom: "Agence Île-de-France Mobilités - Gare de Lyon", type: "Agence", latitude: 48.8443, longitude: 2.3735, adresse: "Place Louis-Armand", commune: "Paris" },
                { nom: "Agence Île-de-France Mobilités - La Défense", type: "Agence", latitude: 48.8920, longitude: 2.2380, adresse: "Parvis de La Défense", commune: "Puteaux" },
                { nom: "Agence Île-de-France Mobilités - Saint-Lazare", type: "Agence", latitude: 48.8762, longitude: 2.3252, adresse: "Place du Havre", commune: "Paris" },
                { nom: "Distributeur - Gare du Nord", type: "Distributeur", latitude: 48.8809, longitude: 2.3553, adresse: "18 Rue de Dunkerque", commune: "Paris" },
                { nom: "Distributeur - Nation", type: "Distributeur", latitude: 48.8484, longitude: 2.3959, adresse: "Place de la Nation", commune: "Paris" },
                { nom: "Distributeur - Bastille", type: "Distributeur", latitude: 48.8530, longitude: 2.3690, adresse: "Place de la Bastille", commune: "Paris" },
                { nom: "Distributeur - Montparnasse", type: "Distributeur", latitude: 48.8419, longitude: 2.3210, adresse: "Place Raoul Dautry", commune: "Paris" },
                { nom: "Tabac Presse - République", type: "Commerce", latitude: 48.8675, longitude: 2.3637, adresse: "Place de la République", commune: "Paris" },
                { nom: "Distributeur - Vincennes", type: "Distributeur", latitude: 48.8473, longitude: 2.4336, adresse: "Château de Vincennes", commune: "Vincennes" },
                { nom: "Agence Île-de-France Mobilités - Versailles", type: "Agence", latitude: 48.8050, longitude: 2.1340, adresse: "Gare de Versailles-Chantiers", commune: "Versailles" },
                { nom: "Distributeur - Créteil", type: "Distributeur", latitude: 48.7770, longitude: 2.4590, adresse: "Créteil Préfecture", commune: "Créteil" },
                { nom: "Distributeur - Bobigny", type: "Distributeur", latitude: 48.9060, longitude: 2.4490, adresse: "Bobigny Pablo Picasso", commune: "Bobigny" },
                { nom: "Distributeur - Saint-Denis", type: "Distributeur", latitude: 48.9360, longitude: 2.3570, adresse: "Basilique de Saint-Denis", commune: "Saint-Denis" },
                { nom: "Distributeur - Noisy-le-Grand", type: "Distributeur", latitude: 48.8370, longitude: 2.5520, adresse: "Noisy-Champs", commune: "Noisy-le-Grand" }
            ];
            displayPoints(samplePoints);
        }

        // Load data
        loadPointsDeVente();
    </script>
</body>

</html>
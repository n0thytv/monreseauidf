<?php
/**
 * Mon Réseau IDF - Détail d'une ligne de Bus
 * Page dédiée aux lignes de bus avec plan, arrêts et horaires temps réel
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Récupérer l'ID de la ligne
$lineId = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($lineId)) {
    header('Location: ' . SITE_URL . '/lignes.php');
    exit;
}

$pageTitle = 'Détail de la ligne de Bus';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Mon Réseau IDF</title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .line-hero {
            padding: var(--spacing-8) 0;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .line-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }

        .line-hero-content {
            display: flex;
            align-items: center;
            gap: var(--spacing-6);
            position: relative;
            z-index: 1;
        }

        .line-hero-badge {
            width: 100px;
            height: 100px;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 2rem;
            background: white;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        }

        .line-hero-info h1 {
            font-size: 2rem;
            margin-bottom: var(--spacing-2);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .line-hero-meta {
            display: flex;
            gap: var(--spacing-4);
            flex-wrap: wrap;
            opacity: 0.9;
        }

        .line-hero-meta span {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
        }

        .line-hero-actions {
            margin-top: var(--spacing-4);
            display: flex;
            gap: var(--spacing-3);
            flex-wrap: wrap;
        }

        .hero-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-2);
            padding: var(--spacing-3) var(--spacing-5);
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: var(--radius-lg);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .hero-btn:hover {
            background: white;
            color: var(--primary);
            border-color: white;
        }

        .line-content {
            padding: var(--spacing-8) 0;
            background: var(--gray-50);
        }

        /* Tabs navigation */
        .line-tabs {
            display: flex;
            gap: var(--spacing-2);
            margin-bottom: var(--spacing-6);
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: var(--spacing-3) var(--spacing-5);
            background: white;
            border: none;
            border-radius: var(--radius-lg);
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            box-shadow: var(--shadow);
            transition: all 0.2s;
        }

        .tab-btn:hover {
            transform: translateY(-2px);
        }

        .tab-btn.active {
            background: var(--primary);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-6);
            margin-bottom: var(--spacing-8);
        }

        .info-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-6);
            box-shadow: var(--shadow);
        }

        .info-card-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
            margin-bottom: var(--spacing-4);
            padding-bottom: var(--spacing-4);
            border-bottom: 1px solid var(--gray-100);
        }

        .info-card-icon {
            font-size: 1.5rem;
        }

        .info-card-title {
            font-size: var(--font-size-lg);
            font-weight: 700;
            color: var(--gray-900);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: var(--spacing-3) 0;
            border-bottom: 1px solid var(--gray-50);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--gray-500);
            font-size: var(--font-size-sm);
        }

        .info-value {
            font-weight: 600;
            color: var(--gray-900);
            text-align: right;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: var(--font-size-sm);
            font-weight: 600;
        }

        .status-active {
            background: #DEF7EC;
            color: #03543F;
        }

        .status-inactive {
            background: #FDE8E8;
            color: #9B1C1C;
        }

        /* Map */
        .map-container {
            background: white;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        #line-map {
            height: 500px;
            width: 100%;
        }

        .map-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 500px;
            background: var(--gray-100);
            color: var(--gray-500);
        }

        /* Stops */
        .stations-list {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-6);
            box-shadow: var(--shadow);
        }

        .stations-timeline {
            position: relative;
            padding-left: var(--spacing-8);
        }

        .stations-timeline::before {
            content: '';
            position: absolute;
            left: 12px;
            top: 10px;
            bottom: 10px;
            width: 4px;
            border-radius: 2px;
        }

        .station-item {
            position: relative;
            padding: var(--spacing-3) 0;
            padding-left: var(--spacing-4);
            transition: background 0.2s;
            cursor: pointer;
        }

        .station-item:hover {
            background: rgba(12, 62, 120, 0.05);
        }

        .station-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            background: white;
            border: 3px solid;
            border-radius: 50%;
        }

        .station-item:first-child::before,
        .station-item:last-child::before {
            width: 16px;
            height: 16px;
            left: -26px;
        }

        .station-name {
            font-weight: 600;
            color: var(--gray-900);
        }

        /* Schedule Links */
        .schedule-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--spacing-4);
        }

        .schedule-link-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-5);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: var(--spacing-4);
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
        }

        .schedule-link-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .schedule-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            background: var(--gray-100);
        }

        .schedule-info h3 {
            font-size: var(--font-size-lg);
            margin-bottom: 4px;
        }

        .schedule-info p {
            font-size: var(--font-size-sm);
            color: var(--gray-500);
        }

        .loading-state {
            text-align: center;
            padding: var(--spacing-16);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto var(--spacing-4);
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-2);
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            margin-bottom: var(--spacing-4);
            transition: color 0.2s;
        }

        .back-link:hover {
            color: white;
        }

        /* Bus specific - direction info */
        .direction-info {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            border-radius: var(--radius-lg);
            padding: var(--spacing-4);
            margin-top: var(--spacing-4);
        }

        .direction-info h4 {
            font-size: var(--font-size-sm);
            margin-bottom: var(--spacing-2);
            opacity: 0.9;
        }

        .direction-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 14px;
            border-radius: var(--radius);
            font-size: var(--font-size-sm);
            display: inline-block;
            margin-top: 4px;
        }

        /* Route selector */
        .route-selector-wrapper {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-4);
            margin-bottom: var(--spacing-4);
            box-shadow: var(--shadow);
        }

        .route-selector-wrapper label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--gray-700);
        }

        .route-selector-wrapper select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            background: white;
            cursor: pointer;
        }

        .route-selector-wrapper select:focus {
            outline: none;
            border-color: var(--primary);
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Loading State -->
    <div id="loading-state" style="background: var(--gray-50); min-height: 60vh;">
        <div class="loading-state">
            <div class="loading-spinner"></div>
            <p>Chargement des informations de la ligne de bus...</p>
        </div>
    </div>

    <!-- Line Content -->
    <div id="line-page" style="display: none;">
        <!-- Hero -->
        <section class="line-hero" id="line-hero">
            <div class="container">
                <a href="<?php echo SITE_URL; ?>/lignes.php#mode-bus" class="back-link">← Retour aux lignes de bus</a>
                <div class="line-hero-content">
                    <div class="line-hero-badge" id="hero-badge"></div>
                    <div class="line-hero-info">
                        <h1 id="hero-title">Chargement...</h1>
                        <div class="line-hero-meta" id="hero-meta"></div>
                        <div class="line-hero-actions" id="hero-actions"></div>
                    </div>
                </div>
                <!-- Direction info -->
                <div class="direction-info" id="direction-info" style="display: none;">
                    <h4>Terminus :</h4>
                    <div id="direction-list"></div>
                </div>
            </div>
        </section>

        <!-- Content -->
        <section class="line-content">
            <div class="container">
                <!-- Tabs -->
                <div class="line-tabs">
                    <button class="tab-btn active" onclick="showTab('infos')">📋 Informations</button>
                    <button class="tab-btn" onclick="showTab('plan')">🗺️ Plan de ligne</button>
                    <button class="tab-btn" onclick="showTab('stations')">🚏 Arrêts</button>
                    <button class="tab-btn" onclick="showTab('horaires')">🕐 Horaires</button>
                </div>

                <!-- Tab: Informations -->
                <div class="tab-content active" id="tab-infos">
                    <div class="info-grid">
                        <div class="info-card">
                            <div class="info-card-header">
                                <span class="info-card-icon">📋</span>
                                <span class="info-card-title">Informations générales</span>
                            </div>
                            <div id="general-info"></div>
                        </div>
                        <div class="info-card">
                            <div class="info-card-header">
                                <span class="info-card-icon">🏢</span>
                                <span class="info-card-title">Exploitant & Réseau</span>
                            </div>
                            <div id="operator-info"></div>
                        </div>
                        <div class="info-card">
                            <div class="info-card-header">
                                <span class="info-card-icon">♿</span>
                                <span class="info-card-title">Accessibilité</span>
                            </div>
                            <div id="accessibility-info"></div>
                        </div>
                        <div class="info-card">
                            <div class="info-card-header">
                                <span class="info-card-icon">🎨</span>
                                <span class="info-card-title">Identité visuelle</span>
                            </div>
                            <div id="visual-info"></div>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-header">
                            <span class="info-card-icon">⚙️</span>
                            <span class="info-card-title">Données techniques</span>
                        </div>
                        <div id="technical-info"></div>
                    </div>
                </div>

                <!-- Tab: Plan -->
                <div class="tab-content" id="tab-plan">
                    <!-- Sélecteur de direction -->
                    <div class="route-selector-wrapper" id="route-selector-container" style="display: none;">
                        <label>🚌 Choisir une direction :</label>
                        <select id="route-selector" onchange="selectRoute(this.value)">
                            <option value="all">📍 Voir les deux directions</option>
                        </select>
                    </div>
                    <div class="map-container">
                        <div class="map-loading" id="map-loading">
                            <div class="loading-spinner"></div>
                        </div>
                        <div id="line-map" style="display: none;"></div>
                    </div>
                </div>

                <!-- Tab: Arrêts -->
                <div class="tab-content" id="tab-stations">
                    <!-- Sélecteur de direction pour les arrêts - TOUJOURS VISIBLE -->
                    <div class="route-selector-wrapper" id="stations-route-selector">
                        <div style="display: flex; align-items: center; gap: var(--spacing-3); flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 250px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--gray-700);">
                                    🚌 Sens de circulation :
                                </label>
                                <select id="stations-route-select" onchange="filterStationsByRoute(this.value)"
                                    style="width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); font-size: 1rem; background: white; cursor: pointer;">
                                    <option value="loading">⏳ Chargement des directions...</option>
                                </select>
                            </div>
                            <button id="reverse-stops-btn" onclick="reverseStopsOrder()" 
                                style="padding: 12px 20px; background: var(--gray-100); border: 2px solid var(--gray-200); border-radius: var(--radius-lg); cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.2s;"
                                onmouseover="this.style.background='var(--gray-200)'" 
                                onmouseout="this.style.background='var(--gray-100)'">
                                🔄 Inverser le sens
                            </button>
                        </div>
                        <div id="direction-indicator" style="margin-top: 12px; padding: 10px 16px; background: linear-gradient(135deg, var(--primary), #0056b3); color: white; border-radius: var(--radius-lg); font-weight: 600; display: none;">
                            <!-- Affiche "Vers [Terminus]" -->
                        </div>
                    </div>
                    <!-- Recherche d'arrêt -->
                    <div style="margin-bottom: var(--spacing-4);">
                        <input type="text" id="station-search" placeholder="🔍 Rechercher un arrêt..."
                            style="width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); font-size: 1rem;"
                            oninput="filterStations(this.value)">
                    </div>
                    <div class="stations-list">
                        <div class="info-card-header">
                            <span class="info-card-icon">🚏</span>
                            <span class="info-card-title">Arrêts de la ligne</span>
                            <span id="stations-count" style="margin-left: auto; color: var(--gray-500);"></span>
                        </div>
                        <div class="stations-timeline" id="stations-timeline">
                            <p style="color: var(--gray-500);">Chargement des arrêts...</p>
                        </div>
                    </div>
                </div>

                <!-- Tab: Horaires -->
                <div class="tab-content" id="tab-horaires">
                    <!-- Prochains passages temps réel -->
                    <div class="info-card" style="margin-bottom: var(--spacing-6); border: 2px solid var(--primary);">
                        <div class="info-card-header">
                            <span class="info-card-icon">🚀</span>
                            <span class="info-card-title">Prochains passages en temps réel</span>
                            <span id="realtime-status"
                                style="margin-left: auto; font-size: 12px; padding: 4px 12px; background: #DEF7EC; color: #03543F; border-radius: 20px;">⏳
                                Chargement...</span>
                        </div>

                        <!-- Sélecteur de direction pour horaires -->
                        <div id="horaires-route-selector" style="display: none; margin-bottom: var(--spacing-4);">
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--gray-700);">
                                🚌 Choisir une direction :
                            </label>
                            <select id="horaires-route-select" onchange="filterStopsByRoute(this.value)"
                                style="width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); font-size: 1rem; background: white; cursor: pointer;">
                                <option value="all">📍 Toutes les directions</option>
                            </select>
                        </div>

                        <!-- Sélecteur d'arrêt -->
                        <div style="margin-bottom: var(--spacing-4);">
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--gray-700);">
                                📍 Choisir un arrêt :
                            </label>
                            <select id="stop-selector"
                                style="width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); font-size: 1rem; background: white; cursor: pointer;"
                                onchange="loadDepartures()">
                                <option value="">-- Sélectionner un arrêt --</option>
                            </select>
                        </div>

                        <!-- Liste des prochains passages -->
                        <div id="departures-list">
                            <p style="color: var(--gray-500); text-align: center; padding: 20px;">
                                👆 Sélectionnez un arrêt pour voir les prochains passages
                            </p>
                        </div>

                        <!-- Bouton rafraîchir -->
                        <button onclick="loadDepartures()"
                            style="display: none; width: 100%; margin-top: var(--spacing-4); padding: 12px; background: var(--primary); color: white; border: none; border-radius: var(--radius-lg); font-weight: 600; cursor: pointer;"
                            id="refresh-btn">
                            🔄 Rafraîchir
                        </button>
                    </div>

                    <!-- Horaires de service -->
                    <div class="info-grid" style="margin-bottom: var(--spacing-6);">
                        <div class="info-card">
                            <div class="info-card-header">
                                <span class="info-card-icon">📅</span>
                                <span class="info-card-title">Horaires de service</span>
                            </div>
                            <div id="service-hours">
                                <div class="loading-spinner" style="margin: 20px auto;"></div>
                            </div>
                        </div>

                        <div class="info-card">
                            <div class="info-card-header">
                                <span class="info-card-icon">⏱️</span>
                                <span class="info-card-title">Fréquence de passage</span>
                            </div>
                            <div id="frequency-info">
                                <div class="loading-spinner" style="margin: 20px auto;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Conseils Bus -->
                    <div class="info-card" style="margin-bottom: var(--spacing-6);">
                        <div class="info-card-header">
                            <span class="info-card-icon">💡</span>
                            <span class="info-card-title">Conseils pratiques Bus</span>
                        </div>
                        <div id="travel-tips"></div>
                    </div>

                    <!-- Liens externes -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <span class="info-card-icon">🔗</span>
                            <span class="info-card-title">Liens utiles</span>
                        </div>
                        <div class="schedule-links" id="schedule-links"></div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
        const lineId = '<?php echo htmlspecialchars($lineId, ENT_QUOTES); ?>';
        let lineData = null;
        let lineColor = '91AE28'; // Vert bus par défaut
        let map = null;

        // Tab switching
        function showTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            document.querySelector(`[onclick="showTab('${tabId}')"]`).classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');

            if (tabId === 'plan' && !map) {
                loadLineMap();
            }
            if (tabId === 'stations') {
                loadStations();
            }
            if (tabId === 'horaires' && !document.getElementById('stop-selector').dataset.loaded) {
                populateStopSelector();
                document.getElementById('stop-selector').dataset.loaded = 'true';
            }
        }

        // Load line details
        async function loadLineDetails() {
            try {
                const response = await fetch('<?php echo SITE_URL; ?>/api/lignes.php');
                const data = await response.json();

                const line = data.records.find(l => l.id_line === lineId);
                if (!line) {
                    document.getElementById('loading-state').innerHTML = `
                        <div class="loading-state">
                            <p>❌ Ligne de bus non trouvée</p>
                            <a href="<?php echo SITE_URL; ?>/lignes.php" class="btn btn-primary">Retour aux lignes</a>
                        </div>
                    `;
                    return;
                }

                lineData = line;
                displayLine(line);
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('loading-state').innerHTML = `
                    <div class="loading-state"><p>⚠️ Erreur de chargement</p></div>
                `;
            }
        }

        function displayLine(line) {
            const name = line.shortname_line || line.name_line || 'Bus';
            const fullName = line.name_line || 'Bus ' + name;
            lineColor = line.colourweb_hexa || '91AE28';
            const textColor = line.textcolourweb_hexa || 'FFFFFF';

            document.title = fullName + ' - Mon Réseau IDF';

            // Hero
            document.getElementById('line-hero').style.background = `linear-gradient(135deg, #${lineColor} 0%, #${darkenColor(lineColor, 20)} 100%)`;

            // Badge - tronquer si trop long
            const badgeText = name.length > 4 ? name.substring(0, 4) : name;
            document.getElementById('hero-badge').innerHTML = badgeText;
            document.getElementById('hero-badge').style.color = '#' + lineColor;
            document.getElementById('hero-badge').style.fontSize = name.length > 3 ? '1.5rem' : '2rem';

            document.getElementById('hero-title').textContent = fullName;

            document.getElementById('hero-meta').innerHTML = `
                <span>🚌 Bus</span>
                <span>🏢 ${line.operatorname || 'RATP / Keolis'}</span>
                <span>📍 ${line.networkname || 'Île-de-France'}</span>
            `;

            // Action buttons
            const operator = (line.operatorname || '').toLowerCase();
            let scheduleUrl = '';
            if (operator.includes('ratp')) {
                scheduleUrl = `https://www.ratp.fr/horaires?line=bus-${name}`;
            }

            document.getElementById('hero-actions').innerHTML = `
                <a href="#" class="hero-btn" onclick="showTab('plan'); return false;">🗺️ Voir le plan</a>
                <a href="#" class="hero-btn" onclick="showTab('horaires'); return false;">🕐 Horaires</a>
                ${scheduleUrl ? `<a href="${scheduleUrl}" target="_blank" class="hero-btn">📄 Fiche RATP</a>` : ''}
            `;

            // Info sections
            displayInfoSections(line, lineColor, textColor, name);
            displayScheduleLinks(line);
            loadSchedules(line.id_line);

            // Show page
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('line-page').style.display = 'block';
        }

        function displayInfoSections(line, color, textColor, name) {
            document.getElementById('general-info').innerHTML = `
                <div class="info-row"><span class="info-label">Identifiant</span><span class="info-value">${line.id_line || '-'}</span></div>
                <div class="info-row"><span class="info-label">Nom court</span><span class="info-value">${line.shortname_line || '-'}</span></div>
                <div class="info-row"><span class="info-label">Mode de transport</span><span class="info-value">🚌 Bus</span></div>
                <div class="info-row"><span class="info-label">Sous-mode</span><span class="info-value">${line.transportsubmode || 'Bus urbain'}</span></div>
                <div class="info-row"><span class="info-label">Statut</span><span class="info-value"><span class="status-badge ${line.status === 'active' ? 'status-active' : 'status-inactive'}">${line.status === 'active' ? 'Active' : 'Inactive'}</span></span></div>
            `;

            document.getElementById('operator-info').innerHTML = `
                <div class="info-row"><span class="info-label">Exploitant</span><span class="info-value">${line.operatorname || '-'}</span></div>
                <div class="info-row"><span class="info-label">Code exploitant</span><span class="info-value">${line.operatorref || '-'}</span></div>
                <div class="info-row"><span class="info-label">Réseau</span><span class="info-value">${line.networkname || 'Bus IDFM'}</span></div>
            `;

            const hasAudio = line.audiblesigns_available === 'true';
            const hasVisual = line.visualsigns_available === 'true';
            document.getElementById('accessibility-info').innerHTML = `
                <div class="info-row"><span class="info-label">Accessibilité PMR</span><span class="info-value">${line.accessibility === 'true' ? '♿ Oui (plancher bas)' : '♿ Variable'}</span></div>
                <div class="info-row"><span class="info-label">Annonces sonores</span><span class="info-value">${hasAudio ? '🔊 Oui' : '❓ Variable'}</span></div>
                <div class="info-row"><span class="info-label">Affichage visuel</span><span class="info-value">${hasVisual ? '📺 Oui' : '❓ Variable'}</span></div>
                <div class="info-row"><span class="info-label">Climatisation</span><span class="info-value">${line.air_conditioning === 'true' ? '❄️ Oui' : '❓ Variable'}</span></div>
            `;

            document.getElementById('visual-info').innerHTML = `
                <div class="info-row"><span class="info-label">Couleur</span><span class="info-value"><span style="display:inline-flex;align-items:center;gap:8px;"><span style="width:24px;height:24px;background:#${color};border-radius:4px;"></span>#${color}</span></span></div>
                <div class="info-row"><span class="info-label">Aperçu</span><span class="info-value"><span style="display:inline-flex;align-items:center;justify-content:center;min-width:50px;height:36px;padding:0 12px;background:#${color};color:#${textColor};border-radius:8px;font-weight:800;font-size: ${name.length > 3 ? '0.9rem' : '1rem'};">${name}</span></span></div>
            `;

            document.getElementById('technical-info').innerHTML = `
                <div class="info-row"><span class="info-label">Code privé</span><span class="info-value">${line.privatecode || '-'}</span></div>
                <div class="info-row"><span class="info-label">Validité depuis</span><span class="info-value">${line.valid_fromdate ? formatDate(line.valid_fromdate) : '-'}</span></div>
                <div class="info-row"><span class="info-label">Validité jusqu'à</span><span class="info-value">${line.valid_todate ? formatDate(line.valid_todate) : 'En cours'}</span></div>
            `;
        }

        // Données globales pour les routes Bus
        let lineRoutes = [];
        let currentRouteId = 'all';
        let mapLayers = {};

        // Load map Bus
        async function loadLineMap() {
            try {
                console.log('Loading Bus map for line:', lineId);
                const response = await fetch('<?php echo SITE_URL; ?>/api/trace-ligne.php?id=' + lineId);
                const data = await response.json();
                console.log('Bus Map data:', data);

                lineRoutes = data.routes || [];

                document.getElementById('map-loading').style.display = 'none';
                document.getElementById('line-map').style.display = 'block';

                // Initialize map
                map = L.map('line-map').setView([48.8566, 2.3522], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                // Si plusieurs routes (directions)
                if (lineRoutes.length > 1) {
                    const selector = document.getElementById('route-selector');
                    selector.innerHTML = '<option value="all">📍 Voir les deux directions</option>';
                    lineRoutes.forEach((route, i) => {
                        const name = route.name || `Direction ${i + 1}`;
                        selector.innerHTML += `<option value="${route.id}">${name}</option>`;
                    });
                    document.getElementById('route-selector-container').style.display = 'block';

                    // Show direction info in hero
                    document.getElementById('direction-info').style.display = 'block';
                    document.getElementById('direction-list').innerHTML = lineRoutes.map(r =>
                        `<span class="direction-badge">${r.name || 'Direction'}</span>`
                    ).join(' ↔ ');
                }

                // Display all routes by default for bus
                selectRoute('all');

            } catch (error) {
                console.error('Map error:', error);
                document.getElementById('map-loading').innerHTML = '<p style="text-align: center; padding: 40px; color: var(--gray-500);">❌ Carte non disponible</p>';
            }
        }

        // Select and display a specific route
        function selectRoute(routeId) {
            currentRouteId = routeId;

            // Clear existing layers
            Object.values(mapLayers).forEach(layer => map.removeLayer(layer));
            mapLayers = {};

            const bounds = [];
            const routesToShow = routeId === 'all' ? lineRoutes : lineRoutes.filter(r => r.id == routeId);

            routesToShow.forEach((route, index) => {
                // Draw trace - Style IDFM avec bordure
                if (route.shape && route.shape.geometry) {
                    // D'abord, ajouter une bordure blanche (tracé plus large en dessous)
                    const borderLayer = L.geoJSON(route.shape, {
                        style: {
                            color: '#ffffff',
                            weight: 8,
                            opacity: 1,
                            lineCap: 'round',
                            lineJoin: 'round'
                        }
                    }).addTo(map);
                    mapLayers['trace_border_' + route.id] = borderLayer;

                    // Ensuite, le tracé principal par-dessus
                    const layer = L.geoJSON(route.shape, {
                        style: {
                            color: '#' + lineColor,
                            weight: 5,
                            opacity: 1,
                            lineCap: 'round',
                            lineJoin: 'round'
                        }
                    }).addTo(map);
                    mapLayers['trace_' + route.id] = layer;

                    try {
                        const layerBounds = layer.getBounds();
                        if (layerBounds.isValid()) {
                            bounds.push(layerBounds.getSouthWest());
                            bounds.push(layerBounds.getNorthEast());
                        }
                    } catch (e) { }
                }

                // Add stop markers with numbers
                const stops = route.stops || [];
                stops.forEach((stop, i) => {
                    const lat = parseFloat(stop.stop_lat);
                    const lon = parseFloat(stop.stop_lon);

                    if (lat && lon && !isNaN(lat) && !isNaN(lon)) {
                        bounds.push([lat, lon]);

                        const isTerminus = i === 0 || i === stops.length - 1;
                        const stopNumber = i + 1;
                        
                        // Marqueur de fond blanc (bordure)
                        const bgMarker = L.circleMarker([lat, lon], {
                            radius: isTerminus ? 12 : 8,
                            fillColor: '#ffffff',
                            color: '#' + lineColor,
                            weight: 3,
                            fillOpacity: 1
                        }).addTo(map);
                        mapLayers['stop_bg_' + route.id + '_' + i] = bgMarker;

                        // Marqueur principal avec numéro pour les terminus
                        if (isTerminus) {
                            const divIcon = L.divIcon({
                                className: 'stop-number-icon',
                                html: `<div style="width: 24px; height: 24px; background: #${lineColor}; color: white; border: 2px solid white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">${stopNumber}</div>`,
                                iconSize: [24, 24],
                                iconAnchor: [12, 12]
                            });
                            const marker = L.marker([lat, lon], { icon: divIcon });
                            marker.bindPopup(`
                                <div style="text-align: center; min-width: 140px;">
                                    <div style="background: #${lineColor}; color: white; padding: 4px 8px; border-radius: 4px; margin-bottom: 8px; font-weight: 600;">
                                        Arrêt n°${stopNumber}
                                    </div>
                                    <strong>🚏 ${stop.stop_name || 'Arrêt'}</strong>
                                    ${stop.commune ? `<br><small style="color: #666;">${stop.commune}</small>` : ''}
                                </div>
                            `);
                            marker.addTo(map);
                            mapLayers['stop_' + route.id + '_' + i] = marker;
                        } else {
                            // Arrêts intermédiaires - cercle simple avec survol
                            bgMarker.bindPopup(`
                                <div style="text-align: center; min-width: 140px;">
                                    <div style="background: #${lineColor}; color: white; padding: 4px 8px; border-radius: 4px; margin-bottom: 8px; font-weight: 600;">
                                        Arrêt n°${stopNumber}
                                    </div>
                                    <strong>🚏 ${stop.stop_name || 'Arrêt'}</strong>
                                    ${stop.commune ? `<br><small style="color: #666;">${stop.commune}</small>` : ''}
                                </div>
                            `);
                        }
                    }
                });
            });

            // Fit bounds
            if (bounds.length > 0) {
                try {
                    map.fitBounds(bounds, { padding: [40, 40] });
                } catch (e) { }
            }

            setTimeout(() => map.invalidateSize(), 100);
        }

        // Stations
        let allStations = [];
        let currentStationsRouteId = 'all';
        let stopsReversed = false; // Pour inverser l'ordre des arrêts

        async function loadStations() {
            const timeline = document.getElementById('stations-timeline');
            if (timeline.dataset.loaded) return;

            try {
                const response = await fetch('<?php echo SITE_URL; ?>/api/trace-ligne.php?id=' + lineId);
                const data = await response.json();

                if (!lineRoutes.length) {
                    lineRoutes = data.routes || [];
                }

                // Toujours peupler le sélecteur de direction
                const selector = document.getElementById('stations-route-select');
                
                if (lineRoutes.length > 1) {
                    // Plusieurs directions disponibles
                    selector.innerHTML = '<option value="all">📍 Voir tous les arrêts</option>';
                    lineRoutes.forEach((route, i) => {
                        const terminus = route.terminus_b || route.name || `Direction ${i + 1}`;
                        const count = route.stops_count || (route.stops ? route.stops.length : 0);
                        selector.innerHTML += `<option value="${route.id}">➔ Vers ${terminus} (${count} arrêts)</option>`;
                    });
                } else if (lineRoutes.length === 1) {
                    // Une seule direction
                    const route = lineRoutes[0];
                    const count = route.stops_count || (route.stops ? route.stops.length : 0);
                    selector.innerHTML = `<option value="${route.id}">${route.name || 'Ligne complète'} (${count} arrêts)</option>`;
                } else {
                    selector.innerHTML = '<option value="all">📍 Tous les arrêts</option>';
                }

                // Afficher les arrêts par défaut
                if (lineRoutes.length > 0 && lineRoutes[0].stops && lineRoutes[0].stops.length > 0) {
                    // Sélectionner la première direction par défaut
                    selector.value = lineRoutes[0].id;
                    filterStationsByRoute(lineRoutes[0].id);
                } else if (data.stops && data.stops.length > 0) {
                    displayStations(data.stops);
                } else {
                    timeline.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">Aucun arrêt disponible</p>';
                }

                timeline.dataset.loaded = 'true';
            } catch (error) {
                console.error('Stations load error:', error);
                document.getElementById('stations-route-select').innerHTML = '<option value="">Erreur de chargement</option>';
                timeline.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">Erreur de chargement</p>';
            }
        }

        function filterStationsByRoute(routeId) {
            currentStationsRouteId = routeId;
            let stopsToShow = [];
            let terminusName = '';

            if (routeId === 'all') {
                const allStops = lineRoutes.flatMap(r => r.stops || []);
                const seen = new Set();
                stopsToShow = allStops.filter(s => {
                    if (seen.has(s.stop_name)) return false;
                    seen.add(s.stop_name);
                    return true;
                });
                terminusName = '';
            } else {
                const route = lineRoutes.find(r => r.id == routeId);
                if (route) {
                    stopsToShow = [...(route.stops || [])];
                    terminusName = route.terminus_b || (stopsToShow.length > 0 ? stopsToShow[stopsToShow.length - 1].stop_name : '');
                }
            }

            // Inverser si demandé
            if (stopsReversed) {
                stopsToShow = stopsToShow.reverse();
                if (terminusName) {
                    const route = lineRoutes.find(r => r.id == routeId);
                    if (route) {
                        terminusName = route.terminus_a || (stopsToShow.length > 0 ? stopsToShow[stopsToShow.length - 1].stop_name : '');
                    }
                }
            }

            // Mettre à jour l'indicateur de direction
            const indicator = document.getElementById('direction-indicator');
            if (terminusName && routeId !== 'all') {
                indicator.innerHTML = `<span>➔ Direction : <strong>${terminusName}</strong></span>`;
                indicator.style.display = 'block';
            } else {
                indicator.style.display = 'none';
            }

            displayStations(stopsToShow);
        }

        // Fonction pour inverser l'ordre des arrêts
        function reverseStopsOrder() {
            stopsReversed = !stopsReversed;
            const btn = document.getElementById('reverse-stops-btn');
            btn.innerHTML = stopsReversed ? '🔄 Sens inversé ✔' : '🔄 Inverser le sens';
            btn.style.background = stopsReversed ? 'var(--primary)' : 'var(--gray-100)';
            btn.style.color = stopsReversed ? 'white' : 'inherit';
            filterStationsByRoute(currentStationsRouteId);
        }

        function displayStations(stops) {
            const timeline = document.getElementById('stations-timeline');

            if (!stops || stops.length === 0) {
                timeline.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">Aucun arrêt sur cette direction</p>';
                document.getElementById('stations-count').textContent = '0 arrêts';
                return;
            }

            document.getElementById('stations-count').textContent = stops.length + ' arrêts';
            allStations = stops;

            timeline.innerHTML = stops.map((stop, index) => {
                const isTerminus = index === 0 || index === stops.length - 1;
                const stopNumber = index + 1;
                return `
                    <div class="station-item" data-name="${(stop.stop_name || '').toLowerCase()}"
                        style="${isTerminus ? 'font-weight: 700;' : ''}"
                        onclick="showStationOnMap('${(stop.stop_name || '').replace(/'/g, "\\'")}'  , ${stop.stop_lat}, ${stop.stop_lon})">
                        <span class="station-number" style="position: absolute; left: -48px; width: 24px; height: 24px; background: #${lineColor}; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700;">${stopNumber}</span>
                        <span class="station-name">${isTerminus ? '🚏 ' : ''}${stop.stop_name || 'Arrêt'}</span>
                        ${stop.commune ? `<span style="font-size: 0.75rem; color: var(--gray-400); margin-left: 8px;">${stop.commune}</span>` : ''}
                    </div>
                `;
            }).join('');

            // Apply color styles
            if (!document.getElementById('station-styles')) {
                const style = document.createElement('style');
                style.id = 'station-styles';
                style.textContent = `
                    .stations-timeline::before { background: #${lineColor}; }
                    .station-item::before { border-color: #${lineColor}; }
                    .station-item:first-child::before, .station-item:last-child::before { background: #${lineColor}; }
                    .stations-timeline { padding-left: 60px !important; }
                    .station-item { position: relative; }
                `;
                document.head.appendChild(style);
            }
        }

        function filterStations(query) {
            const searchTerm = query.toLowerCase().trim();
            const items = document.querySelectorAll('.station-item');

            items.forEach(item => {
                const name = item.dataset.name || '';
                item.style.display = (searchTerm === '' || name.includes(searchTerm)) ? '' : 'none';
            });
        }

        function showStationOnMap(name, lat, lon) {
            if (map && lat && lon) {
                showTab('plan');
                setTimeout(() => {
                    map.setView([lat, lon], 16);
                    L.popup()
                        .setLatLng([lat, lon])
                        .setContent(`<strong>🚏 ${name}</strong>`)
                        .openOn(map);
                }, 100);
            }
        }

        // Horaires Bus
        let horairesRoutes = [];
        let currentHorairesRouteId = 'all';

        async function populateStopSelector() {
            const selector = document.getElementById('stop-selector');

            try {
                const response = await fetch('<?php echo SITE_URL; ?>/api/trace-ligne.php?id=' + lineId);
                const data = await response.json();

                horairesRoutes = data.routes || [];

                // Populate route selector if multiple directions
                if (horairesRoutes.length > 1) {
                    const routeSelector = document.getElementById('horaires-route-select');
                    routeSelector.innerHTML = '<option value="all">📍 Toutes les directions</option>';
                    horairesRoutes.forEach((route, i) => {
                        const name = route.name || `Direction ${i + 1}`;
                        const count = route.stops_count || (route.stops ? route.stops.length : 0);
                        routeSelector.innerHTML += `<option value="${route.id}">${name} (${count} arrêts)</option>`;
                    });
                    document.getElementById('horaires-route-selector').style.display = 'block';
                }

                // Display all stops
                if (data.stops && data.stops.length > 0) {
                    populateStopSelectorWithStops(data.stops);
                } else if (horairesRoutes.length > 0) {
                    filterStopsByRoute('all');
                } else {
                    selector.innerHTML = '<option value="">Aucun arrêt disponible</option>';
                }
            } catch (error) {
                console.error('Stop selector error:', error);
                selector.innerHTML = '<option value="">Erreur de chargement</option>';
            }
        }

        function filterStopsByRoute(routeId) {
            currentHorairesRouteId = routeId;
            let stopsToShow = [];

            if (routeId === 'all') {
                const allStops = horairesRoutes.flatMap(r => r.stops || []);
                const seen = new Set();
                stopsToShow = allStops.filter(s => {
                    if (seen.has(s.stop_name)) return false;
                    seen.add(s.stop_name);
                    return true;
                });
            } else {
                const route = horairesRoutes.find(r => r.id == routeId);
                stopsToShow = route ? (route.stops || []) : [];
            }

            populateStopSelectorWithStops(stopsToShow);
        }

        function populateStopSelectorWithStops(stops) {
            const selector = document.getElementById('stop-selector');

            if (stops && stops.length > 0) {
                const uniqueStops = [...new Map(stops.map(s => [s.stop_name, s])).values()];

                selector.innerHTML = '<option value="">-- Sélectionner un arrêt --</option>' +
                    uniqueStops.map((stop, index) =>
                        `<option value="${index}">🚏 ${stop.stop_name}</option>`
                    ).join('');

                window.lineStops = uniqueStops;
                document.getElementById('realtime-status').innerHTML = '✅ Prêt';
                document.getElementById('realtime-status').style.background = '#DEF7EC';
            } else {
                selector.innerHTML = '<option value="">Aucun arrêt sur cette direction</option>';
                window.lineStops = [];
            }

            document.getElementById('departures-list').innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">👆 Sélectionnez un arrêt pour voir les prochains passages</p>';
            document.getElementById('refresh-btn').style.display = 'none';
        }

        async function loadDepartures() {
            const selector = document.getElementById('stop-selector');
            const stopIndex = selector.value;
            const departuresList = document.getElementById('departures-list');
            const refreshBtn = document.getElementById('refresh-btn');

            if (!stopIndex || !window.lineStops) {
                departuresList.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">👆 Sélectionnez un arrêt</p>';
                refreshBtn.style.display = 'none';
                return;
            }

            const stop = window.lineStops[parseInt(stopIndex)];
            const stopName = stop.stop_name;

            departuresList.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <div class="loading-spinner"></div>
                    <p style="color: var(--gray-500); margin-top: 10px;">Chargement des prochains bus pour ${stopName}...</p>
                </div>
            `;

            document.getElementById('realtime-status').innerHTML = '⏳ Chargement...';
            document.getElementById('realtime-status').style.background = '#E0E7FF';

            try {
                const response = await fetch(`<?php echo SITE_URL; ?>/api/prochains-passages-bus.php?name=${encodeURIComponent(stopName)}&line=${encodeURIComponent(lineId)}`);
                const data = await response.json();

                console.log('Bus API Response:', data);

                refreshBtn.style.display = 'block';

                if (data.success && data.departures && data.departures.length > 0) {
                    document.getElementById('realtime-status').innerHTML = '🔴 Temps réel';
                    document.getElementById('realtime-status').style.background = '#DEF7EC';

                    departuresList.innerHTML = `
                        <div style="font-weight: 600; margin-bottom: 12px; color: var(--gray-700);">
                            🚌 Prochains départs depuis <strong>${stopName}</strong> :
                        </div>
                        <div style="font-size: 12px; color: var(--gray-500); margin-bottom: 16px; padding: 8px 12px; background: var(--gray-50); border-radius: 8px;">
                            ⓘ Les horaires ci-dessous indiquent l'heure de <strong>départ</strong> (passage du bus à cet arrêt)
                        </div>
                        ${data.departures.slice(0, 6).map(dep => `
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; margin-bottom: 8px; background: linear-gradient(135deg, #${lineColor}10, #${lineColor}05); border-radius: 10px; border-left: 4px solid #${lineColor};">
                                <div>
                                    <div style="font-weight: 600; color: var(--gray-900);">
                                        → ${dep.direction || dep.destination || 'Direction'}
                                    </div>
                                    <div style="font-size: 12px; color: var(--gray-500);">
                                        🕐 Départ prévu : ${dep.expected_time ? new Date(dep.expected_time).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '--:--'}
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 1.5rem; font-weight: 800; color: #${lineColor};">
                                        ${dep.wait_minutes !== null ? (dep.wait_minutes <= 0 ? '<span style="color: #059669; animation: pulse 1s infinite;">🚌 À l\'arrêt</span>' : dep.wait_minutes + ' min') : '--'}
                                    </div>
                                    ${dep.status === 'delayed' ? '<span style="font-size: 11px; color: #DC2626;">⚠️ Retard</span>' : ''}
                                </div>
                            </div>
                        `).join('')}
                    `;
                } else {
                    departuresList.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: var(--gray-500);">
                            <p>😔 Aucun bus prévu pour le moment</p>
                            ${data.error ? `<p style="font-size: 12px; margin-top: 10px;">${data.error}</p>` : ''}
                            <p style="font-size: 12px; margin-top: 10px;">Consultez l'appli IDFM pour plus d'informations</p>
                        </div>
                    `;
                    document.getElementById('realtime-status').innerHTML = '⚠️ Pas de données';
                    document.getElementById('realtime-status').style.background = '#FEF3C7';
                }
            } catch (error) {
                console.error('Departures error:', error);
                departuresList.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">❌ Erreur de chargement</p>';
            }
        }

        // Schedule links
        function displayScheduleLinks(line) {
            const name = line.shortname_line || '';
            const operator = (line.operatorname || '').toLowerCase();

            loadSchedules(line.id_line);

            let links = [];

            if (operator.includes('ratp')) {
                links.push({
                    icon: '🚌',
                    title: 'RATP - Bus ' + name,
                    desc: 'Horaires et plan de ligne',
                    url: `https://www.ratp.fr/horaires?line=bus-${name}`,
                    color: '#003B82'
                });
            }

            links.push({
                icon: '🗺️',
                title: 'Île-de-France Mobilités',
                desc: 'Plans et informations réseau',
                url: 'https://www.iledefrance-mobilites.fr/',
                color: '#00814F'
            });

            links.push({
                icon: '📱',
                title: 'Vianavigo',
                desc: 'Calcul d\'itinéraire',
                url: 'https://www.vianavigo.com/',
                color: '#E4002B'
            });

            document.getElementById('schedule-links').innerHTML = links.map(link => `
                <a href="${link.url}" target="_blank" class="schedule-link-card">
                    <div class="schedule-icon" style="background: ${link.color}20; color: ${link.color};">${link.icon}</div>
                    <div class="schedule-info">
                        <h3>${link.title}</h3>
                        <p>${link.desc}</p>
                    </div>
                </a>
            `).join('');
        }

        // Load schedule data
        async function loadSchedules(lineId) {
            try {
                const response = await fetch('<?php echo SITE_URL; ?>/api/horaires.php?line=' + lineId);
                const data = await response.json();

                if (data.success) {
                    const schedules = data.theoretical_schedules;
                    document.getElementById('service-hours').innerHTML = `
                        <div class="info-row">
                            <span class="info-label">Premier bus (semaine)</span>
                            <span class="info-value">${schedules.weekday?.first_train || '06:00'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Dernier bus (semaine)</span>
                            <span class="info-value">${schedules.weekday?.last_train || '21:00'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Premier bus (dimanche)</span>
                            <span class="info-value">${schedules.sunday?.first_train || '07:00'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Service Noctilien</span>
                            <span class="info-value">Voir lignes N01-N153</span>
                        </div>
                    `;

                    document.getElementById('frequency-info').innerHTML = `
                        <div class="info-row">
                            <span class="info-label">Heures de pointe</span>
                            <span class="info-value" style="color: var(--primary); font-weight: 700;">⚡ ${schedules.weekday?.frequency_peak || '8-12 min'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Heures creuses</span>
                            <span class="info-value">${schedules.weekday?.frequency_offpeak || '15-20 min'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Soirée / Week-end</span>
                            <span class="info-value">${schedules.weekday?.frequency_night || '20-30 min'}</span>
                        </div>
                    `;

                    // Bus specific tips
                    document.getElementById('travel-tips').innerHTML = `
                        <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--gray-100);">
                            <span style="font-size: 1.2rem;">✓</span>
                            <span style="color: var(--gray-700);">Validez votre titre de transport à la montée</span>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--gray-100);">
                            <span style="font-size: 1.2rem;">✓</span>
                            <span style="color: var(--gray-700);">Les Noctiliens circulent la nuit (0h30-5h30) sur certains itinéraires</span>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--gray-100);">
                            <span style="font-size: 1.2rem;">✓</span>
                            <span style="color: var(--gray-700);">Le temps d'attente peut varier en fonction du trafic routier</span>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 0;">
                            <span style="font-size: 1.2rem;">✓</span>
                            <span style="color: var(--gray-700);">Téléchargez l'appli IDFM pour le suivi GPS en temps réel</span>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Schedule error:', error);
            }
        }

        function formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
        }

        function darkenColor(hex, percent) {
            const num = parseInt(hex, 16);
            const amt = Math.round(2.55 * percent);
            const R = Math.max(0, (num >> 16) - amt);
            const G = Math.max(0, ((num >> 8) & 0x00FF) - amt);
            const B = Math.max(0, (num & 0x0000FF) - amt);
            return ((1 << 24) + (R << 16) + (G << 8) + B).toString(16).slice(1);
        }

        loadLineDetails();
    </script>
</body>

</html>
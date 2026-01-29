<?php
/**
 * Mon Réseau IDF - Détail d'une ligne RER
 * Page dédiée aux lignes RER (A, B, C, D, E) avec gestion des branches multiples
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Récupérer l'ID de la ligne
$lineId = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($lineId)) {
    header('Location: ' . SITE_URL . '/lignes.php');
    exit;
}

$pageTitle = 'Détail de la ligne RER';
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
            width: 120px;
            height: 120px;
            border-radius: var(--radius-2xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 3rem;
            background: white;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        }

        .line-hero-info h1 {
            font-size: 2.5rem;
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

        /* Stations */
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
            width: 14px;
            height: 14px;
            background: white;
            border: 4px solid;
            border-radius: 50%;
        }

        .station-item:first-child::before,
        .station-item:last-child::before {
            width: 18px;
            height: 18px;
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

        /* RER specific - branches info */
        .branches-info {
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            border-radius: var(--radius-lg);
            padding: var(--spacing-4);
            margin-top: var(--spacing-4);
        }

        .branches-info h4 {
            font-size: var(--font-size-sm);
            margin-bottom: var(--spacing-2);
            opacity: 0.9;
        }

        .branches-list {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-2);
        }

        .branch-badge {
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: var(--radius);
            font-size: var(--font-size-xs);
        }

        /* Route selector style */
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
            <p>Chargement des informations RER...</p>
        </div>
    </div>

    <!-- Line Content -->
    <div id="line-page" style="display: none;">
        <!-- Hero -->
        <section class="line-hero" id="line-hero">
            <div class="container">
                <a href="<?php echo SITE_URL; ?>/lignes.php#mode-rer" class="back-link">← Retour aux lignes RER</a>
                <div class="line-hero-content">
                    <div class="line-hero-badge" id="hero-badge"></div>
                    <div class="line-hero-info">
                        <h1 id="hero-title">Chargement...</h1>
                        <div class="line-hero-meta" id="hero-meta"></div>
                        <div class="line-hero-actions" id="hero-actions"></div>
                    </div>
                </div>
                <!-- Branches info -->
                <div class="branches-info" id="branches-info" style="display: none;">
                    <h4>Branches desservies :</h4>
                    <div class="branches-list" id="branches-list"></div>
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
                    <button class="tab-btn" onclick="showTab('stations')">🚏 Stations</button>
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
                    <!-- Sélecteur de trajet RER -->
                    <div class="route-selector-wrapper" id="route-selector-container">
                        <label>🚆 Choisir une branche :</label>
                        <select id="route-selector" onchange="selectRoute(this.value)">
                            <option value="all">📍 Voir toutes les branches</option>
                        </select>
                    </div>
                    <div class="map-container">
                        <div class="map-loading" id="map-loading">
                            <div class="loading-spinner"></div>
                        </div>
                        <div id="line-map" style="display: none;"></div>
                    </div>
                </div>

                <!-- Tab: Stations -->
                <div class="tab-content" id="tab-stations">
                    <!-- Sélecteur de trajet pour les stations -->
                    <div class="route-selector-wrapper" id="stations-route-selector">
                        <label>🚆 Afficher les stations de la branche :</label>
                        <select id="stations-route-select" onchange="filterStationsByRoute(this.value)">
                            <option value="all">📍 Toutes les stations</option>
                        </select>
                    </div>
                    <!-- Recherche de station -->
                    <div style="margin-bottom: var(--spacing-4);">
                        <input type="text" id="station-search" placeholder="🔍 Rechercher une gare..."
                            style="width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); font-size: 1rem;"
                            oninput="filterStations(this.value)">
                    </div>
                    <div class="stations-list">
                        <div class="info-card-header">
                            <span class="info-card-icon">🚉</span>
                            <span class="info-card-title">Gares de la ligne</span>
                            <span id="stations-count" style="margin-left: auto; color: var(--gray-500);"></span>
                        </div>
                        <div class="stations-timeline" id="stations-timeline">
                            <p style="color: var(--gray-500);">Chargement des gares...</p>
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

                        <!-- Sélecteur de branche pour horaires -->
                        <div id="horaires-route-selector" style="margin-bottom: var(--spacing-4);">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--gray-700);">
                                🚆 Choisir une branche :
                            </label>
                            <select id="horaires-route-select" onchange="filterStopsByRoute(this.value)"
                                style="width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); font-size: 1rem; background: white; cursor: pointer;">
                                <option value="all">📍 Toutes les branches</option>
                            </select>
                        </div>

                        <!-- Sélecteur de gare -->
                        <div style="margin-bottom: var(--spacing-4);">
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--gray-700);">
                                📍 Choisir une gare :
                            </label>
                            <select id="stop-selector"
                                style="width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); font-size: 1rem; background: white; cursor: pointer;"
                                onchange="loadDepartures()">
                                <option value="">-- Sélectionner une gare --</option>
                            </select>
                        </div>

                        <!-- Liste des prochains passages -->
                        <div id="departures-list">
                            <p style="color: var(--gray-500); text-align: center; padding: 20px;">
                                👆 Sélectionnez une gare pour voir les prochains passages
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

                    <!-- Heures de pointe -->
                    <div class="info-card" style="margin-bottom: var(--spacing-6);">
                        <div class="info-card-header">
                            <span class="info-card-icon">⚠️</span>
                            <span class="info-card-title">Heures de pointe</span>
                        </div>
                        <div id="peak-hours"></div>
                    </div>

                    <!-- Conseils RER -->
                    <div class="info-card" style="margin-bottom: var(--spacing-6);">
                        <div class="info-card-header">
                            <span class="info-card-icon">💡</span>
                            <span class="info-card-title">Conseils pratiques RER</span>
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
        let lineColor = 'E3051C'; // Rouge RER A par défaut
        let map = null;

        // Couleurs officielles RER
        const rerColors = {
            'A': 'E3051C',
            'B': '5291CE',
            'C': 'FFCE00',
            'D': '00814F',
            'E': 'C04191'
        };

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
                            <p>❌ Ligne RER non trouvée</p>
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
            const name = line.shortname_line || line.name_line || 'RER';
            const fullName = line.name_line || 'RER ' + name;
            lineColor = line.colourweb_hexa || rerColors[name.toUpperCase()] || 'E3051C';
            const textColor = line.textcolourweb_hexa || 'FFFFFF';

            document.title = fullName + ' - Mon Réseau IDF';

            // Hero
            document.getElementById('line-hero').style.background = `linear-gradient(135deg, #${lineColor} 0%, #${darkenColor(lineColor, 20)} 100%)`;
            document.getElementById('hero-badge').innerHTML = name;
            document.getElementById('hero-badge').style.color = '#' + lineColor;
            document.getElementById('hero-title').textContent = fullName;

            document.getElementById('hero-meta').innerHTML = `
                <span>🚆 RER - Réseau Express Régional</span>
                <span>🏢 ${line.operatorname || 'RATP / SNCF'}</span>
                <span>📍 ${line.networkname || 'Île-de-France'}</span>
            `;

            // Action buttons
            document.getElementById('hero-actions').innerHTML = `
                <a href="#" class="hero-btn" onclick="showTab('plan'); return false;">🗺️ Voir le plan</a>
                <a href="#" class="hero-btn" onclick="showTab('horaires'); return false;">🕐 Horaires</a>
                <a href="https://www.transilien.com/fr/page-ligne/ligne-${name.toLowerCase()}" target="_blank" class="hero-btn">📄 Site officiel</a>
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
                <div class="info-row"><span class="info-label">Nom court</span><span class="info-value">RER ${line.shortname_line || '-'}</span></div>
                <div class="info-row"><span class="info-label">Mode de transport</span><span class="info-value">🚆 RER</span></div>
                <div class="info-row"><span class="info-label">Sous-mode</span><span class="info-value">${line.transportsubmode || 'Regional Rail'}</span></div>
                <div class="info-row"><span class="info-label">Statut</span><span class="info-value"><span class="status-badge ${line.status === 'active' ? 'status-active' : 'status-inactive'}">${line.status === 'active' ? 'Active' : 'Inactive'}</span></span></div>
            `;

            document.getElementById('operator-info').innerHTML = `
                <div class="info-row"><span class="info-label">Exploitant</span><span class="info-value">${line.operatorname || 'RATP / SNCF'}</span></div>
                <div class="info-row"><span class="info-label">Code exploitant</span><span class="info-value">${line.operatorref || '-'}</span></div>
                <div class="info-row"><span class="info-label">Réseau</span><span class="info-value">${line.networkname || 'Transilien'}</span></div>
            `;

            document.getElementById('accessibility-info').innerHTML = `
                <div class="info-row"><span class="info-label">Accessibilité PMR</span><span class="info-value">${line.accessibility === 'true' ? '♿ Oui (gares équipées)' : '♿ Partielle'}</span></div>
                <div class="info-row"><span class="info-label">Annonces sonores</span><span class="info-value">🔊 Oui</span></div>
                <div class="info-row"><span class="info-label">Affichage visuel</span><span class="info-value">📺 Oui</span></div>
                <div class="info-row"><span class="info-label">Climatisation</span><span class="info-value">❄️ Oui (trains récents)</span></div>
            `;

            document.getElementById('visual-info').innerHTML = `
                <div class="info-row"><span class="info-label">Couleur</span><span class="info-value"><span style="display:inline-flex;align-items:center;gap:8px;"><span style="width:24px;height:24px;background:#${color};border-radius:4px;"></span>#${color}</span></span></div>
                <div class="info-row"><span class="info-label">Aperçu</span><span class="info-value"><span style="display:inline-flex;align-items:center;justify-content:center;min-width:50px;height:36px;padding:0 12px;background:#${color};color:#${textColor};border-radius:8px;font-weight:800;">${name}</span></span></div>
            `;

            document.getElementById('technical-info').innerHTML = `
                <div class="info-row"><span class="info-label">Code privé</span><span class="info-value">${line.privatecode || '-'}</span></div>
                <div class="info-row"><span class="info-label">Validité depuis</span><span class="info-value">${line.valid_fromdate ? formatDate(line.valid_fromdate) : '-'}</span></div>
                <div class="info-row"><span class="info-label">Validité jusqu'à</span><span class="info-value">${line.valid_todate ? formatDate(line.valid_todate) : 'En cours'}</span></div>
            `;
        }

        // Données globales pour les routes RER
        let lineRoutes = [];
        let currentRouteId = 'all';
        let mapLayers = {};

        // Load map RER avec branches
        async function loadLineMap() {
            try {
                console.log('Loading RER map for line:', lineId);
                const response = await fetch('<?php echo SITE_URL; ?>/api/trace-ligne.php?id=' + lineId);
                const data = await response.json();
                console.log('RER Map data:', data);

                lineRoutes = data.routes || [];

                document.getElementById('map-loading').style.display = 'none';
                document.getElementById('line-map').style.display = 'block';

                // Initialize map
                map = L.map('line-map').setView([48.8566, 2.3522], 11);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                // Populate route selector
                const selector = document.getElementById('route-selector');
                selector.innerHTML = '<option value="all">📍 Voir toutes les branches (' + lineRoutes.length + ')</option>';
                lineRoutes.forEach((route, i) => {
                    const name = route.name || `Branche ${i + 1}`;
                    selector.innerHTML += `<option value="${route.id}">${name}</option>`;
                });

                // Show branches info in hero
                if (lineRoutes.length > 1) {
                    document.getElementById('branches-info').style.display = 'block';
                    document.getElementById('branches-list').innerHTML = lineRoutes.map(r =>
                        `<span class="branch-badge">${r.name || 'Branche'}</span>`
                    ).join('');
                }

                // Display first route by default
                selectRoute(lineRoutes.length > 0 ? lineRoutes[0].id : 'all');

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
                // Draw trace
                if (route.shape && route.shape.geometry) {
                    const layer = L.geoJSON(route.shape, {
                        style: {
                            color: '#' + lineColor,
                            weight: 5,
                            opacity: 0.9
                        }
                    }).addTo(map);
                    mapLayers['trace_' + route.id] = layer;

                    try {
                        const layerBounds = layer.getBounds();
                        if (layerBounds.isValid()) {
                            bounds.push(layerBounds.getSouthWest());
                            bounds.push(layerBounds.getNorthEast());
                        }
                    } catch (e) {}
                }

                // Add station markers
                const stops = route.stops || [];
                stops.forEach((stop, i) => {
                    const lat = parseFloat(stop.stop_lat);
                    const lon = parseFloat(stop.stop_lon);

                    if (lat && lon && !isNaN(lat) && !isNaN(lon)) {
                        bounds.push([lat, lon]);

                        const isTerminus = i === 0 || i === stops.length - 1;
                        const marker = L.circleMarker([lat, lon], {
                            radius: isTerminus ? 10 : 7,
                            fillColor: isTerminus ? '#' + lineColor : '#ffffff',
                            color: '#' + lineColor,
                            weight: isTerminus ? 4 : 3,
                            fillOpacity: 1
                        });

                        marker.bindPopup(`
                            <div style="text-align: center; min-width: 150px;">
                                <strong style="font-size: 14px;">🚉 ${stop.stop_name || 'Gare'}</strong>
                                ${stop.commune ? `<br><small style="color: #666;">${stop.commune}</small>` : ''}
                            </div>
                        `);
                        marker.addTo(map);
                        mapLayers['stop_' + route.id + '_' + i] = marker;
                    }
                });
            });

            // Fit bounds
            if (bounds.length > 0) {
                try {
                    map.fitBounds(bounds, { padding: [50, 50] });
                } catch (e) {}
            }

            setTimeout(() => map.invalidateSize(), 100);
        }

        // Stations
        let allStations = [];
        let currentStationsRouteId = 'all';

        async function loadStations() {
            const timeline = document.getElementById('stations-timeline');
            if (timeline.dataset.loaded) return;

            try {
                const response = await fetch('<?php echo SITE_URL; ?>/api/trace-ligne.php?id=' + lineId);
                const data = await response.json();

                if (!lineRoutes.length) {
                    lineRoutes = data.routes || [];
                }

                // Populate route selector
                const selector = document.getElementById('stations-route-select');
                selector.innerHTML = '<option value="all">📍 Toutes les gares</option>';
                lineRoutes.forEach((route, i) => {
                    const name = route.name || `Branche ${i + 1}`;
                    const count = route.stops_count || 0;
                    selector.innerHTML += `<option value="${route.id}">${name} (${count} gares)</option>`;
                });

                // Display first route by default
                if (lineRoutes.length > 0) {
                    filterStationsByRoute(lineRoutes[0].id);
                } else if (data.stops && data.stops.length > 0) {
                    displayStations(data.stops);
                } else {
                    timeline.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">Aucune gare disponible</p>';
                }

                timeline.dataset.loaded = 'true';
            } catch (error) {
                timeline.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">Erreur de chargement</p>';
            }
        }

        function filterStationsByRoute(routeId) {
            currentStationsRouteId = routeId;
            let stopsToShow = [];

            if (routeId === 'all') {
                const allStops = lineRoutes.flatMap(r => r.stops || []);
                const seen = new Set();
                stopsToShow = allStops.filter(s => {
                    if (seen.has(s.stop_name)) return false;
                    seen.add(s.stop_name);
                    return true;
                });
            } else {
                const route = lineRoutes.find(r => r.id == routeId);
                stopsToShow = route ? (route.stops || []) : [];
            }

            displayStations(stopsToShow);
        }

        function displayStations(stops) {
            const timeline = document.getElementById('stations-timeline');

            if (!stops || stops.length === 0) {
                timeline.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">Aucune gare sur cette branche</p>';
                document.getElementById('stations-count').textContent = '0 gares';
                return;
            }

            document.getElementById('stations-count').textContent = stops.length + ' gares';
            allStations = stops;

            timeline.innerHTML = stops.map((stop, index) => {
                const isTerminus = index === 0 || index === stops.length - 1;
                return `
                    <div class="station-item" data-name="${(stop.stop_name || '').toLowerCase()}"
                        style="${isTerminus ? 'font-weight: 700;' : ''}"
                        onclick="showStationOnMap('${stop.stop_name}', ${stop.stop_lat}, ${stop.stop_lon})">
                        <span class="station-name">${isTerminus ? '🚉 ' : ''}${stop.stop_name || 'Gare'}</span>
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
                    map.setView([lat, lon], 14);
                    L.popup()
                        .setLatLng([lat, lon])
                        .setContent(`<strong>🚉 ${name}</strong>`)
                        .openOn(map);
                }, 100);
            }
        }

        // Horaires RER
        let horairesRoutes = [];
        let currentHorairesRouteId = 'all';

        async function populateStopSelector() {
            const selector = document.getElementById('stop-selector');

            try {
                const response = await fetch('<?php echo SITE_URL; ?>/api/trace-ligne.php?id=' + lineId);
                const data = await response.json();

                horairesRoutes = data.routes || [];

                // Populate route selector
                const routeSelector = document.getElementById('horaires-route-select');
                routeSelector.innerHTML = '<option value="all">📍 Toutes les branches</option>';
                horairesRoutes.forEach((route, i) => {
                    const name = route.name || `Branche ${i + 1}`;
                    const count = route.stops_count || (route.stops ? route.stops.length : 0);
                    routeSelector.innerHTML += `<option value="${route.id}">${name} (${count} gares)</option>`;
                });

                // Display stops from first route
                if (horairesRoutes.length > 0) {
                    filterStopsByRoute(horairesRoutes[0].id);
                } else if (data.stops && data.stops.length > 0) {
                    populateStopSelectorWithStops(data.stops);
                } else {
                    selector.innerHTML = '<option value="">Aucune gare disponible</option>';
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

                selector.innerHTML = '<option value="">-- Sélectionner une gare --</option>' +
                    uniqueStops.map((stop, index) =>
                        `<option value="${index}">🚉 ${stop.stop_name}</option>`
                    ).join('');

                window.lineStops = uniqueStops;
                document.getElementById('realtime-status').innerHTML = '✅ Prêt';
                document.getElementById('realtime-status').style.background = '#DEF7EC';
            } else {
                selector.innerHTML = '<option value="">Aucune gare sur cette branche</option>';
                window.lineStops = [];
            }

            document.getElementById('departures-list').innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">👆 Sélectionnez une gare pour voir les prochains passages</p>';
            document.getElementById('refresh-btn').style.display = 'none';
        }

        async function loadDepartures() {
            const selector = document.getElementById('stop-selector');
            const stopIndex = selector.value;
            const departuresList = document.getElementById('departures-list');
            const refreshBtn = document.getElementById('refresh-btn');

            if (!stopIndex || !window.lineStops) {
                departuresList.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">👆 Sélectionnez une gare</p>';
                refreshBtn.style.display = 'none';
                return;
            }

            const stop = window.lineStops[parseInt(stopIndex)];
            const stopName = stop.stop_name;

            departuresList.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <div class="loading-spinner"></div>
                    <p style="color: var(--gray-500); margin-top: 10px;">Chargement des prochains trains pour ${stopName}...</p>
                </div>
            `;

            document.getElementById('realtime-status').innerHTML = '⏳ Chargement...';
            document.getElementById('realtime-status').style.background = '#E0E7FF';

            try {
                const response = await fetch(`<?php echo SITE_URL; ?>/api/prochains-passages-rer.php?name=${encodeURIComponent(stopName)}&line=${encodeURIComponent(lineId)}`);
                const data = await response.json();

                console.log('RER API Response:', data);

                refreshBtn.style.display = 'block';

                if (data.success && data.departures && data.departures.length > 0) {
                    document.getElementById('realtime-status').innerHTML = '🔴 Temps réel';
                    document.getElementById('realtime-status').style.background = '#DEF7EC';

                    departuresList.innerHTML = `
                        <div style="font-weight: 600; margin-bottom: 12px; color: var(--gray-700);">
                            🚉 Prochains trains depuis <strong>${stopName}</strong> :
                        </div>
                        ${data.departures.slice(0, 8).map(dep => `
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; margin-bottom: 8px; background: linear-gradient(135deg, #${lineColor}10, #${lineColor}05); border-radius: 10px; border-left: 4px solid #${lineColor};">
                                <div>
                                    <div style="font-weight: 600; color: var(--gray-900);">
                                        → ${dep.direction || dep.destination || 'Direction'}
                                    </div>
                                    <div style="font-size: 12px; color: var(--gray-500);">
                                        ${dep.mission ? `Mission: ${dep.mission} • ` : ''}${dep.expected_time ? new Date(dep.expected_time).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '--:--'}
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 1.5rem; font-weight: 800; color: #${lineColor};">
                                        ${dep.wait_minutes !== null ? (dep.wait_minutes <= 0 ? '<span style="color: #059669; animation: pulse 1s infinite;">🚆 À quai</span>' : dep.wait_minutes + ' min') : '--'}
                                    </div>
                                    ${dep.status === 'delayed' ? '<span style="font-size: 11px; color: #DC2626;">⚠️ Retard</span>' : ''}
                                </div>
                            </div>
                        `).join('')}
                    `;
                } else {
                    departuresList.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: var(--gray-500);">
                            <p>😔 Aucun train prévu pour le moment</p>
                            ${data.error ? `<p style="font-size: 12px; margin-top: 10px;">${data.error}</p>` : ''}
                            <p style="font-size: 12px; margin-top: 10px;">Consultez <a href="https://www.transilien.com" target="_blank">transilien.com</a> pour plus d'informations</p>
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
            const name = (line.shortname_line || 'A').toUpperCase();

            loadSchedules(line.id_line);

            let links = [
                {
                    icon: '🚆',
                    title: 'SNCF Transilien',
                    desc: 'Horaires et infos trafic RER ' + name,
                    url: `https://www.transilien.com/fr/page-ligne/ligne-${name.toLowerCase()}`,
                    color: '#0088CE'
                }
            ];

            // RATP pour RER A et B
            if (['A', 'B'].includes(name)) {
                links.unshift({
                    icon: '🚇',
                    title: 'RATP - RER ' + name,
                    desc: 'Horaires et trafic',
                    url: `https://www.ratp.fr/horaires?line=rer-${name.toLowerCase()}`,
                    color: '#003B82'
                });
            }

            links.push({
                icon: '🗺️',
                title: 'Île-de-France Mobilités',
                desc: 'Plans et informations réseau',
                url: 'https://www.iledefrance-mobilites.fr/plans',
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
                            <span class="info-label">Premier train (semaine)</span>
                            <span class="info-value">${schedules.weekday?.first_train || '05:00'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Dernier train (semaine)</span>
                            <span class="info-value">${schedules.weekday?.last_train || '00:30'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Premier train (dimanche)</span>
                            <span class="info-value">${schedules.sunday?.first_train || '06:00'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Service week-end</span>
                            <span class="info-value">Jusqu'à ${schedules.friday_saturday_night?.last_train || '01:00'}</span>
                        </div>
                    `;

                    document.getElementById('frequency-info').innerHTML = `
                        <div class="info-row">
                            <span class="info-label">Heures de pointe</span>
                            <span class="info-value" style="color: var(--primary); font-weight: 700;">⚡ ${schedules.weekday?.frequency_peak || '4-8 min'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Heures creuses</span>
                            <span class="info-value">${schedules.weekday?.frequency_offpeak || '10-15 min'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Soirée / Week-end</span>
                            <span class="info-value">${schedules.weekday?.frequency_night || '15-20 min'}</span>
                        </div>
                    `;

                    document.getElementById('peak-hours').innerHTML = `
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                            <div style="padding: 16px; background: linear-gradient(135deg, #${lineColor}20, #${lineColor}10); border-radius: 12px; text-align: center;">
                                <div style="font-size: 2rem; margin-bottom: 8px;">🌅</div>
                                <div style="font-weight: 600; color: #${lineColor};">Matin</div>
                                <div style="font-size: 1.25rem; font-weight: 700;">${data.peak_hours?.morning || '07:00 - 09:30'}</div>
                            </div>
                            <div style="padding: 16px; background: linear-gradient(135deg, #${lineColor}20, #${lineColor}10); border-radius: 12px; text-align: center;">
                                <div style="font-size: 2rem; margin-bottom: 8px;">🌆</div>
                                <div style="font-weight: 600; color: #${lineColor};">Soir</div>
                                <div style="font-size: 1.25rem; font-weight: 700;">${data.peak_hours?.evening || '17:00 - 20:00'}</div>
                            </div>
                        </div>
                    `;

                    // RER specific tips
                    document.getElementById('travel-tips').innerHTML = `
                        <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--gray-100);">
                            <span style="font-size: 1.2rem;">✓</span>
                            <span style="color: var(--gray-700);">Vérifiez la mission du train (toutes les gares ne sont pas desservies)</span>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--gray-100);">
                            <span style="font-size: 1.2rem;">✓</span>
                            <span style="color: var(--gray-700);">Attention aux travaux fréquents le week-end - consultez l'état du trafic</span>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--gray-100);">
                            <span style="font-size: 1.2rem;">✓</span>
                            <span style="color: var(--gray-700);">Évitez les heures de pointe si possible (7h-9h30 et 17h-20h)</span>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 0;">
                            <span style="font-size: 1.2rem;">✓</span>
                            <span style="color: var(--gray-700);">Téléchargez l'appli IDFM ou Transilien pour le suivi temps réel</span>
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

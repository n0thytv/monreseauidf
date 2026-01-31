<?php
/**
 * Mon Réseau IDF - Détail d'une ligne
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Récupérer l'ID de la ligne
$lineId = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($lineId)) {
    header('Location: ' . SITE_URL . '/lignes.php');
    exit;
}

$pageTitle = 'Détail de la ligne';
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

        .station-id {
            font-size: var(--font-size-xs);
            color: var(--gray-400);
            margin-left: var(--spacing-2);
        }

        /* Schedules */
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

        /* Styles pour les stations en grille */
        .station-card:hover {
            transform: translateX(4px);
        }

        #station-search:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(12, 62, 120, 0.1);
        }

        /* Animation pour les éléments de station cliquables */
        .station-item {
            transition: background 0.2s;
        }

        .station-item:hover {
            background: rgba(12, 62, 120, 0.05);
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Loading State -->
    <div id="loading-state" style="background: var(--gray-50); min-height: 60vh;">
        <div class="loading-state">
            <div class="loading-spinner"></div>
            <p>Chargement des informations...</p>
        </div>
    </div>

    <!-- Line Content -->
    <div id="line-page" style="display: none;">
        <!-- Hero -->
        <section class="line-hero" id="line-hero">
            <div class="container">
                <a href="<?php echo SITE_URL; ?>/lignes.php" class="back-link">← Retour aux lignes</a>
                <div class="line-hero-content">
                    <div class="line-hero-badge" id="hero-badge"></div>
                    <div class="line-hero-info">
                        <h1 id="hero-title">Chargement...</h1>
                        <div class="line-hero-meta" id="hero-meta"></div>
                        <div class="line-hero-actions" id="hero-actions"></div>
                    </div>
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
                    <!-- Sélecteur de trajet -->
                    <div id="route-selector-container" style="display: none; margin-bottom: var(--spacing-4);">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--gray-700);">
                            🚆 Choisir un trajet :
                        </label>
                        <select id="route-selector" onchange="selectRoute(this.value)"
                            style="width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); font-size: 1rem; background: white; cursor: pointer;">
                            <option value="all">📍 Voir tous les trajets</option>
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
                    <div id="stations-route-selector" style="display: none; margin-bottom: var(--spacing-4);">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--gray-700);">
                            🚆 Afficher les stations du trajet :
                        </label>
                        <select id="stations-route-select" onchange="filterStationsByRoute(this.value)"
                            style="width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); font-size: 1rem; background: white; cursor: pointer;">
                            <option value="all">📍 Toutes les stations</option>
                        </select>
                    </div>
                    <!-- Recherche de station -->
                    <div style="margin-bottom: var(--spacing-4);">
                        <input type="text" id="station-search" placeholder="🔍 Rechercher une station..."
                            style="width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); font-size: 1rem;"
                            oninput="filterStations(this.value)">
                    </div>
                    <div class="stations-list">
                        <div class="info-card-header">
                            <span class="info-card-icon">🚏</span>
                            <span class="info-card-title">Stations de la ligne</span>
                            <span id="stations-count" style="margin-left: auto; color: var(--gray-500);"></span>
                        </div>
                        <div id="stations-grid" style="display: none;"></div>
                        <div class="stations-timeline" id="stations-timeline">
                            <p style="color: var(--gray-500);">Chargement des stations...</p>
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

                        <!-- Sélecteur de trajet pour horaires -->
                        <div id="horaires-route-selector" style="display: none; margin-bottom: var(--spacing-4);">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--gray-700);">
                                🚆 Choisir un trajet :
                            </label>
                            <select id="horaires-route-select" onchange="filterStopsByRoute(this.value)"
                                style="width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); font-size: 1rem; background: white; cursor: pointer;">
                                <option value="all">📍 Tous les trajets</option>
                            </select>
                        </div>

                        <!-- Sélecteur de station -->
                        <div style="margin-bottom: var(--spacing-4);">
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--gray-700);">
                                📍 Choisir un arrêt :
                            </label>
                            <select id="stop-selector"
                                style="width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); font-size: 1rem; background: white; cursor: pointer;"
                                onchange="loadDepartures()">
                                <option value="">-- Sélectionner une station --</option>
                            </select>
                        </div>

                        <!-- Liste des prochains passages -->
                        <div id="departures-list">
                            <p style="color: var(--gray-500); text-align: center; padding: 20px;">
                                👆 Sélectionnez une station pour voir les prochains passages
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

                    <!-- Conseils -->
                    <div class="info-card" style="margin-bottom: var(--spacing-6);">
                        <div class="info-card-header">
                            <span class="info-card-icon">💡</span>
                            <span class="info-card-title">Conseils pratiques</span>
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
        let lineColor = '0C3E78';
        let map = null;

        const modeIcons = {
            'metro': '🚇', 'rer': '🚆', 'tram': '🚋', 'tramway': '🚋',
            'bus': '🚌', 'rail': '🚂', 'train': '🚂', 'funicular': '🚠', 'cable': '🚡'
        };

        // Tab switching
        function showTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            document.querySelector(`[onclick="showTab('${tabId}')"]`).classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');

            // Load map when tab is shown
            if (tabId === 'plan' && !map) {
                loadLineMap();
            }
            if (tabId === 'stations') {
                loadStations();
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
                            <p>❌ Ligne non trouvée</p>
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
            const name = line.shortname_line || line.name_line || 'Ligne';
            const fullName = line.name_line || 'Ligne ' + name;
            const mode = (line.transportmode || 'bus').toLowerCase();
            lineColor = line.colourweb_hexa || '0C3E78';
            const textColor = line.textcolourweb_hexa || 'FFFFFF';
            const icon = modeIcons[mode] || '🚌';

            document.title = fullName + ' - Mon Réseau IDF';

            // Hero
            document.getElementById('line-hero').style.background = `linear-gradient(135deg, #${lineColor} 0%, #${darkenColor(lineColor, 20)} 100%)`;
            document.getElementById('hero-badge').innerHTML = name.length > 3 ? name.substring(0, 3) : name;
            document.getElementById('hero-badge').style.color = '#' + lineColor;
            document.getElementById('hero-title').textContent = fullName;

            document.getElementById('hero-meta').innerHTML = `
                <span>${icon} ${capitalizeFirst(mode)}</span>
                <span>🏢 ${line.operatorname || 'IDFM'}</span>
                <span>📍 ${line.networkname || 'Île-de-France'}</span>
            `;

            // Action buttons
            const scheduleUrl = getScheduleUrl(line);
            document.getElementById('hero-actions').innerHTML = `
                <a href="#" class="hero-btn" onclick="showTab('plan'); return false;">🗺️ Voir le plan</a>
                <a href="#" class="hero-btn" onclick="showTab('horaires'); return false;">🕐 Horaires</a>
                ${scheduleUrl ? `<a href="${scheduleUrl}" target="_blank" class="hero-btn">📄 Fiche officielle</a>` : ''}
            `;

            // Info sections
            displayInfoSections(line, lineColor, textColor, name, icon);

            // Schedule links
            displayScheduleLinks(line);

            // Show page
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('line-page').style.display = 'block';
        }

        function displayInfoSections(line, color, textColor, name, icon) {
            document.getElementById('general-info').innerHTML = `
                <div class="info-row"><span class="info-label">Identifiant</span><span class="info-value">${line.id_line || '-'}</span></div>
                <div class="info-row"><span class="info-label">Nom court</span><span class="info-value">${line.shortname_line || '-'}</span></div>
                <div class="info-row"><span class="info-label">Mode de transport</span><span class="info-value">${icon} ${capitalizeFirst(line.transportmode || '-')}</span></div>
                <div class="info-row"><span class="info-label">Sous-mode</span><span class="info-value">${line.transportsubmode || '-'}</span></div>
                <div class="info-row"><span class="info-label">Statut</span><span class="info-value"><span class="status-badge ${line.status === 'active' ? 'status-active' : 'status-inactive'}">${line.status === 'active' ? 'Active' : 'Inactive'}</span></span></div>
            `;

            document.getElementById('operator-info').innerHTML = `
                <div class="info-row"><span class="info-label">Exploitant</span><span class="info-value">${line.operatorname || '-'}</span></div>
                <div class="info-row"><span class="info-label">Code exploitant</span><span class="info-value">${line.operatorref || '-'}</span></div>
                <div class="info-row"><span class="info-label">Réseau</span><span class="info-value">${line.networkname || '-'}</span></div>
                ${line.shortname_groupoflines ? `<div class="info-row"><span class="info-label">Parcours</span><span class="info-value" style="max-width: 200px; text-align: right; font-size: 12px;">${line.shortname_groupoflines}</span></div>` : ''}
            `;

            const hasAudio = line.audiblesigns_available === 'true';
            const hasVisual = line.visualsigns_available === 'true';
            const hasAC = line.air_conditioning && line.air_conditioning !== 'false';
            document.getElementById('accessibility-info').innerHTML = `
                <div class="info-row"><span class="info-label">Accessibilité PMR</span><span class="info-value">${line.accessibility === 'true' ? '♿ Oui' : (line.accessibility === 'unknown' ? '❓ Inconnu' : '❌ Non')}</span></div>
                <div class="info-row"><span class="info-label">Annonces sonores</span><span class="info-value">${hasAudio ? '🔊 Oui' : '❌ Non'}</span></div>
                <div class="info-row"><span class="info-label">Affichage visuel</span><span class="info-value">${hasVisual ? '📺 Oui' : '❌ Non'}</span></div>
                <div class="info-row"><span class="info-label">Climatisation</span><span class="info-value">${hasAC ? '❄️ ' + (line.air_conditioning === 'partial' ? 'Partielle' : 'Oui') : '❌ Non'}</span></div>
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

        // Données globales pour les routes
        let lineRoutes = [];
        let currentRouteId = 'all';
        let mapLayers = {};
        let isMetroLine = false;
        let isMultiRouteLine = false;

        // Load map - Gère les lignes simples (métro) et multi-trajets (RER, Transilien)
        async function loadLineMap() {
            try {
                console.log('Loading map for line:', lineId);
                const response = await fetch('<?php echo SITE_URL; ?>/api/trace-ligne.php?id=' + lineId);
                const data = await response.json();
                console.log('Map data:', data);

                // Stocker les infos
                lineRoutes = data.routes || [];
                isMetroLine = data.is_metro || false;
                isMultiRouteLine = data.is_multi_route || false;

                document.getElementById('map-loading').style.display = 'none';
                document.getElementById('line-map').style.display = 'block';

                // Initialize map
                map = L.map('line-map').setView([48.8566, 2.3522], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                // Pour les métros : pas de sélecteur, affichage direct
                // Pour les lignes multi-trajets (RER, Transilien) : sélecteur
                if (!isMetroLine && isMultiRouteLine && lineRoutes.length > 1) {
                    const selector = document.getElementById('route-selector');
                    selector.innerHTML = '<option value="all">📍 Voir tous les trajets (' + lineRoutes.length + ')</option>';
                    lineRoutes.forEach((route, i) => {
                        const name = route.name || `Trajet ${i + 1}`;
                        selector.innerHTML += `<option value="${route.id}">${name}</option>`;
                    });
                    document.getElementById('route-selector-container').style.display = 'block';
                    // Afficher le premier trajet par défaut
                    selectRoute(lineRoutes[0].id);
                } else {
                    // Ligne simple (métro, bus simple) : tout afficher
                    document.getElementById('route-selector-container').style.display = 'none';
                    selectRoute('all');
                }

            } catch (error) {
                console.error('Map error:', error);
                document.getElementById('map-loading').innerHTML = '<p style="text-align: center; padding: 40px; color: var(--gray-500);">❌ Carte non disponible</p>';
            }
        }

        // Sélectionner et afficher un trajet spécifique
        function selectRoute(routeId) {
            currentRouteId = routeId;

            // Effacer les couches existantes
            Object.values(mapLayers).forEach(layer => map.removeLayer(layer));
            mapLayers = {};

            const bounds = [];
            const routesToShow = routeId === 'all' ? lineRoutes : lineRoutes.filter(r => r.id == routeId);

            routesToShow.forEach((route, index) => {
                // Dessiner le tracé
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

                // Ajouter les marqueurs de stations
                const stops = route.stops || [];
                stops.forEach((stop, i) => {
                    const lat = parseFloat(stop.stop_lat);
                    const lon = parseFloat(stop.stop_lon);

                    if (lat && lon && !isNaN(lat) && !isNaN(lon)) {
                        bounds.push([lat, lon]);

                        const isTerminus = i === 0 || i === stops.length - 1;
                        const marker = L.circleMarker([lat, lon], {
                            radius: isTerminus ? 9 : 6,
                            fillColor: isTerminus ? '#' + lineColor : '#ffffff',
                            color: '#' + lineColor,
                            weight: isTerminus ? 3 : 2,
                            fillOpacity: 1
                        });

                        marker.bindPopup(`
                            <div style="text-align: center; min-width: 120px;">
                                <strong>${stop.stop_name || 'Station'}</strong>
                                ${stop.commune ? `<br><small style="color: #666;">${stop.commune}</small>` : ''}
                            </div>
                        `);
                        marker.addTo(map);
                        mapLayers['stop_' + route.id + '_' + i] = marker;
                    }
                });
            });

            // Ajuster la vue
            if (bounds.length > 0) {
                try {
                    map.fitBounds(bounds, { padding: [40, 40] });
                } catch (e) {}
            }

            setTimeout(() => map.invalidateSize(), 100);
        }

        // Stocker les stations pour la recherche
        let allStations = [];
        let currentStationsRouteId = 'all';

        // Load stations - Affichage simple pour métro, sélecteur pour lignes multi-trajets
        async function loadStations() {
            const timeline = document.getElementById('stations-timeline');
            const stationsGrid = document.getElementById('stations-grid');
            if (timeline.dataset.loaded) return;

            try {
                const response = await fetch('<?php echo SITE_URL; ?>/api/trace-ligne.php?id=' + lineId);
                const data = await response.json();

                // Stocker les infos si pas déjà fait
                if (!lineRoutes.length) {
                    lineRoutes = data.routes || [];
                    isMetroLine = data.is_metro || false;
                    isMultiRouteLine = data.is_multi_route || false;
                }

                // Pour les métros et lignes simples : affichage direct sans sélecteur
                // Pour les lignes multi-trajets (RER, Transilien) : sélecteur
                if (!isMetroLine && isMultiRouteLine && lineRoutes.length > 1) {
                    const selector = document.getElementById('stations-route-select');
                    selector.innerHTML = '<option value="all">📍 Toutes les stations</option>';
                    lineRoutes.forEach((route, i) => {
                        const name = route.name || `Trajet ${i + 1}`;
                        const count = route.stops_count || 0;
                        selector.innerHTML += `<option value="${route.id}">${name} (${count} arrêts)</option>`;
                    });
                    document.getElementById('stations-route-selector').style.display = 'block';

                    // Afficher les stations du premier trajet par défaut
                    filterStationsByRoute(lineRoutes[0].id);
                } else if (data.stops && data.stops.length > 0) {
                    // Ligne simple (métro, bus simple) : afficher toutes les stations
                    document.getElementById('stations-route-selector').style.display = 'none';
                    displayStations(data.stops);
                } else {
                    timeline.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">Aucune station disponible</p>';
                }

                timeline.dataset.loaded = 'true';
            } catch (error) {
                timeline.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">Erreur de chargement</p>';
            }
        }

        // Filtrer les stations par trajet
        function filterStationsByRoute(routeId) {
            currentStationsRouteId = routeId;
            let stopsToShow = [];

            if (routeId === 'all') {
                // Toutes les stations uniques
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

        // Afficher les stations
        function displayStations(stops) {
            const timeline = document.getElementById('stations-timeline');
            const stationsGrid = document.getElementById('stations-grid');

            if (!stops || stops.length === 0) {
                timeline.style.display = 'block';
                stationsGrid.style.display = 'none';
                timeline.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">Aucune station sur ce trajet</p>';
                document.getElementById('stations-count').textContent = '0 stations';
                return;
            }

            document.getElementById('stations-count').textContent = stops.length + ' stations';
            allStations = stops;

            // Affichage en timeline pour une vue claire du parcours
            timeline.style.display = 'block';
            stationsGrid.style.display = 'none';

            timeline.innerHTML = stops.map((stop, index) => {
                const isTerminus = index === 0 || index === stops.length - 1;
                return `
                    <div class="station-item" data-name="${(stop.stop_name || '').toLowerCase()}"
                        style="cursor: pointer; ${isTerminus ? 'font-weight: 700;' : ''}"
                        onclick="showStationOnMap('${stop.stop_name}', ${stop.stop_lat}, ${stop.stop_lon})">
                        <span class="station-name">${isTerminus ? '🔴 ' : ''}${stop.stop_name || 'Station'}</span>
                        ${stop.commune ? `<span style="font-size: 0.75rem; color: var(--gray-400); margin-left: 8px;">${stop.commune}</span>` : ''}
                    </div>
                `;
            }).join('');

            // Appliquer les styles de couleur
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

        // Filtrer les stations par recherche
        function filterStations(query) {
            const searchTerm = query.toLowerCase().trim();
            const items = document.querySelectorAll('.station-item');

            items.forEach(item => {
                const name = item.dataset.name || '';
                item.style.display = (searchTerm === '' || name.includes(searchTerm)) ? '' : 'none';
            });
        }

        // Afficher une station sur la carte
        function showStationOnMap(name, lat, lon) {
            if (map && lat && lon) {
                showTab('plan');
                setTimeout(() => {
                    map.setView([lat, lon], 15);
                    L.popup()
                        .setLatLng([lat, lon])
                        .setContent(`<strong>${name}</strong>`)
                        .openOn(map);
                }, 100);
            }
        }

        // Schedule links
        function displayScheduleLinks(line) {
            const mode = (line.transportmode || '').toLowerCase();
            const name = line.shortname_line || '';
            const operator = (line.operatorname || '').toLowerCase();

            // Load schedule data
            loadSchedules(line.id_line);

            let links = [];

            // RATP
            if (operator.includes('ratp')) {
                if (mode === 'metro') {
                    links.push({
                        icon: '🚇',
                        title: 'RATP - Métro ' + name,
                        desc: 'Horaires et plans officiels',
                        url: `https://www.ratp.fr/horaires?line=${encodeURIComponent('metro-' + name)}`,
                        color: '#003B82'
                    });
                } else if (mode === 'rer' && ['a', 'b'].includes(name.toLowerCase())) {
                    links.push({
                        icon: '🚆',
                        title: 'RATP - RER ' + name,
                        desc: 'Horaires et informations',
                        url: `https://www.ratp.fr/horaires?line=${encodeURIComponent('rer-' + name.toLowerCase())}`,
                        color: '#003B82'
                    });
                } else if (mode === 'bus') {
                    links.push({
                        icon: '🚌',
                        title: 'RATP - Bus ' + name,
                        desc: 'Horaires et plan de ligne',
                        url: `https://www.ratp.fr/horaires?line=${encodeURIComponent('bus-' + name)}`,
                        color: '#003B82'
                    });
                } else if (mode.includes('tram')) {
                    links.push({
                        icon: '🚋',
                        title: 'RATP - Tramway ' + name,
                        desc: 'Horaires et informations',
                        url: `https://www.ratp.fr/horaires?line=${encodeURIComponent('tram-' + name)}`,
                        color: '#003B82'
                    });
                }
            }

            // SNCF / Transilien
            if (operator.includes('sncf') || mode === 'rail' || (mode === 'rer' && !['a', 'b'].includes(name.toLowerCase()))) {
                links.push({
                    icon: '🚆',
                    title: 'SNCF Transilien',
                    desc: 'Horaires temps réel',
                    url: 'https://www.transilien.com/',
                    color: '#0088CE'
                });
            }

            // IDFM general
            links.push({
                icon: '🗺️',
                title: 'Île-de-France Mobilités',
                desc: 'Plans et informations réseau',
                url: 'https://www.iledefrance-mobilites.fr/plans',
                color: '#00814F'
            });

            // Vianavigo
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

        // Load schedule data - UNIQUEMENT données temps réel
        async function loadSchedules(lineId) {
            try {
                const response = await fetch('<?php echo SITE_URL; ?>/api/horaires.php?line=' + lineId);
                const data = await response.json();

                // Afficher les prochains passages réels
                if (data.realtime_available && data.next_departures?.length > 0) {
                    const departures = data.next_departures;
                    const stopName = data.departure_stop || 'Terminus';

                    document.getElementById('service-hours').innerHTML = `
                        <div style="background: #D1FAE5; border: 1px solid #10B981; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; gap: 8px; color: #065F46;">
                                <span>⚡</span>
                                <span style="font-weight: 600;">Données temps réel IDFM</span>
                            </div>
                            <p style="font-size: 13px; color: #065F46; margin: 4px 0 0 0;">
                                Départs depuis <strong>${stopName}</strong>
                            </p>
                        </div>
                        ${departures.map(dep => `
                            <div class="info-row">
                                <span class="info-label">${dep.destination || 'Direction inconnue'}</span>
                                <span class="info-value" style="font-weight: 700; color: var(--primary);">
                                    ${dep.time} <span style="font-size: 12px; color: var(--gray-500);">(${dep.wait_minutes} min)</span>
                                </span>
                            </div>
                        `).join('')}
                        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--gray-100);">
                            <a href="${data.official_idfm_url || data.official_url || '#'}" target="_blank"
                               style="color: var(--primary); font-size: 13px; text-decoration: none;">
                                Voir tous les horaires sur IDFM →
                            </a>
                        </div>
                    `;

                    // Cacher les sections inutiles
                    document.getElementById('frequency-info').innerHTML = '';
                    document.getElementById('peak-hours').innerHTML = '';
                    document.getElementById('travel-tips').innerHTML = '';

                } else {
                    // Pas de données temps réel disponibles
                    document.getElementById('service-hours').innerHTML = `
                        <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 8px; padding: 12px;">
                            <div style="display: flex; align-items: center; gap: 8px; color: #92400E;">
                                <span>⚠️</span>
                                <span style="font-weight: 600;">Aucun passage prévu</span>
                            </div>
                            <p style="font-size: 13px; color: #92400E; margin: 8px 0 0 0;">
                                ${data.message || 'Aucun départ prévu actuellement pour cette ligne.'}
                            </p>
                            <p style="margin-top: 12px;">
                                <a href="${data.official_idfm_url || data.official_url || 'https://www.iledefrance-mobilites.fr/fiches-horaires'}"
                                   target="_blank" style="color: #1D4ED8; font-weight: 600;">
                                    Consulter les fiches horaires IDFM →
                                </a>
                            </p>
                        </div>
                    `;

                    // Cacher les sections inutiles
                    document.getElementById('frequency-info').innerHTML = '';
                    document.getElementById('peak-hours').innerHTML = '';
                    document.getElementById('travel-tips').innerHTML = '';
                }

            } catch (error) {
                console.error('Schedule error:', error);
                document.getElementById('service-hours').innerHTML = `
                    <div style="background: #FEE2E2; border: 1px solid #EF4444; border-radius: 8px; padding: 12px;">
                        <p style="color: #991B1B; margin: 0;">Impossible de charger les horaires. Réessayez plus tard.</p>
                        <p style="margin-top: 8px;">
                            <a href="https://www.iledefrance-mobilites.fr/fiches-horaires" target="_blank"
                               style="color: #1D4ED8; font-weight: 600;">Consulter les horaires sur IDFM →</a>
                        </p>
                    </div>
                `;
                document.getElementById('frequency-info').innerHTML = '';
                document.getElementById('peak-hours').innerHTML = '';
                document.getElementById('travel-tips').innerHTML = '';
            }
        }

        // Stocker les données pour les horaires
        let horairesRoutes = [];
        let currentHorairesRouteId = 'all';
        let isHorairesMetro = false;
        let isHorairesMultiRoute = false;

        // Populate stop selector with line stations
        async function populateStopSelector() {
            const selector = document.getElementById('stop-selector');
            if (!selector) return;

            try {
                const response = await fetch('<?php echo SITE_URL; ?>/api/trace-ligne.php?id=' + lineId);
                const data = await response.json();

                // Stocker les infos
                horairesRoutes = data.routes || [];
                isHorairesMetro = data.is_metro || false;
                isHorairesMultiRoute = data.is_multi_route || false;

                // Pour les métros et lignes simples : pas de sélecteur de trajet
                // Pour les lignes multi-trajets (RER, Transilien) : sélecteur
                if (!isHorairesMetro && isHorairesMultiRoute && horairesRoutes.length > 1) {
                    const routeSelector = document.getElementById('horaires-route-select');
                    routeSelector.innerHTML = '<option value="all">📍 Tous les trajets</option>';
                    horairesRoutes.forEach((route, i) => {
                        const name = route.name || `Trajet ${i + 1}`;
                        const count = route.stops_count || (route.stops ? route.stops.length : 0);
                        routeSelector.innerHTML += `<option value="${route.id}">${name} (${count} arrêts)</option>`;
                    });
                    document.getElementById('horaires-route-selector').style.display = 'block';

                    // Afficher les arrêts du premier trajet par défaut
                    filterStopsByRoute(horairesRoutes[0].id);
                } else if (data.stops && data.stops.length > 0) {
                    // Ligne simple (métro, bus simple) - afficher tous les arrêts
                    document.getElementById('horaires-route-selector').style.display = 'none';
                    populateStopSelectorWithStops(data.stops);
                } else {
                    selector.innerHTML = '<option value="">Aucune station disponible</option>';
                    document.getElementById('realtime-status').innerHTML = '⚠️ Pas de stations';
                    document.getElementById('realtime-status').style.background = '#FEF3C7';
                }
            } catch (error) {
                console.error('Stop selector error:', error);
                selector.innerHTML = '<option value="">Erreur de chargement</option>';
            }
        }

        // Filtrer les arrêts par trajet sélectionné
        function filterStopsByRoute(routeId) {
            currentHorairesRouteId = routeId;
            let stopsToShow = [];

            if (routeId === 'all') {
                // Toutes les stations uniques de tous les trajets
                const allStops = horairesRoutes.flatMap(r => r.stops || []);
                const seen = new Set();
                stopsToShow = allStops.filter(s => {
                    if (seen.has(s.stop_name)) return false;
                    seen.add(s.stop_name);
                    return true;
                });
            } else {
                // Stations du trajet sélectionné uniquement
                const route = horairesRoutes.find(r => r.id == routeId);
                stopsToShow = route ? (route.stops || []) : [];
            }

            populateStopSelectorWithStops(stopsToShow);
        }

        // Remplir le sélecteur d'arrêts avec une liste de stations
        function populateStopSelectorWithStops(stops) {
            const selector = document.getElementById('stop-selector');

            if (stops && stops.length > 0) {
                // Remove duplicates by name
                const uniqueStops = [...new Map(stops.map(s => [s.stop_name, s])).values()];

                selector.innerHTML = '<option value="">-- Sélectionner une station --</option>' +
                    uniqueStops.map((stop, index) =>
                        `<option value="${index}">${stop.stop_name}</option>`
                    ).join('');

                // Store stops for later use
                window.lineStops = uniqueStops;

                document.getElementById('realtime-status').innerHTML = '✅ Prêt';
                document.getElementById('realtime-status').style.background = '#DEF7EC';
            } else {
                selector.innerHTML = '<option value="">Aucune station sur ce trajet</option>';
                window.lineStops = [];
                document.getElementById('realtime-status').innerHTML = '⚠️ Pas de stations';
                document.getElementById('realtime-status').style.background = '#FEF3C7';
            }

            // Reset departures list
            document.getElementById('departures-list').innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">👆 Sélectionnez une station pour voir les prochains passages</p>';
            document.getElementById('refresh-btn').style.display = 'none';
        }

        // Load departures for selected stop
        async function loadDepartures() {
            const selector = document.getElementById('stop-selector');
            const stopIndex = selector.value;
            const departuresList = document.getElementById('departures-list');
            const refreshBtn = document.getElementById('refresh-btn');

            if (!stopIndex || !window.lineStops) {
                departuresList.innerHTML = '<p style="color: var(--gray-500); text-align: center; padding: 20px;">👆 Sélectionnez une station</p>';
                refreshBtn.style.display = 'none';
                return;
            }

            const stop = window.lineStops[parseInt(stopIndex)];
            const stopName = stop.stop_name;

            departuresList.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <div class="loading-spinner"></div>
                    <p style="color: var(--gray-500); margin-top: 10px;">Chargement des prochains passages pour ${stopName}...</p>
                </div>
            `;

            document.getElementById('realtime-status').innerHTML = '⏳ Chargement...';
            document.getElementById('realtime-status').style.background = '#E0E7FF';

            try {
                // Choisir l'API appropriée selon le mode de transport
                // Pour les bus/trams, utiliser l'API bus avec le lineId pour filtrer
                // Pour les métros, utiliser l'API métro
                const mode = lineData ? (lineData.transportmode || '').toLowerCase() : 'bus';
                const isMetro = mode === 'metro';

                let apiUrl;
                if (isMetro) {
                    apiUrl = `<?php echo SITE_URL; ?>/api/prochains-passages.php?name=${encodeURIComponent(stopName)}`;
                } else {
                    // Pour bus, tram, RER, rail: utiliser l'API bus avec le lineId
                    apiUrl = `<?php echo SITE_URL; ?>/api/prochains-passages-bus.php?name=${encodeURIComponent(stopName)}&line=${encodeURIComponent(lineId)}`;
                }

                const response = await fetch(apiUrl);
                const data = await response.json();
                
                console.log('API Response:', data);

                refreshBtn.style.display = 'block';

                if (data.success && data.departures && data.departures.length > 0) {
                    document.getElementById('realtime-status').innerHTML = '🔴 Temps réel';
                    document.getElementById('realtime-status').style.background = '#DEF7EC';

                    departuresList.innerHTML = `
                        <div style="font-weight: 600; margin-bottom: 12px; color: var(--gray-700);">
                            🚉 Prochains départs depuis <strong>${stopName}</strong> :
                        </div>
                        ${data.departures.slice(0, 6).map(dep => `
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; margin-bottom: 8px; background: linear-gradient(135deg, #${lineColor}10, #${lineColor}05); border-radius: 10px; border-left: 4px solid #${lineColor};">
                                <div>
                                    <div style="font-weight: 600; color: var(--gray-900);">
                                        → ${dep.direction || dep.destination || 'Direction'}
                                    </div>
                                    <div style="font-size: 12px; color: var(--gray-500);">
                                        ${dep.expected_time ? new Date(dep.expected_time).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '--:--'}
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 1.5rem; font-weight: 800; color: #${lineColor};">
                                        ${dep.wait_minutes !== null ? (dep.wait_minutes <= 0 ? '<span style="color: #059669; animation: pulse 1s infinite;">🚇 À quai</span>' : dep.wait_minutes + ' min') : '--'}
                                    </div>
                                    ${dep.status === 'delayed' ? '<span style="font-size: 11px; color: #DC2626;">⚠️ Retard</span>' : ''}
                                </div>
                            </div>
                        `).join('')}
                    `;
                } else {
                    departuresList.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: var(--gray-500);">
                            <p>😔 Aucun passage prévu pour le moment</p>
                            ${data.error ? `<p style="font-size: 12px; margin-top: 10px;">${data.error}</p>` : ''}
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

        // Call populateStopSelector when horaires tab is shown
        const originalShowTab = showTab;
        showTab = function (tabId) {
            originalShowTab(tabId);
            if (tabId === 'horaires' && !document.getElementById('stop-selector').dataset.loaded) {
                populateStopSelector();
                document.getElementById('stop-selector').dataset.loaded = 'true';
            }
        };

        function getScheduleUrl(line) {
            const mode = (line.transportmode || '').toLowerCase();
            const name = line.shortname_line || '';
            if (mode === 'metro') return `https://www.ratp.fr/horaires?line=metro-${name}`;
            if (mode === 'rer') return `https://www.ratp.fr/horaires?line=rer-${name.toLowerCase()}`;
            return null;
        }

        function capitalizeFirst(str) {
            return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
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
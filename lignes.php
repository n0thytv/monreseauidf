<?php
/**
 * Mon Réseau IDF - Lignes de transport
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Lignes de transport';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Mon Réseau IDF</title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
    <style>
        .lines-page {
            padding: var(--spacing-8) 0;
            background: var(--gray-50);
            min-height: 80vh;
        }

        /* Mode Section */
        .mode-section {
            background: white;
            border-radius: var(--radius-xl);
            margin-bottom: var(--spacing-6);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .mode-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-4);
            padding: var(--spacing-5);
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid var(--gray-100);
        }

        .mode-header:hover {
            background: var(--gray-50);
        }

        .mode-icon {
            font-size: 2.5rem;
        }

        .mode-title {
            flex: 1;
        }

        .mode-title h2 {
            margin: 0;
            font-size: var(--font-size-xl);
            color: var(--gray-900);
        }

        .mode-title span {
            font-size: var(--font-size-sm);
            color: var(--gray-500);
        }

        .mode-toggle {
            font-size: 1.5rem;
            color: var(--gray-400);
            transition: transform 0.3s;
        }

        .mode-section.open .mode-toggle {
            transform: rotate(180deg);
        }

        /* Lines Container */
        .mode-lines {
            display: none;
            padding: var(--spacing-5);
            background: var(--gray-50);
        }

        .mode-section.open .mode-lines {
            display: block;
        }

        /* Line Badges - Style plan de métro */
        .lines-row {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-3);
            align-items: center;
        }

        .line-badge-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 50px;
            height: 50px;
            padding: 0 var(--spacing-3);
            border-radius: var(--radius-lg);
            font-weight: 800;
            font-size: var(--font-size-lg);
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            position: relative;
        }

        .line-badge-link:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
            z-index: 10;
        }

        .line-badge-link:hover::after {
            content: attr(data-name);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--gray-900);
            color: white;
            padding: 4px 10px;
            border-radius: var(--radius);
            font-size: var(--font-size-xs);
            font-weight: 500;
            white-space: nowrap;
            margin-bottom: 8px;
            z-index: 100;
        }

        /* Separator for line groups */
        .line-separator {
            width: 1px;
            height: 30px;
            background: var(--gray-300);
            margin: 0 var(--spacing-2);
        }

        /* Loading */
        .loading-message {
            text-align: center;
            padding: var(--spacing-10);
            color: var(--gray-500);
        }

        .loading-message .spinner {
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-bottom: var(--spacing-3);
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Quick nav */
        .quick-nav {
            display: flex;
            gap: var(--spacing-2);
            flex-wrap: wrap;
            margin-bottom: var(--spacing-6);
        }

        .quick-nav-btn {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            padding: var(--spacing-2) var(--spacing-4);
            background: white;
            border: none;
            border-radius: var(--radius-full);
            box-shadow: var(--shadow);
            cursor: pointer;
            font-weight: 600;
            font-size: var(--font-size-sm);
            transition: all 0.2s;
        }

        .quick-nav-btn:hover {
            background: var(--primary);
            color: white;
        }

        .quick-nav-btn .icon {
            font-size: 1.2em;
        }

        /* Bus Search Section */
        .bus-search-container {
            margin-bottom: var(--spacing-4);
        }

        .bus-search-input {
            width: 100%;
            padding: var(--spacing-3) var(--spacing-4);
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-base);
            transition: border-color 0.2s;
        }

        .bus-search-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .bus-operators {
            display: flex;
            gap: var(--spacing-2);
            flex-wrap: wrap;
            margin-top: var(--spacing-3);
            margin-bottom: var(--spacing-4);
        }

        .operator-btn {
            padding: var(--spacing-1) var(--spacing-3);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-full);
            background: white;
            cursor: pointer;
            font-size: var(--font-size-xs);
            font-weight: 600;
            transition: all 0.2s;
        }

        .operator-btn:hover,
        .operator-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .bus-results-info {
            font-size: var(--font-size-sm);
            color: var(--gray-500);
            margin-bottom: var(--spacing-3);
        }

        .bus-results-info strong {
            color: var(--primary);
        }

        .load-more-btn {
            display: block;
            width: 100%;
            padding: var(--spacing-3);
            margin-top: var(--spacing-4);
            background: var(--gray-100);
            border: none;
            border-radius: var(--radius-lg);
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }

        .load-more-btn:hover {
            background: var(--gray-200);
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Hero -->
    <section class="hero" style="padding: var(--spacing-10) 0;">
        <div class="container">
            <div class="hero-content">
                <h1>🚇 Lignes de transport</h1>
                <p>Cliquez sur une ligne pour voir ses informations détaillées</p>
            </div>
        </div>
    </section>

    <!-- Lines Page -->
    <section class="lines-page">
        <div class="container">
            <!-- Quick Nav -->
            <div class="quick-nav" id="quick-nav">
                <button class="quick-nav-btn" onclick="scrollToMode('metro')"><span class="icon">🚇</span>
                    Métro</button>
                <button class="quick-nav-btn" onclick="scrollToMode('rer')"><span class="icon">🚆</span> RER</button>
                <button class="quick-nav-btn" onclick="scrollToMode('tram')"><span class="icon">🚋</span>
                    Tramway</button>
                <button class="quick-nav-btn" onclick="scrollToMode('rail')"><span class="icon">🚂</span>
                    Transilien</button>
                <button class="quick-nav-btn" onclick="scrollToMode('other')"><span class="icon">🚡</span>
                    Autres</button>
                <button class="quick-nav-btn" onclick="scrollToMode('bus')"><span class="icon">🚌</span> Bus</button>
            </div>

            <!-- Loading -->
            <div class="loading-message" id="loading">
                <div class="spinner"></div>
                <p>Chargement des lignes...</p>
            </div>

            <!-- Lines Container -->
            <div id="lines-container"></div>
        </div>
    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
        // Mode configuration
        const modes = [
            { id: 'metro', icon: '🚇', title: 'Métro', desc: 'Lignes de métro parisien' },
            { id: 'rer', icon: '🚆', title: 'RER', desc: 'Réseau Express Régional' },
            { id: 'tram', icon: '🚋', title: 'Tramway', desc: 'Lignes de tramway' },
            { id: 'rail', icon: '🚂', title: 'Transilien', desc: 'Lignes de train de banlieue' },
            { id: 'other', icon: '🚡', title: 'Câble & Funiculaire', desc: 'Autres modes de transport' },
            { id: 'bus', icon: '🚌', title: 'Bus', desc: 'Lignes de bus' }
        ];

        // Load lines
        async function loadLines() {
            const container = document.getElementById('lines-container');
            const loading = document.getElementById('loading');

            try {
                const response = await fetch('<?php echo SITE_URL; ?>/api/lignes.php');
                const data = await response.json();
                const lines = data.records || [];

                // Group lines by mode
                const grouped = {
                    metro: [],
                    rer: [],
                    tram: [],
                    rail: [],
                    other: [],
                    bus: []
                };

                lines.forEach(line => {
                    const mode = (line.transportmode || '').toLowerCase();
                    const network = (line.networkname || '').toLowerCase();
                    const shortname = (line.shortname_line || '').toUpperCase();

                    // Détecter les RER : ils sont classés "rail" avec networkname "RER"
                    const isRER = (mode === 'rer') ||
                                  (mode === 'rail' && network === 'rer') ||
                                  (mode === 'rail' && ['A', 'B', 'C', 'D', 'E'].includes(shortname) && network.includes('rer'));

                    if (mode === 'metro') grouped.metro.push(line);
                    else if (isRER) grouped.rer.push(line);
                    else if (mode.includes('tram')) grouped.tram.push(line);
                    else if (mode === 'rail' || mode === 'train') grouped.rail.push(line);
                    else if (mode === 'cable' || mode === 'funicular') grouped.other.push(line);
                    else if (mode === 'bus') grouped.bus.push(line);
                });

                // Sort each group
                Object.keys(grouped).forEach(key => {
                    grouped[key].sort((a, b) => {
                        const nameA = a.shortname_line || a.name_line || '';
                        const nameB = b.shortname_line || b.name_line || '';
                        return nameA.localeCompare(nameB, 'fr', { numeric: true });
                    });
                });

                // Build sections
                let html = '';

                // Store bus lines globally for search
                window.busLines = grouped.bus;

                modes.forEach(mode => {
                    const modeLines = grouped[mode.id] || [];
                    if (modeLines.length === 0 && mode.id !== 'bus') return;

                    const isOpen = mode.id !== 'bus';

                    if (mode.id === 'bus') {
                        // Special bus section with search
                        html += `
                            <div class="mode-section" id="mode-bus">
                                <div class="mode-header" onclick="toggleMode('bus')">
                                    <span class="mode-icon">🚌</span>
                                    <div class="mode-title">
                                        <h2>Bus</h2>
                                        <span>${modeLines.length} lignes</span>
                                    </div>
                                    <span class="mode-toggle">▼</span>
                                </div>
                                <div class="mode-lines">
                                    <div class="bus-search-container">
                                        <input type="text" class="bus-search-input" 
                                               placeholder="🔍 Rechercher une ligne de bus (ex: 38, 183, N01...)" 
                                               oninput="searchBus(this.value)">
                                        <div class="bus-operators" id="bus-operators"></div>
                                    </div>
                                    <div class="bus-results-info" id="bus-results-info">Tapez un numéro pour rechercher</div>
                                    <div class="lines-row" id="bus-lines-container"></div>
                                </div>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="mode-section ${isOpen ? 'open' : ''}" id="mode-${mode.id}">
                                <div class="mode-header" onclick="toggleMode('${mode.id}')">
                                    <span class="mode-icon">${mode.icon}</span>
                                    <div class="mode-title">
                                        <h2>${mode.title}</h2>
                                        <span>${modeLines.length} lignes</span>
                                    </div>
                                    <span class="mode-toggle">▼</span>
                                </div>
                                <div class="mode-lines">
                                    <div class="lines-row">
                                        ${renderLineBadges(modeLines)}
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                });

                loading.style.display = 'none';
                container.innerHTML = html;

                // Build operator filter buttons
                buildOperatorFilters(grouped.bus);

            } catch (error) {
                console.error('Error:', error);
                loading.innerHTML = '<p>⚠️ Erreur de chargement</p>';
            }
        }

        // Build operator filter buttons for bus
        function buildOperatorFilters(busLines) {
            const operators = {};
            busLines.forEach(line => {
                const op = line.operatorname || 'Autre';
                if (!operators[op]) operators[op] = 0;
                operators[op]++;
            });

            const container = document.getElementById('bus-operators');
            if (!container) return;

            // Sort by count
            const sorted = Object.entries(operators).sort((a, b) => b[1] - a[1]).slice(0, 10);

            container.innerHTML = sorted.map(([op, count]) =>
                `<button class="operator-btn" onclick="filterByOperator('${op}')">${op} (${count})</button>`
            ).join('');
        }

        // Search bus lines
        let currentBusOperator = null;

        function searchBus(query) {
            const container = document.getElementById('bus-lines-container');
            const info = document.getElementById('bus-results-info');
            const busLines = window.busLines || [];

            if (!query && !currentBusOperator) {
                container.innerHTML = '';
                info.textContent = 'Tapez un numéro pour rechercher';
                return;
            }

            let filtered = busLines;

            // Filter by operator if selected
            if (currentBusOperator) {
                filtered = filtered.filter(l => l.operatorname === currentBusOperator);
            }

            // Filter by search query
            if (query) {
                const q = query.toLowerCase();
                filtered = filtered.filter(line => {
                    const name = (line.shortname_line || line.name_line || '').toLowerCase();
                    return name.includes(q);
                });
            }

            // Limit results
            const maxShow = 50;
            const toShow = filtered.slice(0, maxShow);

            if (filtered.length === 0) {
                info.innerHTML = 'Aucune ligne trouvée';
                container.innerHTML = '';
            } else {
                info.innerHTML = `Affichage de <strong>${toShow.length}</strong> sur ${filtered.length} lignes`;
                container.innerHTML = renderLineBadges(toShow);

                if (filtered.length > maxShow) {
                    container.innerHTML += `<button class="load-more-btn" onclick="loadMoreBus('${query}')">Afficher plus...</button>`;
                }
            }
        }

        // Filter by operator
        function filterByOperator(operator) {
            // Toggle operator
            if (currentBusOperator === operator) {
                currentBusOperator = null;
            } else {
                currentBusOperator = operator;
            }

            // Update button states
            document.querySelectorAll('.operator-btn').forEach(btn => {
                btn.classList.toggle('active', btn.textContent.startsWith(currentBusOperator));
            });

            // Search with current input
            const input = document.querySelector('.bus-search-input');
            searchBus(input ? input.value : '');
        }

        // Load more bus lines
        window.busOffset = 50;
        function loadMoreBus(query) {
            const container = document.getElementById('bus-lines-container');
            const busLines = window.busLines || [];

            let filtered = busLines;
            if (currentBusOperator) {
                filtered = filtered.filter(l => l.operatorname === currentBusOperator);
            }
            if (query) {
                const q = query.toLowerCase();
                filtered = filtered.filter(line => {
                    const name = (line.shortname_line || line.name_line || '').toLowerCase();
                    return name.includes(q);
                });
            }

            window.busOffset += 50;
            const toShow = filtered.slice(0, window.busOffset);

            const info = document.getElementById('bus-results-info');
            info.innerHTML = `Affichage de <strong>${toShow.length}</strong> sur ${filtered.length} lignes`;

            container.innerHTML = renderLineBadges(toShow);
            if (filtered.length > window.busOffset) {
                container.innerHTML += `<button class="load-more-btn" onclick="loadMoreBus('${query}')">Afficher plus...</button>`;
            }
        }

        // Render line badges - route vers la bonne page selon le mode
        function renderLineBadges(lines) {
            return lines.map(line => {
                const name = line.shortname_line || line.name_line || '?';
                const displayName = name.length > 5 ? name.substring(0, 5) : name;
                const fullName = line.name_line || name;
                const color = line.colourweb_hexa || '0C3E78';
                const textColor = line.textcolourweb_hexa || 'FFFFFF';
                const lineId = line.id_line || '';
                const mode = (line.transportmode || 'bus').toLowerCase();
                const network = (line.networkname || '').toLowerCase();

                // Déterminer si c'est une ligne RER
                // Les RER sont classés comme "rail" avec networkname "RER" dans l'API IDFM
                const isRER = (mode === 'rer') ||
                              (mode === 'rail' && network === 'rer') ||
                              (mode === 'rail' && ['A', 'B', 'C', 'D', 'E'].includes(name.toUpperCase()) && network.includes('rer'));

                // Déterminer la page de destination selon le mode
                let targetPage = 'ligne.php'; // Métro par défaut
                if (isRER) {
                    targetPage = 'ligne-rer.php';
                } else if (mode === 'bus') {
                    targetPage = 'ligne-bus.php';
                }
                // Métro, tram, rail (Transilien) et autres restent sur ligne.php

                return `<a href="<?php echo SITE_URL; ?>/${targetPage}?id=${lineId}" class="line-badge-link"
                           style="background: #${color}; color: #${textColor};"
                           data-name="${fullName}">
                    ${displayName}
                </a>`;
            }).join('');
        }

        // Toggle mode section
        function toggleMode(modeId) {
            const section = document.getElementById('mode-' + modeId);
            section.classList.toggle('open');
        }

        // Scroll to mode
        function scrollToMode(modeId) {
            const section = document.getElementById('mode-' + modeId);
            if (section) {
                section.classList.add('open');
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // Show line info (placeholder)
        function showLineInfo(event, lineName) {
            event.preventDefault();
            alert('Ligne : ' + lineName + '\n\nDétails à venir...');
        }

        // Load on ready
        loadLines();
    </script>
</body>

</html>
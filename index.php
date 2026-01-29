<?php
/**
 * Mon Réseau IDF - Page d'accueil
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Accueil';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>Vos transports en Île-de-France<br>en toute simplicité</h1>
            <p>Suivez l'état du réseau, planifiez vos trajets et restez informé des travaux</p>
        </div>

        <!-- Search Box -->
        <div class="search-box">
            <h3><svg class="location-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"
                        fill="#3B82F6" />
                </svg> Où allons-nous ?</h3>
            <form class="search-form" action="#" method="GET">
                <div class="form-group">
                    <label for="departure">DÉPART</label>
                    <input type="text" id="departure" name="departure" placeholder="Gare, station, arrêt ou lieu">
                </div>
                <button type="button" class="swap-btn" title="Inverser">⇄</button>
                <div class="form-group">
                    <label for="arrival">ARRIVÉE</label>
                    <input type="text" id="arrival" name="arrival" placeholder="Gare, station, arrêt ou lieu">
                </div>
                <div class="form-group">
                    <label for="datetime">QUAND</label>
                    <select id="datetime" name="datetime">
                        <option value="now">Partir maintenant</option>
                        <option value="later">Choisir l'heure</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-accent btn-lg">Rechercher</button>
            </form>
        </div>
    </div>
</section>

<!-- Promo Banner -->
<section class="container">
    <div class="promo-banner">
        <div class="promo-content">
            <h3>🎫 Navigo Annuel</h3>
            <p>Voyagez en illimité toute l'année et bénéficiez du 12ème mois offert !</p>
        </div>
        <a href="#" class="btn btn-secondary">En savoir plus →</a>
    </div>
</section>

<!-- Quick Access -->
<section class="quick-access">
    <div class="container">
        <div class="section-title">
            <h2>Plus de possibilités pour vos déplacements</h2>
            <p>Accédez rapidement aux informations dont vous avez besoin</p>
        </div>

        <div class="quick-cards">
            <a href="#" class="quick-card">
                <div class="quick-card-icon">🚇</div>
                <h4>Lignes</h4>
                <p>Consultez les horaires et plans des lignes</p>
            </a>
            <a href="#" class="quick-card">
                <div class="quick-card-icon">🚧</div>
                <h4>Travaux</h4>
                <p>Retrouvez les travaux en cours et à venir</p>
            </a>
            <a href="#" class="quick-card">
                <div class="quick-card-icon">⚡</div>
                <h4>Info Trafic</h4>
                <p>Vérifiez si tout va bien sur votre ligne</p>
            </a>
            <a href="#" class="quick-card">
                <div class="quick-card-icon">♿</div>
                <h4>Accessibilité</h4>
                <p>Découvrez nos solutions d'accessibilité</p>
            </a>
        </div>
    </div>
</section>

<!-- News Section -->
<section class="news-section">
    <div class="container">
        <div class="section-title">
            <h2>Actualités</h2>
            <p>Les dernières nouvelles du réseau francilien</p>
        </div>

        <div class="news-grid">
            <article class="news-card">
                <div class="news-card-image">🚄</div>
                <div class="news-card-content">
                    <span class="news-card-date">Modifié le 25 janv. 2026</span>
                    <h4>Le jingle de vos transports va changer en 2026</h4>
                    <p>Un nouveau son pour accompagner vos voyages quotidiens dans les transports en commun franciliens.
                    </p>
                </div>
            </article>
            <article class="news-card">
                <div class="news-card-image">🗺️</div>
                <div class="news-card-content">
                    <span class="news-card-date">Modifié le 21 janv. 2026</span>
                    <h4>Plan des mobilités : à quoi ressembleront vos transports en 2030 ?</h4>
                    <p>Découvrez les 14 actions pour transformer les mobilités d'ici 2030.</p>
                </div>
            </article>
            <article class="news-card">
                <div class="news-card-image">🚌</div>
                <div class="news-card-content">
                    <span class="news-card-date">Modifié le 20 janv. 2026</span>
                    <h4>Nouveaux bus électriques sur les lignes 38 et 91</h4>
                    <p>IDFM continue sa transition écologique avec l'arrivée de nouveaux véhicules 100% électriques.</p>
                </div>
            </article>
        </div>

        <div class="text-center mt-8">
            <a href="#" class="btn btn-secondary">Voir toutes les actualités</a>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <h2>Votre réseau de transport</h2>
        <p>Le réseau de transport francilien est le 2ème plus dense et fréquenté au monde</p>

        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value">
                    <span class="stat-number" data-target="1900">1 900</span>
                    <span class="stat-unit">Lignes</span>
                </div>
                <div class="stat-label">de bus</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">
                    <span class="stat-number" data-target="2149">2 149</span>
                    <span class="stat-unit">Km</span>
                </div>
                <div class="stat-label">de réseau ferré</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">
                    <span class="stat-number" data-target="9">9,4</span>
                    <span class="stat-unit">Millions</span>
                </div>
                <div class="stat-label">de déplacements chaque jour</div>
            </div>
        </div>

        <div class="text-center mt-8">
            <a href="#" class="btn btn-secondary">Découvrir les projets et chantiers</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
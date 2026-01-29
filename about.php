<?php
/**
 * Mon Réseau IDF - Qui sommes-nous ?
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Qui sommes-nous ?';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero About -->
<section class="hero" style="padding: var(--spacing-12) 0;">
    <div class="container">
        <div class="hero-content">
            <h1>Qui sommes-nous ?</h1>
            <p>Découvrez l'équipe derrière Mon Réseau IDF</p>
        </div>
    </div>
</section>

<!-- About Content -->
<section class="quick-access">
    <div class="container" style="max-width: 900px;">

        <!-- Introduction -->
        <div class="card mb-8">
            <div class="card-body">
                <h2 style="color: var(--primary-dark); margin-bottom: var(--spacing-4);">🚇 Notre mission</h2>
                <p style="font-size: var(--font-size-lg); line-height: 1.8; color: var(--gray-600);">
                    <strong>Mon Réseau IDF</strong> est un projet indépendant créé par des passionnés de transport,
                    pour les passionnés et utilisateurs du réseau Île-de-France Mobilités. Notre objectif ?
                    Vous fournir les informations qui manquent parfois ailleurs : <strong>retards en temps
                        réel</strong>,
                    <strong>absences de service</strong>, <strong>infos sur le matériel roulant</strong>, et bien plus
                    encore.
                </p>
            </div>
        </div>

        <!-- Features Grid -->
        <div class="section-title">
            <h2>Ce que nous proposons</h2>
            <p>Une plateforme complète pour les usagers et passionnés du réseau</p>
        </div>

        <div class="quick-cards mb-8">
            <div class="quick-card">
                <div class="quick-card-icon">📊</div>
                <h4>Info Trafic</h4>
                <p>Retards et absences en temps réel sur toutes les lignes</p>
            </div>
            <div class="quick-card">
                <div class="quick-card-icon">🚧</div>
                <h4>Travaux</h4>
                <p>Calendrier des travaux et perturbations à venir</p>
            </div>
            <div class="quick-card">
                <div class="quick-card-icon">🚃</div>
                <h4>Matériel</h4>
                <p>Base de données sur les véhicules du réseau</p>
            </div>
            <div class="quick-card">
                <div class="quick-card-icon">📸</div>
                <h4>Photos</h4>
                <p>Galerie photo du réseau francilien</p>
            </div>
        </div>

        <!-- Founder -->
        <div class="card mb-8">
            <div class="card-body">
                <h2 style="color: var(--primary-dark); margin-bottom: var(--spacing-4);">👤 Le fondateur</h2>
                <div style="display: flex; gap: var(--spacing-6); align-items: flex-start; flex-wrap: wrap;">
                    <div
                        style="width: 100px; height: 100px; background: linear-gradient(135deg, var(--primary-dark), var(--accent)); border-radius: var(--radius-xl); display: flex; align-items: center; justify-content: center; font-size: 48px; flex-shrink: 0;">
                        🧑‍💻
                    </div>
                    <div style="flex: 1; min-width: 250px;">
                        <h3 style="margin-bottom: var(--spacing-2);">Anthony V.</h3>
                        <p style="color: var(--gray-500); margin-bottom: var(--spacing-3);">Fondateur & Développeur</p>
                        <p style="color: var(--gray-600); line-height: 1.7;">
                            Utilisateur quotidien du réseau IDFM, passionné de transports en commun et photographe
                            du réseau francilien. J'ai créé Mon Réseau IDF pour combler les manques d'information
                            que je constatais en tant qu'usager : les retards, les absences de service,
                            et tout ce qui impacte notre quotidien de voyageur.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Disclaimer -->
        <div class="card mb-8" style="border-left: 4px solid var(--warning);">
            <div class="card-body">
                <h3 style="color: var(--gray-800); margin-bottom: var(--spacing-3);">⚠️ Avertissement</h3>
                <p style="color: var(--gray-600);">
                    <strong>Mon Réseau IDF</strong> est un site <strong>indépendant et non officiel</strong>.
                    Nous ne sommes pas affiliés à Île-de-France Mobilités, la RATP, la SNCF, ou tout autre
                    opérateur de transport. Les informations présentées proviennent de sources publiques
                    et sont fournies à titre informatif.
                </p>
            </div>
        </div>

        <!-- Join Us -->
        <div class="card"
            style="background: linear-gradient(135deg, var(--primary-dark), var(--primary)); color: white;">
            <div class="card-body" style="text-align: center; padding: var(--spacing-10);">
                <h2 style="color: white; margin-bottom: var(--spacing-4);">🤝 Rejoignez l'aventure !</h2>
                <p
                    style="opacity: 0.9; margin-bottom: var(--spacing-6); max-width: 600px; margin-left: auto; margin-right: auto;">
                    On recherche des passionnés bénévoles pour nous aider à développer le projet :
                    rédacteurs, photographes, développeurs, ou simplement des mordus de transport !
                </p>
                <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-secondary btn-lg">
                    Créer un compte →
                </a>
            </div>
        </div>

    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
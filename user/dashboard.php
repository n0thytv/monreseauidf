<?php
/**
 * Mon Réseau IDF - Dashboard Utilisateur
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier la connexion
if (!isLoggedIn()) {
    redirect(SITE_URL . '/login.php');
}

// Rediriger les admins vers leur dashboard
if (isAdmin()) {
    redirect(SITE_URL . '/admin/dashboard.php');
}

$pageTitle = 'Mon Espace';
$currentUser = getCurrentUser($pdo);
$flash = getFlashMessage();
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
</head>

<body>
    <!-- Header simplifié pour dashboard -->
    <header class="header">
        <div class="container">
            <a href="<?php echo SITE_URL; ?>" class="logo">
                <div class="logo-icon">🚇</div>
                <span>Mon Réseau <strong>IDF</strong></span>
            </a>

            <div class="nav-actions">
                <span style="color: var(--white); margin-right: var(--spacing-4);">
                    👤
                    <?php echo sanitize($currentUser['username']); ?>
                </span>
                <a href="<?php echo SITE_URL; ?>/logout.php" class="btn btn-outline btn-sm">Déconnexion</a>
            </div>
        </div>
    </header>

    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <nav>
                <ul class="sidebar-nav">
                    <li>
                        <a href="<?php echo SITE_URL; ?>/user/dashboard.php" class="active">
                            📊 Tableau de bord
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            🚇 Mes trajets
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            📈 Statistiques
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            ⭐ Favoris
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            🔔 Alertes
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            ⚙️ Paramètres
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo $flash['type'] === 'success' ? '✅' : '⚠️'; ?>
                    <?php echo sanitize($flash['message']); ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h1>Bienvenue,
                    <?php echo sanitize($currentUser['username']); ?> !
                </h1>
                <p>Gérez vos trajets et consultez vos statistiques de déplacement</p>
            </div>

            <!-- Empty State -->
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-state-icon">🚧</div>
                        <h3>Contenu à venir prochainement</h3>
                        <p>
                            Cette section est en cours de développement.<br>
                            Bientôt, vous pourrez :
                        </p>
                        <ul style="list-style: none; margin: var(--spacing-4) 0; color: var(--gray-600);">
                            <li>📍 Suivre vos trajets quotidiens</li>
                            <li>📊 Consulter vos statistiques de déplacement</li>
                            <li>⭐ Gérer vos lignes favorites</li>
                            <li>🔔 Configurer des alertes personnalisées</li>
                        </ul>
                        <a href="<?php echo SITE_URL; ?>" class="btn btn-primary">
                            ← Retour à l'accueil
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
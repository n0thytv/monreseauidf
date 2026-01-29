<?php
/**
 * Mon Réseau IDF - Paramètres (Admin)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier la connexion et les droits admin
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Paramètres';
$currentUser = getCurrentUser($pdo);
$flash = getFlashMessage();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idfm_api_key = trim($_POST['idfm_api_key'] ?? '');
    $site_name = sanitize($_POST['site_name'] ?? 'Mon Réseau IDF');
    $site_description = sanitize($_POST['site_description'] ?? '');

    setSetting($pdo, 'idfm_api_key', $idfm_api_key);
    setSetting($pdo, 'site_name', $site_name);
    setSetting($pdo, 'site_description', $site_description);

    setFlashMessage('success', 'Paramètres sauvegardés avec succès !');
    redirect(SITE_URL . '/admin/settings.php');
}

// Récupérer les paramètres actuels
$idfm_api_key = getSetting($pdo, 'idfm_api_key', '');
$site_name = getSetting($pdo, 'site_name', 'Mon Réseau IDF');
$site_description = getSetting($pdo, 'site_description', '');
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
    <!-- Header Admin -->
    <header class="header">
        <div class="container">
            <a href="<?php echo SITE_URL; ?>" class="logo">
                <div class="logo-icon">🚇</div>
                <span>Mon Réseau <strong>IDF</strong></span>
            </a>

            <div class="nav-actions">
                <span class="badge badge-primary" style="margin-right: var(--spacing-2);">Admin</span>
                <span style="color: var(--white); margin-right: var(--spacing-4);">
                    👤
                    <?php echo sanitize($currentUser['username']); ?>
                </span>
                <a href="<?php echo SITE_URL; ?>/logout.php" class="btn btn-outline btn-sm">Déconnexion</a>
            </div>
        </div>
    </header>

    <div class="dashboard-layout">
        <!-- Sidebar Admin -->
        <aside class="sidebar">
            <nav>
                <ul class="sidebar-nav">
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php">
                            📊 Tableau de bord
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/users.php">
                            👥 Utilisateurs
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            📝 Articles (bientôt)
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            🚇 Lignes (bientôt)
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/admin/settings.php" class="active">
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
                <h1>Paramètres</h1>
                <p>Configurez les paramètres de votre site</p>
            </div>

            <!-- Settings Form -->
            <div class="card" style="max-width: 700px;">
                <div class="card-header">
                    <h3>🔧 Configuration générale</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group" style="margin-bottom: var(--spacing-4);">
                            <label class="form-label" for="site_name">Nom du site</label>
                            <input type="text" id="site_name" name="site_name" class="form-control"
                                value="<?php echo sanitize($site_name); ?>">
                        </div>

                        <div class="form-group" style="margin-bottom: var(--spacing-6);">
                            <label class="form-label" for="site_description">Description du site</label>
                            <input type="text" id="site_description" name="site_description" class="form-control"
                                value="<?php echo sanitize($site_description); ?>">
                        </div>

                        <hr style="border: none; border-top: 1px solid var(--gray-200); margin: var(--spacing-6) 0;">

                        <h4 style="margin-bottom: var(--spacing-4); color: var(--primary-dark);">🔑 API IDFM PRIM</h4>

                        <div class="form-group" style="margin-bottom: var(--spacing-4);">
                            <label class="form-label" for="idfm_api_key">Clé API IDFM</label>
                            <input type="text" id="idfm_api_key" name="idfm_api_key" class="form-control"
                                placeholder="Votre clé API PRIM" value="<?php echo sanitize($idfm_api_key); ?>">
                            <span class="form-text">
                                Obtenez votre clé sur <a href="https://prim.iledefrance-mobilites.fr"
                                    target="_blank">prim.iledefrance-mobilites.fr</a>
                            </span>
                        </div>

                        <?php if ($idfm_api_key): ?>
                            <div class="alert alert-success" style="margin-bottom: var(--spacing-4);">
                                ✅ Clé API configurée
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning" style="margin-bottom: var(--spacing-4);">
                                ⚠️ Clé API non configurée - Les fonctionnalités IDFM seront limitées
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary">
                            💾 Sauvegarder les paramètres
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
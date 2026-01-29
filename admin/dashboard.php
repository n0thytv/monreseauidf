<?php
/**
 * Mon Réseau IDF - Dashboard Admin
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier la connexion et les droits admin
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Administration';
$currentUser = getCurrentUser($pdo);
$flash = getFlashMessage();

// Statistiques
$totalUsers = countUsers($pdo);
$recentUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
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
                        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="active">
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
                        <a href="<?php echo SITE_URL; ?>/admin/settings.php">
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
                <h1>Tableau de bord</h1>
                <p>Vue d'ensemble de votre site Mon Réseau IDF</p>
            </div>

            <!-- Stats Cards -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value">
                                <?php echo $totalUsers; ?>
                            </div>
                            <div class="stat-card-label">Utilisateurs</div>
                        </div>
                        <div class="stat-card-icon blue">👥</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value">0</div>
                            <div class="stat-card-label">Articles</div>
                        </div>
                        <div class="stat-card-icon green">📝</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value">0</div>
                            <div class="stat-card-label">Lignes</div>
                        </div>
                        <div class="stat-card-icon yellow">🚇</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value">0</div>
                            <div class="stat-card-label">Trajets suivis</div>
                        </div>
                        <div class="stat-card-icon red">📍</div>
                    </div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="card">
                <div class="card-header flex-between">
                    <h3>Utilisateurs récents</h3>
                    <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn btn-secondary btn-sm">
                        Voir tous →
                    </a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Inscrit le</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td>
                                        <?php echo sanitize($user['username']); ?>
                                    </td>
                                    <td>
                                        <?php echo sanitize($user['email']); ?>
                                    </td>
                                    <td>
                                        <span
                                            class="badge <?php echo $user['role'] === 'admin' ? 'badge-primary' : 'badge-success'; ?>">
                                            <?php echo $user['role'] === 'admin' ? 'Admin' : 'Utilisateur'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y à H:i', strtotime($user['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
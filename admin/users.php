<?php
/**
 * Mon Réseau IDF - Gestion des utilisateurs (Admin)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier la connexion et les droits admin
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

$pageTitle = 'Gestion des utilisateurs';
$currentUser = getCurrentUser($pdo);
$flash = getFlashMessage();
$users = getAllUsers($pdo);
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
                        <a href="<?php echo SITE_URL; ?>/admin/users.php" class="active">
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

            <div class="page-header flex-between">
                <div>
                    <h1>Utilisateurs</h1>
                    <p>Gérez les utilisateurs de la plateforme</p>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin/add_user.php" class="btn btn-primary">
                    ➕ Ajouter un utilisateur
                </a>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-body" style="padding: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom d'utilisateur</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Inscrit le</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: var(--spacing-8);">
                                        Aucun utilisateur trouvé
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>#
                                            <?php echo $user['id']; ?>
                                        </td>
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
                                            <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                        </td>
                                        <td>
                                            <div class="flex gap-2">
                                                <a href="<?php echo SITE_URL; ?>/admin/edit_user.php?id=<?php echo $user['id']; ?>"
                                                    class="btn btn-secondary btn-sm">
                                                    ✏️ Modifier
                                                </a>
                                                <?php if ($user['id'] !== $currentUser['id']): ?>
                                                    <a href="<?php echo SITE_URL; ?>/admin/delete_user.php?id=<?php echo $user['id']; ?>"
                                                        class="btn btn-danger btn-sm"
                                                        data-confirm="Êtes-vous sûr de vouloir supprimer cet utilisateur ?">
                                                        🗑️ Supprimer
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo SITE_URL; ?>/js/main.js"></script>
</body>

</html>
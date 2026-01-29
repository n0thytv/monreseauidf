<?php
/**
 * Mon Réseau IDF - Modifier un utilisateur (Admin)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier la connexion et les droits admin
if (!isLoggedIn() || !isAdmin()) {
    redirect(SITE_URL . '/login.php');
}

// Récupérer l'ID de l'utilisateur
$userId = intval($_GET['id'] ?? 0);
if (!$userId) {
    redirect(SITE_URL . '/admin/users.php');
}

// Récupérer l'utilisateur
$user = getUserById($pdo, $userId);
if (!$user) {
    setFlashMessage('error', 'Utilisateur non trouvé.');
    redirect(SITE_URL . '/admin/users.php');
}

$pageTitle = 'Modifier ' . $user['username'];
$currentUser = getCurrentUser($pdo);
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitize($_POST['role'] ?? 'user');

    // Validation
    if (empty($username) || empty($email)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez entrer une adresse email valide.';
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif (!in_array($role, ['user', 'admin'])) {
        $error = 'Rôle invalide.';
    } else {
        // Vérifier si l'email existe déjà (pour un autre utilisateur)
        $existingUser = getUserByEmail($pdo, $email);
        if ($existingUser && $existingUser['id'] !== $userId) {
            $error = 'Cette adresse email est déjà utilisée par un autre compte.';
        } else {
            // Mettre à jour l'utilisateur
            $passwordToUpdate = !empty($password) ? $password : null;
            if (updateUser($pdo, $userId, $username, $email, $role, $passwordToUpdate)) {
                setFlashMessage('success', 'Utilisateur mis à jour avec succès !');
                redirect(SITE_URL . '/admin/users.php');
            } else {
                $error = 'Une erreur est survenue lors de la mise à jour.';
            }
        }
    }
}
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
            <div class="page-header">
                <h1>Modifier l'utilisateur</h1>
                <p>Modifiez les informations de
                    <?php echo sanitize($user['username']); ?>
                </p>
            </div>

            <div class="card" style="max-width: 600px;">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            ⚠️
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group" style="margin-bottom: var(--spacing-4);">
                            <label class="form-label" for="username">Nom d'utilisateur *</label>
                            <input type="text" id="username" name="username" class="form-control" required minlength="3"
                                value="<?php echo sanitize($user['username']); ?>">
                        </div>

                        <div class="form-group" style="margin-bottom: var(--spacing-4);">
                            <label class="form-label" for="email">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required
                                value="<?php echo sanitize($user['email']); ?>">
                        </div>

                        <div class="form-group" style="margin-bottom: var(--spacing-4);">
                            <label class="form-label" for="password">Nouveau mot de passe</label>
                            <input type="password" id="password" name="password" class="form-control" minlength="6">
                            <span class="form-text">Laissez vide pour conserver le mot de passe actuel</span>
                        </div>

                        <div class="form-group" style="margin-bottom: var(--spacing-6);">
                            <label class="form-label" for="role">Rôle *</label>
                            <select id="role" name="role" class="form-control">
                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>
                                    Utilisateur
                                </option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>
                                    Administrateur
                                </option>
                            </select>
                        </div>

                        <div class="flex gap-4">
                            <button type="submit" class="btn btn-primary">
                                ✅ Enregistrer les modifications
                            </button>
                            <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn btn-secondary">
                                Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
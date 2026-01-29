<?php
/**
 * Mon Réseau IDF - Page de connexion
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(SITE_URL . '/admin/dashboard.php');
    } else {
        redirect(SITE_URL . '/user/dashboard.php');
    }
}

$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $user = getUserByEmail($pdo, $email);

        if ($user && verifyPassword($password, $user['password'])) {
            // Connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['username'];

            setFlashMessage('success', 'Connexion réussie ! Bienvenue ' . $user['username']);

            if ($user['role'] === 'admin') {
                redirect(SITE_URL . '/admin/dashboard.php');
            } else {
                redirect(SITE_URL . '/user/dashboard.php');
            }
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Mon Réseau IDF</title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
</head>

<body>
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-logo">
                <div class="logo-icon" style="width: 64px; height: 64px; font-size: 32px; margin: 0 auto;">🚇</div>
                <h2>Mon Réseau IDF</h2>
                <p style="color: var(--gray-500);">Connectez-vous à votre espace</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    ⚠️
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="votre@email.fr"
                        required value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••"
                        required>
                </div>

                <button type="submit" class="btn btn-primary btn-lg">Se connecter</button>
            </form>

            <div class="auth-links">
                <p>Pas encore de compte ? <a href="<?php echo SITE_URL; ?>/register.php">S'inscrire</a></p>
                <p style="margin-top: var(--spacing-2);"><a href="<?php echo SITE_URL; ?>">← Retour à l'accueil</a></p>
            </div>
        </div>
    </div>
</body>

</html>
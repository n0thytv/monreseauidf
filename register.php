<?php
/**
 * Mon Réseau IDF - Page d'inscription
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirect(SITE_URL . '/user/dashboard.php');
}

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (strlen($username) < 3) {
        $error = 'Le nom d\'utilisateur doit contenir au moins 3 caractères.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez entrer une adresse email valide.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        // Vérifier si l'email existe déjà
        $existingUser = getUserByEmail($pdo, $email);
        if ($existingUser) {
            $error = 'Cette adresse email est déjà utilisée.';
        } else {
            // Créer l'utilisateur
            if (createUser($pdo, $username, $email, $password)) {
                setFlashMessage('success', 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.');
                redirect(SITE_URL . '/login.php');
            } else {
                $error = 'Une erreur est survenue lors de la création du compte.';
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
    <title>Inscription - Mon Réseau IDF</title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
</head>

<body>
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-logo">
                <div class="logo-icon" style="width: 64px; height: 64px; font-size: 32px; margin: 0 auto;">🚇</div>
                <h2>Mon Réseau IDF</h2>
                <p style="color: var(--gray-500);">Créez votre compte</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    ⚠️
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Votre pseudo"
                        required minlength="3"
                        value="<?php echo isset($_POST['username']) ? sanitize($_POST['username']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="votre@email.fr"
                        required value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••"
                        required minlength="6">
                    <span class="form-text">Minimum 6 caractères</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirm">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                        placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary btn-lg">Créer mon compte</button>
            </form>

            <div class="auth-links">
                <p>Déjà inscrit ? <a href="<?php echo SITE_URL; ?>/login.php">Se connecter</a></p>
                <p style="margin-top: var(--spacing-2);"><a href="<?php echo SITE_URL; ?>">← Retour à l'accueil</a></p>
            </div>
        </div>
    </div>
</body>

</html>
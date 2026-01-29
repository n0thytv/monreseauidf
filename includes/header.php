<?php
/**
 * Mon Réseau IDF - Header
 */

// Déterminer la page active
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Mon Réseau IDF - Votre compagnon pour les transports en Île-de-France">
    <title>
        <?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Mon Réseau IDF
    </title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/assets/favicon.ico">
</head>

<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div class="top-bar-left">
                <a href="<?php echo SITE_URL; ?>/about.php">Qui sommes-nous ?</a>
            </div>
            <div class="top-bar-right">
                <a href="<?php echo SITE_URL; ?>/points-de-vente.php">📍 Points de vente</a>
                <a href="#">❓ Aide et contacts</a>
                <span>🇫🇷 FR</span>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <a href="<?php echo SITE_URL; ?>" class="logo">
                <div class="logo-icon">🚇</div>
                <span>Mon Réseau <strong>IDF</strong></span>
            </a>

            <nav class="nav-main">
                <a href="<?php echo SITE_URL; ?>/lignes.php">Lignes</a>
                <a href="<?php echo SITE_URL; ?>/travaux.php">Travaux</a>
                <a href="<?php echo SITE_URL; ?>/trafic.php">Trafic</a>
                <a href="<?php echo SITE_URL; ?>/blog.php">Blog</a>
                <a href="<?php echo SITE_URL; ?>/vehicules.php">Véhicules</a>
            </nav>

            <div class="nav-actions">
                <button class="btn btn-secondary btn-sm">🔍 Rechercher</button>
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="btn btn-outline btn-sm">⚙️ Admin</a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/user/dashboard.php" class="btn btn-outline btn-sm">👤 Mon espace</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/logout.php" class="btn btn-outline btn-sm">Déconnexion</a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-outline btn-sm">👤 Mon espace</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
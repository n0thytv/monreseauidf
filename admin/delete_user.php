<?php
/**
 * Mon Réseau IDF - Supprimer un utilisateur (Admin)
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

// Récupérer l'utilisateur actuel et celui à supprimer
$currentUser = getCurrentUser($pdo);
$user = getUserById($pdo, $userId);

// Vérifications
if (!$user) {
    setFlashMessage('error', 'Utilisateur non trouvé.');
    redirect(SITE_URL . '/admin/users.php');
}

// Empêcher l'auto-suppression
if ($user['id'] === $currentUser['id']) {
    setFlashMessage('error', 'Vous ne pouvez pas supprimer votre propre compte.');
    redirect(SITE_URL . '/admin/users.php');
}

// Supprimer l'utilisateur
if (deleteUser($pdo, $userId)) {
    setFlashMessage('success', 'Utilisateur "' . $user['username'] . '" supprimé avec succès.');
} else {
    setFlashMessage('error', 'Une erreur est survenue lors de la suppression.');
}

redirect(SITE_URL . '/admin/users.php');

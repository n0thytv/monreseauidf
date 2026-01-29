<?php
/**
 * Mon Réseau IDF - Déconnexion
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Détruire la session
session_destroy();

// Rediriger vers l'accueil
header("Location: " . SITE_URL);
exit;

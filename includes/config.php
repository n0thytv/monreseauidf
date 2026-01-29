<?php
/**
 * Mon Réseau IDF - Configuration
 * Configuration de la base de données et paramètres généraux
 */

// Démarrage de la session
session_start();

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'monreseauidf');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configuration du site
define('SITE_NAME', 'Mon Réseau IDF');
define('SITE_URL', 'http://localhost/monreseauidf');

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // En développement, afficher l'erreur
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Fuseau horaire
date_default_timezone_set('Europe/Paris');

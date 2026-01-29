-- Mon Réseau IDF - Schéma de base de données
-- Exécuter ce script dans phpMyAdmin ou via CLI MySQL

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS monreseauidf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE monreseauidf;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion d'un utilisateur admin par défaut
-- Mot de passe : admin123 (hashé avec password_hash)
INSERT INTO users (username, email, password, role) VALUES 
('Admin', 'admin@monreseauidf.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Utilisateur Test', 'user@monreseauidf.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');

-- Table des paramètres du site
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paramètres par défaut
INSERT INTO settings (setting_key, setting_value) VALUES 
('idfm_api_key', ''),
('site_name', 'Mon Réseau IDF'),
('site_description', 'Votre compagnon pour les transports en Île-de-France')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
-- Pour créer un nouveau hash : echo password_hash('votre_mot_de_passe', PASSWORD_DEFAULT);

<?php
/**
 * Mon Réseau IDF - Fonctions utilitaires
 */

/**
 * Nettoie une entrée utilisateur
 */
function sanitize($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirige vers une URL
 */
function redirect($url)
{
    header("Location: " . $url);
    exit;
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur est admin
 */
function isAdmin()
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Récupère l'utilisateur courant
 */
function getCurrentUser($pdo)
{
    if (!isLoggedIn()) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Hash un mot de passe
 */
function hashPassword($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Vérifie un mot de passe
 */
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

// ==================== CRUD Utilisateurs ====================

/**
 * Récupère tous les utilisateurs
 */
function getAllUsers($pdo)
{
    $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

/**
 * Récupère un utilisateur par ID
 */
function getUserById($pdo, $id)
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Récupère un utilisateur par email
 */
function getUserByEmail($pdo, $email)
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

/**
 * Crée un nouvel utilisateur
 */
function createUser($pdo, $username, $email, $password, $role = 'user')
{
    $hashedPassword = hashPassword($password);

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$username, $email, $hashedPassword, $role]);
}

/**
 * Met à jour un utilisateur
 */
function updateUser($pdo, $id, $username, $email, $role, $password = null)
{
    if ($password) {
        $hashedPassword = hashPassword($password);
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
        return $stmt->execute([$username, $email, $role, $hashedPassword, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
        return $stmt->execute([$username, $email, $role, $id]);
    }
}

/**
 * Supprime un utilisateur
 */
function deleteUser($pdo, $id)
{
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Compte le nombre total d'utilisateurs
 */
function countUsers($pdo)
{
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    return $stmt->fetch()['count'];
}

/**
 * Affiche un message flash
 */
function setFlashMessage($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Récupère et supprime le message flash
 */
function getFlashMessage()
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ==================== Paramètres du site ====================

/**
 * Récupère un paramètre
 */
function getSetting($pdo, $key, $default = '')
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Met à jour un paramètre
 */
function setSetting($pdo, $key, $value)
{
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

/**
 * Récupère tous les paramètres
 */
function getAllSettings($pdo)
{
    try {
        $stmt = $pdo->query("SELECT * FROM settings ORDER BY setting_key");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

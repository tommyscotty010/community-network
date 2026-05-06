<?php
/**
 * WALKER NETWORK v3 – Setup
 * Esegui una volta, poi ELIMINA questo file.
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'community_network');

$adminUsername = 'admin';
$adminEmail    = 'admin@localhost';
$adminPassword = 'Admin123!'; // CAMBIA QUESTA PASSWORD

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `".DB_NAME."`");

    // Elimina tutto e ricrea (fresh install)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach (['webrtc_signals','group_members','groups','attachments','map_pins',
              'messages','invite_tokens','user_profiles','users'] as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `$t`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    $pdo->exec("
        CREATE TABLE users (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username   VARCHAR(50)  UNIQUE NOT NULL,
            email      VARCHAR(120) UNIQUE NOT NULL,
            password   VARCHAR(255) NOT NULL,
            is_admin   TINYINT(1)   DEFAULT 0,
            last_seen  DATETIME     NULL DEFAULT NULL,
            created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE user_profiles (
            user_id INT UNSIGNED PRIMARY KEY,
            bio     VARCHAR(300) NULL,
            avatar  VARCHAR(200) NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE invite_tokens (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token      VARCHAR(64) UNIQUE NOT NULL,
            created_by INT UNSIGNED NOT NULL,
            used_by    INT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            used_at    DATETIME NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (used_by)    REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE attachments (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uploader_id INT UNSIGNED NOT NULL,
            filename    VARCHAR(200) NOT NULL,
            original    VARCHAR(200) NOT NULL,
            mime        VARCHAR(100) NOT NULL,
            size        INT UNSIGNED NOT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE `groups` (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(80)  NOT NULL,
            description VARCHAR(300) NULL,
            created_by  INT UNSIGNED NOT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE group_members (
            group_id  INT UNSIGNED NOT NULL,
            user_id   INT UNSIGNED NOT NULL,
            role      ENUM('admin','member') DEFAULT 'member',
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (group_id, user_id),
            FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id)  REFERENCES users(id)    ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // content è nullable per consentire messaggi con solo allegato
    $pdo->exec("
        CREATE TABLE messages (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sender_id     INT UNSIGNED NOT NULL,
            receiver_id   INT UNSIGNED NULL,
            group_id      INT UNSIGNED NULL,
            content       TEXT         NULL,
            attachment_id INT UNSIGNED NULL,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id)     REFERENCES users(id)        ON DELETE CASCADE,
            FOREIGN KEY (receiver_id)   REFERENCES users(id)        ON DELETE CASCADE,
            FOREIGN KEY (group_id)      REFERENCES `groups`(id)     ON DELETE CASCADE,
            FOREIGN KEY (attachment_id) REFERENCES attachments(id)  ON DELETE SET NULL,
            INDEX idx_global (receiver_id, group_id, created_at),
            INDEX idx_dm     (sender_id, receiver_id, created_at),
            INDEX idx_group  (group_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE map_pins (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED NOT NULL,
            lat        DECIMAL(10,7) NOT NULL,
            lng        DECIMAL(10,7) NOT NULL,
            message    VARCHAR(500) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // TTL più lungo (5min) per non perdere segnali WebRTC sotto carico
    $pdo->exec("
        CREATE TABLE webrtc_signals (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            from_user_id INT UNSIGNED NOT NULL,
            to_user_id   INT UNSIGNED NOT NULL,
            type         VARCHAR(30)  NOT NULL,
            payload      MEDIUMTEXT   NOT NULL,
            processed    TINYINT(1)   DEFAULT 0,
            created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (to_user_id)   REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_pending (to_user_id, processed, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Admin account
    $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
    $pdo->prepare("INSERT IGNORE INTO users (username,email,password,is_admin) VALUES (?,?,?,1)")
        ->execute([$adminUsername, $adminEmail, $hash]);

    echo "<pre style='font-family:monospace;background:#0d0d0d;color:#68d391;padding:20px;margin:0'>";
    echo "✅ Database <b>".DB_NAME."</b> creato\n";
    echo "✅ Tutte le tabelle create\n";
    echo "✅ Account admin creato\n\n";
    echo "─────────────────────────────\n";
    echo "Username : <b>{$adminUsername}</b>\n";
    echo "Password : <b>{$adminPassword}</b>\n";
    echo "─────────────────────────────\n\n";
    echo "<span style='color:#fc8181'>⚠ ELIMINA setup.php immediatamente!</span>\n";
    echo "</pre>";

} catch (PDOException $e) {
    echo "<pre style='color:#fc8181;background:#0d0d0d;padding:20px'>❌ ".htmlspecialchars($e->getMessage())."</pre>";
}

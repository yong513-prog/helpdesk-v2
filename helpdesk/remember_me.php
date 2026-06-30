<?php

/**
 * WLS Helpdesk Remember Me helper.
 * Keeps mobile/PWA users logged in for 30 days using a random token stored in a HttpOnly cookie.
 */

if(!defined('HD_REMEMBER_COOKIE'))
{
    define('HD_REMEMBER_COOKIE', 'wls_helpdesk_remember');
}

if(!defined('HD_REMEMBER_DAYS'))
{
    define('HD_REMEMBER_DAYS', 30);
}

function hd_remember_cookie_options($expires)
{
    $secure = false;

    if(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    {
        $secure = true;
    }

    if(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    {
        $secure = true;
    }

    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
}

function hd_ensure_remember_table(PDO $pdo)
{
    static $done = false;
    if($done) return;

    $pdo->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        user_agent_hash VARCHAR(64) NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME NULL,
        UNIQUE KEY uniq_token_hash (token_hash),
        KEY idx_user_id (user_id),
        KEY idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $done = true;
}

function hd_current_user_agent_hash()
{
    return hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

function hd_set_login_session_from_user(array $user)
{
    if(session_status() === PHP_SESSION_NONE)
    {
        session_start();
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'] ?? '';

    $role = $user['role'] ?? 'staff';
    if(function_exists('normalize_role'))
    {
        $role = normalize_role($role);
    }

    $_SESSION['role'] = $role;
    $_SESSION['ticket_scope'] = $user['ticket_scope'] ?? 'OWN';
    $_SESSION['branch'] = $user['branch'] ?? '';
    $_SESSION['department'] = $user['department'] ?? '';
    $_SESSION['branch_access'] = $user['branch_access'] ?? ($user['branch'] ?? '');
    $_SESSION['ticket_branch_access'] = $user['ticket_branch_access'] ?? '';
    $_SESSION['ticket_pic_access'] = $user['ticket_pic_access'] ?? '';
    $_SESSION['full_name'] = $user['full_name'] ?? '';
    $_SESSION['remember_restored_at'] = date('Y-m-d H:i:s');
}

function hd_issue_remember_token(PDO $pdo, $userId)
{
    hd_ensure_remember_table($pdo);

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresTs = time() + (HD_REMEMBER_DAYS * 86400);
    $expiresAt = date('Y-m-d H:i:s', $expiresTs);
    $uaHash = hd_current_user_agent_hash();

    // Keep only one active token per user/device login action to reduce stale tokens.
    $cleanup = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND (expires_at < NOW() OR user_agent_hash = ?)");
    $cleanup->execute([$userId, $uaHash]);

    $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token_hash, user_agent_hash, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $tokenHash, $uaHash, $expiresAt]);

    setcookie(HD_REMEMBER_COOKIE, $token, hd_remember_cookie_options($expiresTs));
}

function hd_clear_current_remember_token(PDO $pdo)
{
    hd_ensure_remember_table($pdo);

    if(!empty($_COOKIE[HD_REMEMBER_COOKIE]))
    {
        $tokenHash = hash('sha256', $_COOKIE[HD_REMEMBER_COOKIE]);
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token_hash = ?");
        $stmt->execute([$tokenHash]);
    }

    setcookie(HD_REMEMBER_COOKIE, '', hd_remember_cookie_options(time() - 3600));
}

function hd_restore_remembered_login(PDO $pdo)
{
    if(session_status() === PHP_SESSION_NONE)
    {
        session_start();
    }

    if(!empty($_SESSION['user_id']))
    {
        return true;
    }

    if(empty($_COOKIE[HD_REMEMBER_COOKIE]))
    {
        return false;
    }

    try
    {
        hd_ensure_remember_table($pdo);

        $token = $_COOKIE[HD_REMEMBER_COOKIE];
        $tokenHash = hash('sha256', $token);
        $uaHash = hd_current_user_agent_hash();

        $stmt = $pdo->prepare("SELECT rt.id AS remember_id, rt.expires_at, rt.user_agent_hash, u.*
            FROM remember_tokens rt
            INNER JOIN users u ON u.id = rt.user_id
            WHERE rt.token_hash = ?
              AND rt.expires_at > NOW()
            LIMIT 1");
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$user)
        {
            setcookie(HD_REMEMBER_COOKIE, '', hd_remember_cookie_options(time() - 3600));
            return false;
        }

        if(isset($user['status']) && $user['status'] !== 'active')
        {
            hd_clear_current_remember_token($pdo);
            return false;
        }

        // If user agent changed, reject the token. This keeps copied cookies less useful.
        if(!empty($user['user_agent_hash']) && !hash_equals($user['user_agent_hash'], $uaHash))
        {
            hd_clear_current_remember_token($pdo);
            return false;
        }

        session_regenerate_id(true);
        hd_set_login_session_from_user($user);

        // Rotate token after successful restore.
        $delete = $pdo->prepare("DELETE FROM remember_tokens WHERE id = ?");
        $delete->execute([$user['remember_id']]);
        hd_issue_remember_token($pdo, $user['id']);

        return true;
    }
    catch(Exception $e)
    {
        return false;
    }
}

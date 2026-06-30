<?php

session_start();
require_once __DIR__ . '/lang.php';
ob_start('hd_translate_buffer');

require 'db.php';
require_once 'remember_me.php';
require_once 'access_control.php';
require_once 'audit_log.php';

function ensure_login_security_columns($pdo)
{
    $columns = [
        'failed_login_attempts' => "ALTER TABLE users ADD COLUMN failed_login_attempts INT NOT NULL DEFAULT 0 AFTER status",
        'locked_until' => "ALTER TABLE users ADD COLUMN locked_until DATETIME NULL AFTER failed_login_attempts",
        'last_failed_login' => "ALTER TABLE users ADD COLUMN last_failed_login DATETIME NULL AFTER locked_until"
    ];

    foreach($columns as $column => $sql)
    {
        try
        {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE ?");
            $stmt->execute([$column]);

            if(!$stmt->fetch())
            {
                $pdo->exec($sql);
            }
        }
        catch(Exception $e)
        {
            // Keep login usable even if the installer SQL has not been executed yet.
        }
    }
}

function login_lock_message($lockedUntil)
{
    if(empty($lockedUntil))
    {
        return '';
    }

    $timestamp = strtotime($lockedUntil);

    if(!$timestamp || $timestamp <= time())
    {
        return '';
    }

    return 'Account locked. Please try again after '.date('d/m/Y h:i A', $timestamp).'.';
}

ensure_login_security_columns($pdo);

if(!empty($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST')
{
    header("Location: dashboard.php");
    exit;
}

$remembered_username = $_COOKIE['helpdesk_remember_username'] ?? '';

if($_SERVER["REQUEST_METHOD"] == "POST")
{
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberId = isset($_POST['remember_id']);
    $rememberLogin = isset($_POST['remember_me']);

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if($user)
    {
        $lockMessage = login_lock_message($user['locked_until'] ?? null);

        if($lockMessage !== '')
        {
            $error = $lockMessage;
        }
        else
        {
            $storedPassword = trim($user['password']);

            $isHashed =
                strpos($storedPassword, '$2y$') === 0 ||
                strpos($storedPassword, '$2a$') === 0 ||
                strpos($storedPassword, '$argon2') === 0;

            if($isHashed)
            {
                $passwordOk = password_verify($password, $storedPassword);
            }
            else
            {
                $passwordOk = trim($password) === $storedPassword;
            }

            if($passwordOk)
            {
                if(isset($user['status']) && $user['status'] != 'active')
                {
                    $error = "Account Disabled";
                }
                else
                {
                    if(!$isHashed)
                    {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);

                        $update = $pdo->prepare("
                            UPDATE users
                            SET password = ?
                            WHERE id = ?
                        ");

                        $update->execute([
                            $newHash,
                            $user['id']
                        ]);
                    }

                    $reset = $pdo->prepare("
                        UPDATE users
                        SET failed_login_attempts = 0,
                            locked_until = NULL,
                            last_failed_login = NULL
                        WHERE id = ?
                    ");
                    $reset->execute([$user['id']]);

                    if($rememberId)
                    {
                        setcookie('helpdesk_remember_username', $user['username'], [
                            'expires' => time() + (86400 * 30),
                            'path' => '/',
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    }
                    else
                    {
                        setcookie('helpdesk_remember_username', '', [
                            'expires' => time() - 3600,
                            'path' => '/',
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    }

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = normalize_role($user['role'] ?? 'staff');
                    $_SESSION['ticket_scope'] = $user['ticket_scope'] ?? 'OWN';
                    $_SESSION['branch'] = $user['branch'] ?? '';
                    $_SESSION['department'] = $user['department'] ?? '';
                    $_SESSION['branch_access'] = $user['branch_access'] ?? ($user['branch'] ?? '');
                    $_SESSION['ticket_branch_access'] = $user['ticket_branch_access'] ?? '';
                    $_SESSION['ticket_pic_access'] = $user['ticket_pic_access'] ?? '';
                    $_SESSION['full_name'] = $user['full_name'] ?? '';

                    if($rememberLogin)
                    {
                        hd_issue_remember_token($pdo, $user['id']);
                    }
                    else
                    {
                        hd_clear_current_remember_token($pdo);
                    }

                    audit_log(
                        $pdo,
                        'Login',
                        'User logged in'
                    );

                    header("Location: dashboard.php");
                    exit;
                }
            }
            else
            {
                $attempts = (int)($user['failed_login_attempts'] ?? 0) + 1;

                if($attempts >= 5)
                {
                    $update = $pdo->prepare("
                        UPDATE users
                        SET failed_login_attempts = ?,
                            locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE),
                            last_failed_login = NOW()
                        WHERE id = ?
                    ");
                    $update->execute([$attempts, $user['id']]);

                    $error = "Too many failed login attempts. Account locked for 15 minutes.";
                }
                else
                {
                    $update = $pdo->prepare("
                        UPDATE users
                        SET failed_login_attempts = ?,
                            last_failed_login = NOW()
                        WHERE id = ?
                    ");
                    $update->execute([$attempts, $user['id']]);

                    $remaining = max(0, 5 - $attempts);
                    $error = "Invalid username or password. Remaining attempts: ".$remaining;
                }
            }
        }
    }
    else
    {
        // Do not reveal whether the username exists.
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WLS ENTERPRISE SDN BHD - Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>

:root{
    --primary:#0b57ff;
    --primary-dark:#063fbd;
    --navy:#071633;
    --muted:#7b86a6;
    --border:#dbe3f0;
    --soft:#f4f8ff;
}

*{
    box-sizing:border-box;
}

body{
    min-height:100vh;
    margin:0;
    background:
        radial-gradient(circle at 20% 20%, rgba(11,87,255,.10), transparent 28%),
        radial-gradient(circle at 80% 80%, rgba(11,87,255,.08), transparent 30%),
        linear-gradient(135deg,#f7fbff,#eef5ff);
    font-family:Inter,Segoe UI,Arial,sans-serif;
    color:var(--navy);
}

.login-page{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:32px;
}

.login-card{
    width:100%;
    max-width:620px;
    background:rgba(255,255,255,.96);
    border:1px solid rgba(219,227,240,.95);
    border-radius:34px;
    padding:56px;
    box-shadow:
        0 28px 80px rgba(15,23,42,.12),
        inset 0 1px 0 rgba(255,255,255,.75);
}

.security-icon{
    width:124px;
    height:124px;
    margin:0 auto 24px;
    border-radius:50%;
    background:radial-gradient(circle,#eaf3ff,#f7fbff);
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
}

.security-icon::before{
    content:"";
    position:absolute;
    width:90px;
    height:90px;
    border-radius:28px;
    background:linear-gradient(135deg,#e0f0ff,#f6fbff);
    transform:rotate(45deg);
}

.security-icon i{
    position:relative;
    z-index:2;
    width:64px;
    height:64px;
    border-radius:20px;
    background:linear-gradient(135deg,#0b57ff,#0035c9);
    color:white;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:32px;
    box-shadow:0 16px 32px rgba(11,87,255,.25);
}

.login-title{
    text-align:center;
    font-size:44px;
    line-height:1.1;
    font-weight:900;
    color:#071633;
    margin-bottom:10px;
}

.login-subtitle{
    text-align:center;
    color:var(--muted);
    font-size:20px;
    margin-bottom:38px;
}

.form-label{
    font-weight:800;
    color:#071633;
    margin-bottom:10px;
}

.input-shell{
    position:relative;
}

.input-shell i.input-icon{
    position:absolute;
    left:22px;
    top:50%;
    transform:translateY(-50%);
    font-size:25px;
    color:#071633;
    z-index:3;
}

.input-shell .form-control{
    height:64px;
    border-radius:17px;
    border:2px solid #dbe3f0;
    padding-left:66px;
    padding-right:60px;
    font-size:18px;
    color:#071633;
    box-shadow:none;
}

.input-shell .form-control:focus{
    border-color:#0b57ff;
    box-shadow:0 0 0 5px rgba(11,87,255,.10);
}

.toggle-password{
    position:absolute;
    right:16px;
    top:50%;
    transform:translateY(-50%);
    width:42px;
    height:42px;
    border:0;
    background:transparent;
    color:#071633;
    font-size:23px;
    z-index:4;
}

.login-options{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    margin:26px 0 32px;
}

.remember-box{
    display:flex;
    align-items:center;
    gap:12px;
    color:#334155;
    font-size:18px;
}

.remember-box input{
    width:24px;
    height:24px;
    accent-color:#0b57ff;
}

.forgot-link{
    color:#0b57ff;
    text-decoration:none;
    font-weight:700;
    font-size:18px;
}

.login-button{
    width:100%;
    height:66px;
    border:0;
    border-radius:16px;
    background:linear-gradient(135deg,#0b57ff,#0040df);
    color:white;
    font-size:22px;
    font-weight:900;
    box-shadow:0 18px 34px rgba(11,87,255,.30);
    transition:.2s ease;
}

.login-button:hover{
    transform:translateY(-2px);
    box-shadow:0 24px 42px rgba(11,87,255,.36);
}

.login-button .arrow{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:38px;
    height:38px;
    margin-left:12px;
    border-radius:50%;
    background:rgba(255,255,255,.20);
}

.brand-mini{
    text-align:center;
    margin-bottom:28px;
}

.brand-mini img{
    width:150px;
    max-width:100%;
    background:white;
    padding:8px;
    border-radius:16px;
    box-shadow:0 10px 24px rgba(15,23,42,.08);
}

.brand-mini .company{
    margin-top:10px;
    font-size:14px;
    letter-spacing:.08em;
    color:#0b57ff;
    font-weight:900;
}

.secure-note{
    text-align:center;
    color:#7b86a6;
    font-weight:700;
    margin-top:28px;
}

.alert{
    border-radius:16px;
    font-weight:700;
}

@media(max-width:680px){
    .login-page{
        padding:18px;
    }

    .login-card{
        padding:34px 22px;
        border-radius:26px;
    }

    .login-title{
        font-size:34px;
    }

    .login-subtitle{
        font-size:17px;
    }

    .login-options{
        flex-direction:column;
        align-items:flex-start;
    }
}

.login-lang{position:fixed;top:15px;right:15px;z-index:10;display:flex;border:1px solid #2563eb;border-radius:999px;overflow:hidden;background:#fff}.login-lang a{padding:6px 10px;font-size:12px;font-weight:800;text-decoration:none;color:#2563eb;border-right:1px solid #dbeafe}.login-lang a:last-child{border-right:0}.login-lang a.active{background:#2563eb;color:#fff}</style>
</head>

<body>
<div class="login-lang"><a class="<?= hd_lang()==='en'?'active':'' ?>" href="<?= hd_lang_url('en') ?>">English</a><a class="<?= hd_lang()==='ms'?'active':'' ?>" href="<?= hd_lang_url('ms') ?>">Bahasa Melayu</a><a class="<?= hd_lang()==='zh'?'active':'' ?>" href="<?= hd_lang_url('zh') ?>">中文</a></div>

<div class="login-page">

    <div class="login-card">

        <div class="brand-mini">
            <img src="assets/logo.png" alt="WLS Logo">
            <div class="company">
                WLS ENTERPRISE SDN BHD
            </div>
        </div>

        <div class="security-icon">
            <i class="bi bi-lock-fill"></i>
        </div>

        <h1 class="login-title">
            Welcome Back!
        </h1>

        <div class="login-subtitle">
            Login to continue to your account
        </div>

        <?php if(isset($error)): ?>
        <div class="alert alert-danger mb-4">
            <?= htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">

            <div class="mb-4">
                <label class="form-label">
                    Username
                </label>

                <div class="input-shell">
                    <i class="bi bi-person input-icon"></i>

                    <input
                    type="text"
                    name="username"
                    class="form-control"
                    placeholder="Enter your username"
                    value="<?= htmlspecialchars($_POST['username'] ?? $remembered_username ?? ''); ?>"
                    required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">
                    Password
                </label>

                <div class="input-shell">
                    <i class="bi bi-lock input-icon"></i>

                    <input
                    type="password"
                    name="password"
                    id="loginPassword"
                    class="form-control"
                    placeholder="Enter your password"
                    required>

                    <button
                    type="button"
                    class="toggle-password"
                    onclick="toggleLoginPassword()">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="login-options">

                <div>
                    <label class="remember-box mb-2">
                        <input type="checkbox" name="remember_id" <?= !empty($_POST['remember_id']) || (!isset($_POST['username']) && !empty($remembered_username)) ? 'checked' : ''; ?>>
                        Remember ID
                    </label>
                    <label class="remember-box">
                        <input type="checkbox" name="remember_me" <?= !isset($_POST['username']) || !empty($_POST['remember_me']) ? 'checked' : ''; ?>>
                        Keep me logged in for 30 days
                    </label>
                </div>

                <a href="#" class="forgot-link">
                    Forgot password?
                </a>

            </div>

            <button type="submit" class="login-button">
                Login
                <span class="arrow">
                    <i class="bi bi-arrow-right"></i>
                </span>
            </button>

        </form>

        <div class="secure-note">
            <i class="bi bi-shield-check me-2"></i>
            5 failed attempts will lock the account for 15 minutes
        </div>

    </div>

</div>

<script>
function toggleLoginPassword()
{
    const passwordInput = document.getElementById('loginPassword');
    const icon = document.querySelector('.toggle-password i');

    if(passwordInput.type === 'password')
    {
        passwordInput.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    }
    else
    {
        passwordInput.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}
</script>

</body>
</html>

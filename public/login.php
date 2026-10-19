<?php
/**
 * Halaman bantu: simulasi sesi login untuk pengembangan lokal.
 * Pada produksi, login ditangani SSO Portal (lju.co.id) via cookie LJU_APPS.
 */
require_once dirname(__DIR__) . '/config/config.php';

ini_set('session.cookie_domain', '.lju.co.id');
session_name('LJU_APPS');
session_set_cookie_params([
    'lifetime' => 86400, 'path' => '/', 'domain' => '.lju.co.id',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true, 'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) session_start();

// Sudah login? langsung ke dashboard
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    try {
        $stmt = ssoDb()->prepare("SELECT * FROM users WHERE (username=? OR email=?) AND is_active=1 LIMIT 1");
        $stmt->execute([$u, $u]);
        $row = $stmt->fetch();
        if ($row && password_verify($p, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id']; // bootstrap akan sync lengkap dari users
            header('Location: dashboard.php'); exit;
        }
        $error = 'Username/password salah atau akun nonaktif.';
    } catch (Throwable $ex) {
        $error = 'Database users tidak dapat diakses. Pastikan konfigurasi SSO_DB_NAME benar.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-center">
  <div class="auth-card">
    <img src="<?= e(LOGO_BASE_URL) ?>logo.png" alt="Logo LJU" onerror="this.style.display='none'">
    <h2 style="color:var(--navy)"><?= e(APP_NAME) ?></h2>
    <p style="color:var(--muted);font-size:13px;margin:6px 0 18px"><?= e(APP_COMPANY) ?></p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-group"><label>Username / Email</label><input type="text" name="username" required autofocus></div>
      <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
      <button class="btn btn-primary" style="width:100%;justify-content:center">Masuk</button>
    </form>
    <p style="margin-top:16px;font-size:12px;color:var(--muted)">
      Belum punya akun? <a href="https://lju.co.id/dashboard/?target_system=<?= urlencode(APP_URL) ?>">Login via SSO Portal LJU</a>
    </p>
  </div>
</div>
</body>
</html>

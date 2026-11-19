<?php

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

$error = '';
$success = '';
$configFile = __DIR__ . '/../includes/config.local.php';
$config = is_file($configFile) ? require $configFile : [];
$setupKey = (string) ($config['ADMIN_SETUP_KEY'] ?? '');

if ($setupKey === '' || strlen($setupKey) < 24) {
    $error = 'Set a long, private ADMIN_SETUP_KEY in includes/config.local.php before creating the administrator.';
} else {
    $count = $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ((int) $count > 0) {
        $error = 'Administrator setup is disabled because an administrator account already exists.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
            $error = 'Your form session has expired. Please try again.';
        } else {
            $providedKey = (string) ($_POST['setup_key'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if (!hash_equals($setupKey, $providedKey)) {
                $error = 'The setup key is incorrect.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a valid administrator email address.';
            } elseif (strlen($password) < 12) {
                $error = 'Use an administrator password of at least 12 characters.';
            } else {
                $statement = $pdo->prepare('INSERT INTO admin_users (email, password_hash) VALUES (?, ?)');
                $statement->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
                $success = 'Administrator account created. Delete admin/setup.php and remove ADMIN_SETUP_KEY after signing in.';
            }
        }
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Setup</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
<main class="admin-login-wrap">
    <form class="admin-login-card" method="post" action="setup.php">
        <h1>Administrator Setup</h1>
        <?php if ($error !== ''): ?><p class="admin-error"><?php echo escape_html($error); ?></p><?php endif; ?>
        <?php if ($success !== ''): ?><p class="admin-success"><?php echo escape_html($success); ?></p><?php endif; ?>
        <?php if ($success === '' && $error !== 'Administrator setup is disabled because an administrator account already exists.' && strlen($setupKey) >= 24): ?>
            <input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>">
            <label>Setup key
                <input type="password" name="setup_key" required autocomplete="off">
            </label>
            <label>Email
                <input type="email" name="email" maxlength="190" required autocomplete="username">
            </label>
            <label>Password
                <input type="password" name="password" minlength="12" maxlength="255" required autocomplete="new-password">
            </label>
            <button type="submit" class="admin-button">Create administrator</button>
        <?php endif; ?>
    </form>
</main>
</body>
</html>

<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();

$error = '';
$success = '';

function upload_site_logo(): string
{
    if (empty($_FILES['logo']['name']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The logo could not be uploaded.');
    }
    if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('The logo must be 2 MB or smaller.');
    }

    $imageInfo = getimagesize($_FILES['logo']['tmp_name']);
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mimeType = $imageInfo['mime'] ?? '';
    if ($imageInfo === false || !isset($allowedTypes[$mimeType])) {
        throw new RuntimeException('Use JPG, PNG, or WEBP logo images.');
    }

    $directory = __DIR__ . '/../uploads/profile';
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('The upload directory could not be created.');
    }
    $fileName = 'logo-' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $directory . '/' . $fileName)) {
        throw new RuntimeException('The logo could not be saved.');
    }
    return 'uploads/profile/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session has expired. Please try again.';
    } else {
        try {
            $siteTitle = trim($_POST['site_title'] ?? '');
            $accentColor = trim($_POST['accent_color'] ?? '');
            if ($siteTitle === '' || strlen($siteTitle) > 150) {
                throw new RuntimeException('Enter a valid website title.');
            }
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accentColor)) {
                throw new RuntimeException('Enter a valid six-digit hex accent color.');
            }

            $logoPath = upload_site_logo();
            $values = [
                'site_title' => $siteTitle,
                'accent_color' => strtolower($accentColor),
            ];
            if ($logoPath !== '') {
                $values['site_logo'] = $logoPath;
            }

            $statement = $pdo->prepare(
                'INSERT INTO website_settings (setting_key, setting_value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            foreach ($values as $key => $value) {
                $statement->execute([$key, $value]);
            }
            $success = 'Website settings updated.';
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}

$keys = ['site_title', 'accent_color', 'site_logo'];
$settings = [];
foreach ($keys as $key) {
    $settings[$key] = get_setting($pdo, $key, $key === 'accent_color' ? '#ff7a00' : '');
}
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Settings</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
<header class="admin-header">
    <a class="admin-brand" href="dashboard.php">Portfolio Admin</a>
    <div class="admin-account"><a href="dashboard.php">Dashboard</a></div>
</header>
<main class="admin-main">
    <div class="admin-page-title"><div><p class="admin-eyebrow">Appearance</p><h1>Website Settings</h1></div></div>
    <?php if ($error !== ''): ?><p class="admin-error"><?php echo escape_html($error); ?></p><?php endif; ?>
    <?php if ($success !== ''): ?><p class="admin-success"><?php echo escape_html($success); ?></p><?php endif; ?>
    <section class="admin-form-card">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>">
            <div class="admin-form-grid">
                <label>Website title
                    <input type="text" name="site_title" maxlength="150" required value="<?php echo escape_html($settings['site_title']); ?>">
                </label>
                <label>Accent color
                    <input type="color" name="accent_color" value="<?php echo escape_html($settings['accent_color']); ?>">
                </label>
                <label>Logo image
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
                </label>
            </div>
            <?php if ($settings['site_logo'] !== ''): ?>
                <p>Current logo: <?php echo escape_html($settings['site_logo']); ?></p>
            <?php endif; ?>
            <button type="submit" class="admin-button">Save settings</button>
        </form>
    </section>
</main>
</body>
</html>

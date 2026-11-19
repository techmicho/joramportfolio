<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session has expired. Please try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $headline = trim($_POST['headline'] ?? '');
        $introduction = trim($_POST['introduction'] ?? '');
        $biography = trim($_POST['biography'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $location = trim($_POST['location'] ?? '');

        try {
            if ($fullName === '' || strlen($fullName) > 150) {
                throw new RuntimeException('Enter a valid full name.');
            }
            if ($headline === '' || strlen($headline) > 200) {
                throw new RuntimeException('Enter a valid headline.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid email address.');
            }

            $profileImage = '';
            if (!empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('The profile image could not be uploaded.');
                }
                if ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
                    throw new RuntimeException('The profile image must be 2 MB or smaller.');
                }

                $imageInfo = getimagesize($_FILES['profile_image']['tmp_name']);
                $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $mimeType = $imageInfo['mime'] ?? '';
                if ($imageInfo === false || !isset($allowedTypes[$mimeType])) {
                    throw new RuntimeException('Use JPG, PNG, or WEBP images.');
                }

                $directory = __DIR__ . '/../uploads/profile';
                if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                    throw new RuntimeException('The upload directory could not be created.');
                }

                $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
                if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $directory . '/' . $fileName)) {
                    throw new RuntimeException('The profile image could not be saved.');
                }
                $profileImage = 'uploads/profile/' . $fileName;
            }

            if ($profileImage !== '') {
                $statement = $pdo->prepare('UPDATE profile SET full_name = ?, headline = ?, introduction = ?, biography = ?, email = ?, phone = ?, location = ?, profile_image = ? WHERE id = 1');
                $statement->execute([$fullName, $headline, $introduction, $biography, $email, $phone, $location, $profileImage]);
            } else {
                $statement = $pdo->prepare('UPDATE profile SET full_name = ?, headline = ?, introduction = ?, biography = ?, email = ?, phone = ?, location = ? WHERE id = 1');
                $statement->execute([$fullName, $headline, $introduction, $biography, $email, $phone, $location]);
            }

            $success = 'Profile updated.';
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}

$statement = $pdo->prepare('SELECT * FROM profile WHERE id = 1');
$statement->execute();
$profile = $statement->fetch();
if (!$profile) {
    $profile = ['full_name' => '', 'headline' => '', 'introduction' => '', 'biography' => '', 'email' => '', 'phone' => '', 'location' => '', 'profile_image' => ''];
}
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profile</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
<header class="admin-header">
    <a class="admin-brand" href="dashboard.php">Portfolio Admin</a>
    <div class="admin-account"><a href="dashboard.php">Dashboard</a></div>
</header>
<main class="admin-main">
    <div class="admin-page-title">
        <div><p class="admin-eyebrow">Content</p><h1>Profile</h1></div>
    </div>

    <?php if ($error !== ''): ?><p class="admin-error"><?php echo escape_html($error); ?></p><?php endif; ?>
    <?php if ($success !== ''): ?><p class="admin-success"><?php echo escape_html($success); ?></p><?php endif; ?>

    <section class="admin-form-card">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>">
            <div class="admin-form-grid">
                <label>Full name
                    <input type="text" name="full_name" maxlength="150" required value="<?php echo escape_html($profile['full_name']); ?>">
                </label>
                <label>Headline
                    <input type="text" name="headline" maxlength="200" required value="<?php echo escape_html($profile['headline']); ?>">
                </label>
                <label>Email
                    <input type="email" name="email" maxlength="190" value="<?php echo escape_html($profile['email']); ?>">
                </label>
                <label>Phone
                    <input type="text" name="phone" maxlength="50" value="<?php echo escape_html($profile['phone']); ?>">
                </label>
                <label>Location
                    <input type="text" name="location" maxlength="150" value="<?php echo escape_html($profile['location']); ?>">
                </label>
                <label>Profile image
                    <input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
                </label>
            </div>
            <label>Introduction
                <textarea name="introduction" rows="4" required><?php echo escape_html($profile['introduction']); ?></textarea>
            </label>
            <label>Biography
                <textarea name="biography" rows="6" required><?php echo escape_html($profile['biography']); ?></textarea>
            </label>
            <?php if (!empty($profile['profile_image'])): ?>
                <p>Current image: <?php echo escape_html($profile['profile_image']); ?></p>
            <?php endif; ?>
            <button type="submit" class="admin-button">Save profile</button>
        </form>
    </section>
</main>
</body>
</html>

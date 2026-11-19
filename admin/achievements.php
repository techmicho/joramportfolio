<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();

$error = '';
$success = '';
$editing = null;

function upload_achievement_image(): string
{
    if (empty($_FILES['image']['name']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) return '';
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('The image could not be uploaded.');
    if ($_FILES['image']['size'] > 5 * 1024 * 1024) throw new RuntimeException('The file must be 5 MB or smaller.');

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['image']['tmp_name']) ?: '';
    }

    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $types['application/pdf'] = 'pdf';
    if ($mime === 'application/pdf') {
        $handle = fopen($_FILES['image']['tmp_name'], 'rb');
        $header = $handle ? fread($handle, 5) : '';
        if ($handle) fclose($handle);
        if ($header !== '%PDF-') throw new RuntimeException('Only valid PDF or image files are allowed.');
    } else {
        $info = getimagesize($_FILES['image']['tmp_name']);
        if ($info === false) throw new RuntimeException('Only valid PDF or image files are allowed.');
        $mime = $info['mime'] ?? '';
    }
    if (!isset($types[$mime])) throw new RuntimeException('Use JPG, PNG, WEBP, GIF, or PDF files.');
    $directory = __DIR__ . '/../uploads/achievements';
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new RuntimeException('The upload directory could not be created.');
    $fileName = bin2hex(random_bytes(16)) . '.' . $types[$mime];
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $directory . '/' . $fileName)) throw new RuntimeException('The image could not be saved.');
    return 'uploads/achievements/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) { $error = 'Your form session has expired. Please try again.'; }
    else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'save') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $title = trim($_POST['title'] ?? '');
                $organization = trim($_POST['organization'] ?? '');
                $details = trim($_POST['details'] ?? '');
                $achievementDate = trim($_POST['achievement_date'] ?? '') ?: null;
                $sortOrder = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT) ?: 0;
                if ($title === '') throw new RuntimeException('Title is required.');
                $imagePath = upload_achievement_image();

                if ($id) {
                    if ($imagePath !== '') { $statement = $pdo->prepare('UPDATE achievements SET title=?, organization=?, details=?, image_path=?, achievement_date=?, sort_order=? WHERE id=?'); $statement->execute([$title, $organization, $details, $imagePath, $achievementDate, $sortOrder, $id]); }
                    else { $statement = $pdo->prepare('UPDATE achievements SET title=?, organization=?, details=?, achievement_date=?, sort_order=? WHERE id=?'); $statement->execute([$title, $organization, $details, $achievementDate, $sortOrder, $id]); }
                    $success = 'Achievement updated.';
                } else {
                    $statement = $pdo->prepare('INSERT INTO achievements (title, organization, details, image_path, achievement_date, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
                    $statement->execute([$title, $organization, $details, $imagePath, $achievementDate, $sortOrder]);
                    $success = 'Achievement added.';
                }
            } elseif ($action === 'delete') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if ($id) {
                    $statement = $pdo->prepare('SELECT image_path FROM achievements WHERE id=?'); $statement->execute([$id]); $row = $statement->fetch();
                    $delete = $pdo->prepare('DELETE FROM achievements WHERE id=?'); $delete->execute([$id]);
                    if ($row && str_starts_with($row['image_path'], 'uploads/achievements/')) { $file = __DIR__ . '/../' . $row['image_path']; if (is_file($file)) unlink($file); }
                    $success = 'Achievement deleted.';
                }
            }
        } catch (RuntimeException $exception) { $error = $exception->getMessage(); }
    }
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) { $statement = $pdo->prepare('SELECT * FROM achievements WHERE id=?'); $statement->execute([$editId]); $editing = $statement->fetch(); }
$rows = $pdo->query('SELECT * FROM achievements ORDER BY sort_order ASC, id DESC')->fetchAll();
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Manage Achievements</title><link rel="stylesheet" href="../css/style.css"><link rel="stylesheet" href="admin.css"></head>
<body class="admin-body"><header class="admin-header"><a class="admin-brand" href="dashboard.php">Portfolio Admin</a><div class="admin-account"><a href="dashboard.php">Dashboard</a></div></header>
<main class="admin-main"><div class="admin-page-title"><div><p class="admin-eyebrow">Resume</p><h1>Achievements</h1></div></div>
<?php if ($error): ?><p class="admin-error"><?php echo escape_html($error); ?></p><?php endif; ?><?php if ($success): ?><p class="admin-success"><?php echo escape_html($success); ?></p><?php endif; ?>
<section class="admin-form-card"><h2><?php echo $editing ? 'Edit achievement' : 'Add achievement'; ?></h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo $editing ? (int) $editing['id'] : ''; ?>">
<div class="admin-form-grid"><label>Title<input type="text" name="title" maxlength="180" required value="<?php echo escape_html($editing['title'] ?? ''); ?>"></label><label>Organization<input type="text" name="organization" maxlength="180" value="<?php echo escape_html($editing['organization'] ?? ''); ?>"></label><label>Achievement date<input type="date" name="achievement_date" value="<?php echo escape_html($editing['achievement_date'] ?? ''); ?>"></label><label>Sort order<input type="number" name="sort_order" value="<?php echo (int) ($editing['sort_order'] ?? 0); ?>"></label></div>
<label>Details<textarea name="details" maxlength="5000" rows="5"><?php echo escape_html($editing['details'] ?? ''); ?></textarea></label><label>Certificate or achievement file<input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf"></label><button type="submit" class="admin-button"><?php echo $editing ? 'Update achievement' : 'Add achievement'; ?></button><?php if ($editing): ?> <a class="admin-button admin-button-light" href="achievements.php">Cancel</a><?php endif; ?></form></section>
<section class="admin-table-card"><h2>Existing achievements</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Title</th><th>Organization</th><th>Actions</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?php echo escape_html($row['title']); ?></td><td><?php echo escape_html($row['organization']); ?></td><td><a class="admin-button admin-button-light" href="achievements.php?edit=<?php echo (int) $row['id']; ?>">Edit</a><form method="post" style="display:inline" onsubmit="return confirm('Delete this achievement?');"><input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>"><button type="submit" class="admin-danger-button">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
</main></body></html>

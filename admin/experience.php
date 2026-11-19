<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();

$error = '';
$success = '';
$editing = null;

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
                $startDate = trim($_POST['start_date'] ?? '') ?: null;
                $endDate = trim($_POST['end_date'] ?? '') ?: null;
                $sortOrder = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT) ?: 0;
                if ($title === '') throw new RuntimeException('Title is required.');
                if ($startDate && $endDate && $endDate < $startDate) throw new RuntimeException('End date cannot be before start date.');

                if ($id) { $statement = $pdo->prepare('UPDATE experience SET title=?, organization=?, details=?, start_date=?, end_date=?, sort_order=? WHERE id=?'); $statement->execute([$title, $organization, $details, $startDate, $endDate, $sortOrder, $id]); $success = 'Experience updated.'; }
                else { $statement = $pdo->prepare('INSERT INTO experience (title, organization, details, start_date, end_date, sort_order) VALUES (?, ?, ?, ?, ?, ?)'); $statement->execute([$title, $organization, $details, $startDate, $endDate, $sortOrder]); $success = 'Experience added.'; }
            } elseif ($action === 'delete') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if ($id) { $statement = $pdo->prepare('DELETE FROM experience WHERE id=?'); $statement->execute([$id]); $success = 'Experience deleted.'; }
            }
        } catch (RuntimeException $exception) { $error = $exception->getMessage(); }
    }
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) { $statement = $pdo->prepare('SELECT * FROM experience WHERE id=?'); $statement->execute([$editId]); $editing = $statement->fetch(); }
$rows = $pdo->query('SELECT * FROM experience ORDER BY sort_order ASC, id DESC')->fetchAll();
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Manage Experience</title><link rel="stylesheet" href="../css/style.css"><link rel="stylesheet" href="admin.css"></head>
<body class="admin-body"><header class="admin-header"><a class="admin-brand" href="dashboard.php">Portfolio Admin</a><div class="admin-account"><a href="dashboard.php">Dashboard</a></div></header>
<main class="admin-main"><div class="admin-page-title"><div><p class="admin-eyebrow">Resume</p><h1>Experience</h1></div></div>
<?php if ($error): ?><p class="admin-error"><?php echo escape_html($error); ?></p><?php endif; ?><?php if ($success): ?><p class="admin-success"><?php echo escape_html($success); ?></p><?php endif; ?>
<section class="admin-form-card"><h2><?php echo $editing ? 'Edit experience' : 'Add experience'; ?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo $editing ? (int) $editing['id'] : ''; ?>">
<div class="admin-form-grid"><label>Title<input type="text" name="title" maxlength="180" required value="<?php echo escape_html($editing['title'] ?? ''); ?>"></label><label>Organization<input type="text" name="organization" maxlength="180" value="<?php echo escape_html($editing['organization'] ?? ''); ?>"></label><label>Start date<input type="date" name="start_date" value="<?php echo escape_html($editing['start_date'] ?? ''); ?>"></label><label>End date<input type="date" name="end_date" value="<?php echo escape_html($editing['end_date'] ?? ''); ?>"></label><label>Sort order<input type="number" name="sort_order" value="<?php echo (int) ($editing['sort_order'] ?? 0); ?>"></label></div>
<label>Details<textarea name="details" maxlength="5000" rows="5"><?php echo escape_html($editing['details'] ?? ''); ?></textarea></label><button type="submit" class="admin-button"><?php echo $editing ? 'Update experience' : 'Add experience'; ?></button><?php if ($editing): ?> <a class="admin-button admin-button-light" href="experience.php">Cancel</a><?php endif; ?></form></section>
<section class="admin-table-card"><h2>Existing experience</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Title</th><th>Organization</th><th>Actions</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?php echo escape_html($row['title']); ?></td><td><?php echo escape_html($row['organization']); ?></td><td><a class="admin-button admin-button-light" href="experience.php?edit=<?php echo (int) $row['id']; ?>">Edit</a><form method="post" style="display:inline" onsubmit="return confirm('Delete this experience entry?');"><input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>"><button type="submit" class="admin-danger-button">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
</main></body></html>

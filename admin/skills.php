<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$error = '';
$success = '';
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session has expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'save') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $name = trim($_POST['name'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $levelLabel = trim($_POST['level_label'] ?? '');
                $levelPercent = filter_input(INPUT_POST, 'level_percent', FILTER_VALIDATE_INT) ?: 0;
                $sortOrder = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT) ?: 0;

                if ($name === '' || strlen($name) > 120) {
                    throw new RuntimeException('Enter a valid skill name.');
                }
                if ($levelPercent < 0 || $levelPercent > 100) {
                    throw new RuntimeException('Skill level must be between 0 and 100.');
                }

                if ($id) {
                    $statement = $pdo->prepare('UPDATE skills SET name = ?, category = ?, description = ?, level_label = ?, level_percent = ?, sort_order = ? WHERE id = ?');
                    $statement->execute([$name, $category, $description, $levelLabel, $levelPercent, $sortOrder, $id]);
                    $success = 'Skill updated.';
                } else {
                    $statement = $pdo->prepare('INSERT INTO skills (name, category, description, level_label, level_percent, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
                    $statement->execute([$name, $category, $description, $levelLabel, $levelPercent, $sortOrder]);
                    $success = 'Skill added.';
                }
            }

            if ($action === 'delete') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if ($id) {
                    $statement = $pdo->prepare('DELETE FROM skills WHERE id = ?');
                    $statement->execute([$id]);
                    $success = 'Skill deleted.';
                }
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) {
    $statement = $pdo->prepare('SELECT * FROM skills WHERE id = ?');
    $statement->execute([$editId]);
    $editing = $statement->fetch();
}

$skills = $pdo->query('SELECT * FROM skills ORDER BY sort_order ASC, id DESC')->fetchAll();
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Skills</title>
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
        <div><p class="admin-eyebrow">Content</p><h1>Skills</h1></div>
    </div>

    <?php if ($error !== ''): ?><p class="admin-error"><?php echo escape_html($error); ?></p><?php endif; ?>
    <?php if ($success !== ''): ?><p class="admin-success"><?php echo escape_html($success); ?></p><?php endif; ?>

    <section class="admin-form-card">
        <h2><?php echo $editing ? 'Edit skill' : 'Add skill'; ?></h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $editing ? (int) $editing['id'] : ''; ?>">
            <div class="admin-form-grid">
                <label>Skill name
                    <input type="text" name="name" maxlength="120" required value="<?php echo escape_html($editing['name'] ?? ''); ?>">
                </label>
                <label>Category
                    <input type="text" name="category" maxlength="120" value="<?php echo escape_html($editing['category'] ?? ''); ?>">
                </label>
                <label>Level label
                    <input type="text" name="level_label" maxlength="50" placeholder="Intermediate" value="<?php echo escape_html($editing['level_label'] ?? ''); ?>">
                </label>
                <label>Level percentage
                    <input type="number" name="level_percent" min="0" max="100" value="<?php echo (int) ($editing['level_percent'] ?? 50); ?>">
                </label>
                <label>Sort order
                    <input type="number" name="sort_order" value="<?php echo (int) ($editing['sort_order'] ?? 0); ?>">
                </label>
            </div>
            <label>Description
                <textarea name="description" maxlength="3000" rows="4"><?php echo escape_html($editing['description'] ?? ''); ?></textarea>
            </label>
            <button type="submit" class="admin-button"><?php echo $editing ? 'Update skill' : 'Add skill'; ?></button>
            <?php if ($editing): ?> <a class="admin-button admin-button-light" href="skills.php">Cancel</a><?php endif; ?>
        </form>
    </section>

    <section class="admin-table-card">
        <h2>Existing skills</h2>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Category</th><th>Level</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($skills as $skill): ?>
                    <tr>
                        <td><?php echo escape_html($skill['name']); ?></td>
                        <td><?php echo escape_html($skill['category']); ?></td>
                        <td><?php echo escape_html($skill['level_label']); ?> (<?php echo (int) $skill['level_percent']; ?>%)</td>
                        <td>
                            <a class="admin-button admin-button-light" href="skills.php?edit=<?php echo (int) $skill['id']; ?>">Edit</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this skill?');">
                                <input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $skill['id']; ?>">
                                <button type="submit" class="admin-danger-button">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>

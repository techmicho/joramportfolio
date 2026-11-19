<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$error = '';
$success = '';
$editing = null;

function upload_project_image(): string
{
    if (empty($_FILES['image']['name']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image could not be uploaded.');
    }

    if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('The image must be 2 MB or smaller.');
    }

    $imageInfo = getimagesize($_FILES['image']['tmp_name']);
    if ($imageInfo === false) {
        throw new RuntimeException('Only valid image files are allowed.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $mimeType = $imageInfo['mime'] ?? '';
    if (!isset($allowedTypes[$mimeType])) {
        throw new RuntimeException('Use JPG, PNG, WEBP, or GIF images.');
    }

    $uploadDirectory = __DIR__ . '/../uploads/projects';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
        throw new RuntimeException('The upload directory could not be created.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    $destination = $uploadDirectory . '/' . $fileName;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
        throw new RuntimeException('The image could not be saved.');
    }

    return 'uploads/projects/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session has expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'save') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $technologies = trim($_POST['technologies'] ?? '');
                $projectUrl = trim($_POST['project_url'] ?? '');
                $sortOrder = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT) ?: 0;
                $isVisible = isset($_POST['is_visible']) ? 1 : 0;

                if ($title === '' || $description === '' || strlen($title) > 180) {
                    throw new RuntimeException('Enter a title and description.');
                }

                if ($projectUrl !== '' && !filter_var($projectUrl, FILTER_VALIDATE_URL)) {
                    throw new RuntimeException('Enter a valid project URL.');
                }

                $imagePath = upload_project_image();

                if ($id) {
                    if ($imagePath !== '') {
                        $statement = $pdo->prepare('UPDATE projects SET title = ?, description = ?, technologies = ?, image_path = ?, project_url = ?, is_visible = ?, sort_order = ? WHERE id = ?');
                        $statement->execute([$title, $description, $technologies, $imagePath, $projectUrl, $isVisible, $sortOrder, $id]);
                    } else {
                        $statement = $pdo->prepare('UPDATE projects SET title = ?, description = ?, technologies = ?, project_url = ?, is_visible = ?, sort_order = ? WHERE id = ?');
                        $statement->execute([$title, $description, $technologies, $projectUrl, $isVisible, $sortOrder, $id]);
                    }
                    $success = 'Project updated.';
                } else {
                    $statement = $pdo->prepare('INSERT INTO projects (title, description, technologies, image_path, project_url, is_visible, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $statement->execute([$title, $description, $technologies, $imagePath, $projectUrl, $isVisible, $sortOrder]);
                    $success = 'Project added.';
                }
            }

            if ($action === 'delete') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if ($id) {
                    $statement = $pdo->prepare('SELECT image_path FROM projects WHERE id = ?');
                    $statement->execute([$id]);
                    $project = $statement->fetch();

                    $delete = $pdo->prepare('DELETE FROM projects WHERE id = ?');
                    $delete->execute([$id]);

                    if ($project && str_starts_with($project['image_path'], 'uploads/projects/') ) {
                        $filePath = __DIR__ . '/../' . $project['image_path'];
                        if (is_file($filePath)) {
                            unlink($filePath);
                        }
                    }
                    $success = 'Project deleted.';
                }
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) {
    $statement = $pdo->prepare('SELECT * FROM projects WHERE id = ?');
    $statement->execute([$editId]);
    $editing = $statement->fetch();
}

$projects = $pdo->query('SELECT * FROM projects ORDER BY sort_order ASC, id DESC')->fetchAll();
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Projects</title>
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
        <div>
            <p class="admin-eyebrow">Content</p>
            <h1>Projects</h1>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <p class="admin-error"><?php echo escape_html($error); ?></p>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <p class="admin-success"><?php echo escape_html($success); ?></p>
    <?php endif; ?>

    <section class="admin-form-card">
        <h2><?php echo $editing ? 'Edit project' : 'Add project'; ?></h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $editing ? (int) $editing['id'] : ''; ?>">
            <div class="admin-form-grid">
                <label>Title
                    <input type="text" name="title" maxlength="180" required value="<?php echo escape_html($editing['title'] ?? ''); ?>">
                </label>
                <label>Project URL
                    <input type="url" name="project_url" maxlength="500" value="<?php echo escape_html($editing['project_url'] ?? ''); ?>">
                </label>
                <label>Technologies
                    <input type="text" name="technologies" maxlength="500" placeholder="HTML, CSS, PHP" value="<?php echo escape_html($editing['technologies'] ?? ''); ?>">
                </label>
                <label>Sort order
                    <input type="number" name="sort_order" value="<?php echo (int) ($editing['sort_order'] ?? 0); ?>">
                </label>
            </div>
            <label>Description
                <textarea name="description" maxlength="5000" rows="5" required><?php echo escape_html($editing['description'] ?? ''); ?></textarea>
            </label>
            <label>Project image
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
            </label>
            <label class="admin-checkbox">
                <input type="checkbox" name="is_visible" <?php echo $editing ? ($editing['is_visible'] ? 'checked' : '') : 'checked'; ?>>
                Visible on the website
            </label>
            <button type="submit" class="admin-button"><?php echo $editing ? 'Update project' : 'Add project'; ?></button>
            <?php if ($editing): ?> <a class="admin-button admin-button-light" href="projects.php">Cancel</a><?php endif; ?>
        </form>
    </section>

    <section class="admin-table-card">
        <h2>Existing projects</h2>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Title</th><th>Technologies</th><th>Visible</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><?php echo escape_html($project['title']); ?></td>
                        <td><?php echo escape_html($project['technologies']); ?></td>
                        <td><?php echo $project['is_visible'] ? 'Yes' : 'No'; ?></td>
                        <td>
                            <a class="admin-button admin-button-light" href="projects.php?edit=<?php echo (int) $project['id']; ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this project?');">
                                <input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $project['id']; ?>">
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

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
        $action = $_POST['action'] ?? '';
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($id) {
            if ($action === 'mark_read') {
                $statement = $pdo->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?');
                $statement->execute([$id]);
                $success = 'Message marked as read.';
            } elseif ($action === 'mark_unread') {
                $statement = $pdo->prepare('UPDATE contact_messages SET is_read = 0 WHERE id = ?');
                $statement->execute([$id]);
                $success = 'Message marked as unread.';
            } elseif ($action === 'delete') {
                $statement = $pdo->prepare('DELETE FROM contact_messages WHERE id = ?');
                $statement->execute([$id]);
                $success = 'Message deleted.';
            }
        }
    }
}

$messages = $pdo->query('SELECT * FROM contact_messages ORDER BY created_at DESC, id DESC')->fetchAll();
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages</title>
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
        <div><p class="admin-eyebrow">Inbox</p><h1>Contact Messages</h1></div>
    </div>
    <?php if ($error !== ''): ?><p class="admin-error"><?php echo escape_html($error); ?></p><?php endif; ?>
    <?php if ($success !== ''): ?><p class="admin-success"><?php echo escape_html($success); ?></p><?php endif; ?>
    <section class="admin-table-card">
        <?php if (count($messages) === 0): ?>
            <p>No contact messages yet.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Status</th><th>Name</th><th>Email</th><th>Subject</th><th>Received</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($messages as $message): ?>
                        <tr>
                            <td><?php echo $message['is_read'] ? 'Read' : 'Unread'; ?></td>
                            <td><?php echo escape_html($message['name']); ?></td>
                            <td><a href="mailto:<?php echo escape_html($message['email']); ?>"><?php echo escape_html($message['email']); ?></a></td>
                            <td><?php echo escape_html($message['subject']); ?></td>
                            <td><?php echo escape_html($message['created_at']); ?></td>
                            <td>
                                <details>
                                    <summary>View message</summary>
                                    <p><?php echo nl2br(escape_html($message['message'])); ?></p>
                                </details>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int) $message['id']; ?>">
                                    <input type="hidden" name="action" value="<?php echo $message['is_read'] ? 'mark_unread' : 'mark_read'; ?>">
                                    <button type="submit" class="admin-button admin-button-light"><?php echo $message['is_read'] ? 'Unread' : 'Read'; ?></button>
                                </form>
                                <form method="post" style="display:inline" onsubmit="return confirm('Delete this message?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int) $message['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="admin-danger-button">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>

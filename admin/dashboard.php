<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();

$counts = [
    'Projects' => (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
    'Skills' => (int) $pdo->query('SELECT COUNT(*) FROM skills')->fetchColumn(),
    'Messages' => (int) $pdo->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn(),
    'Education' => (int) $pdo->query('SELECT COUNT(*) FROM education')->fetchColumn(),
    'Experience' => (int) $pdo->query('SELECT COUNT(*) FROM experience')->fetchColumn(),
    'Achievements' => (int) $pdo->query('SELECT COUNT(*) FROM achievements')->fetchColumn(),
];
$unreadMessages = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
$adminEmail = escape_html($_SESSION['admin_email'] ?? 'Administrator');
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="admin.css?v=20261118-3">
</head>
<body class="admin-body">
<header class="admin-header">
    <a class="admin-brand" href="dashboard.php">Portfolio Admin</a>
    <div class="admin-account">
        <span><?php echo $adminEmail; ?></span>
        <form method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>">
            <button type="submit" class="admin-button admin-button-light">Log out</button>
        </form>
    </div>
</header>
<main class="admin-main">
    <div class="admin-page-title">
        <div>
            <p class="admin-eyebrow">Portfolio workspace</p>
            <h1>Dashboard</h1>
            <p>Manage your portfolio content and keep your public profile up to date.</p>
        </div>
        <a class="admin-button" href="../index.php" target="_blank" rel="noopener noreferrer">View live website <span aria-hidden="true">↗</span></a>
    </div>
    <section class="admin-stats" aria-label="Portfolio content overview">
        <?php foreach ($counts as $label => $count): ?>
            <article class="admin-stat-card"><p><?php echo escape_html($label); ?></p><strong><?php echo $count; ?></strong></article>
        <?php endforeach; ?>
    </section>
    <section class="admin-notice" aria-label="Inbox status">
        <div><h2>Inbox</h2><p><?php echo $unreadMessages; ?> unread message<?php echo $unreadMessages === 1 ? '' : 's'; ?> waiting for review.</p></div>
        <a class="admin-button admin-button-light" href="messages.php">Open messages</a>
    </section>
    <nav class="admin-shortcuts" aria-label="Content management">
        <h2>Manage content</h2>
        <p>Choose a section to create, edit, or organize portfolio information.</p>
        <div class="admin-shortcut-list">
            <a class="admin-button" href="profile.php">Profile</a>
            <a class="admin-button" href="settings.php">Website Settings</a>
            <a class="admin-button" href="projects.php">Projects</a>
            <a class="admin-button" href="skills.php">Skills</a>
            <a class="admin-button" href="education.php">Education</a>
            <a class="admin-button" href="experience.php">Experience</a>
            <a class="admin-button" href="achievements.php">Achievements</a>
            <a class="admin-button" href="messages.php">Messages</a>
        </div>
    </nav>
</main>
</body>
</html>

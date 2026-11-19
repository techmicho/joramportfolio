<?php

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';

$error = '';
$success = '';

try {
    $profileStatement = $pdo->prepare('SELECT full_name, email, location FROM profile WHERE id = 1');
    $profileStatement->execute();
    $profile = $profileStatement->fetch();
} catch (PDOException $error) {
    error_log($error->getMessage());
    $profile = false;
}
if (!$profile) {
    $profile = ['full_name' => 'Joram Rusiana', 'email' => 'rusianajor@gmail.com', 'location' => 'Negros Oriental, Philippines'];
}
$siteTitle = get_setting($pdo, 'site_title', $profile['full_name'] . ' — Personal Portfolio');
$accentColor = get_setting($pdo, 'accent_color', '#ff7a00');
$siteLogo = get_setting($pdo, 'site_logo', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session has expired. Please try again.';
    } else {
        if (trim($_POST['website'] ?? '') !== '') {
            $success = 'Thanks! Your message was sent successfully.';
        } else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (strlen($name) < 2 || strlen($name) > 120) {
            $error = 'Please enter a valid name.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($subject) > 180) {
            $error = 'The subject is too long.';
        } elseif (strlen($message) < 10 || strlen($message) > 5000) {
            $error = 'Please enter a message between 10 and 5000 characters.';
        } else {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $attemptStatement = $pdo->prepare(
                'SELECT COUNT(*) FROM contact_attempts
                 WHERE ip_address = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
            );
            $attemptStatement->execute([$ipAddress]);
            if ((int) $attemptStatement->fetchColumn() >= 5) {
                $error = 'Too many messages submitted recently. Please try again later.';
            } else {
                $insertAttempt = $pdo->prepare('INSERT INTO contact_attempts (ip_address) VALUES (?)');
                $insertAttempt->execute([$ipAddress]);
                $statement = $pdo->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)');
                $statement->execute([$name, $email, $subject !== '' ? $subject : 'No subject', $message]);
                $success = 'Thanks! Your message was sent successfully.';
            }
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
<title>Contact — <?php echo escape_html($siteTitle); ?></title>
<link rel="stylesheet" href="css/style.css">
<style>:root { --accent: <?php echo escape_html($accentColor); ?>; }</style>
<script>
  (function () {
    var savedTheme = localStorage.getItem('portfolio-theme');
    if (savedTheme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
  })();
</script>
</head>
<body>
<header>
  <div class="nav-inner wrap">
    <a href="index.php" class="logo"><?php echo $siteLogo !== '' ? '<img src="' . escape_html($siteLogo) . '" alt="' . escape_html($siteTitle) . '" style="max-height:42px">' : escape_html($profile['full_name']); ?></a>
    <nav id="main-nav">
      <ul>
        <li><a href="index.php" class="nav-link">Home</a></li>
        <li><a href="about.php" class="nav-link">About</a></li>
        <li><a href="skills.php" class="nav-link">Skills</a></li>
        <li><a href="projects.php" class="nav-link">Projects</a></li>
        <li><a href="resume.php" class="nav-link">Resume</a></li>
        <li><a href="contact.php" class="nav-link active">Contact</a></li>
      </ul>
    </nav>
    <div class="nav-controls">
      <button id="theme-toggle" aria-label="Toggle dark mode">Dark</button>
      <button id="nav-toggle" aria-label="Toggle menu" aria-expanded="false">Menu</button>
    </div>
  </div>
</header>
<main>
  <div class="wrap page-header">
    <h1>Contact Me</h1>
    <p>Feel free to reach out — I'd love to hear from you.</p>
  </div>
  <section id="contact">
    <div class="wrap contact-grid">
      <div class="contact-info">
        <p><strong>Name:</strong> <?php echo escape_html($profile['full_name']); ?></p>
        <?php if (!empty($profile['email'])): ?><p><strong>Email:</strong> <a href="mailto:<?php echo escape_html($profile['email']); ?>"><?php echo escape_html($profile['email']); ?></a></p><?php endif; ?>
        <?php if (!empty($profile['location'])): ?><p><strong>Location:</strong> <?php echo escape_html($profile['location']); ?></p><?php endif; ?>
      </div>
      <form id="contact-form" method="post" action="contact.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo escape_html($token); ?>">
        <div style="position:absolute;left:-10000px" aria-hidden="true"><label>Leave this field empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <?php if ($error !== ''): ?><div class="admin-error" role="alert"><?php echo escape_html($error); ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div id="form-success" class="admin-success show" role="status"><?php echo escape_html($success); ?></div><?php endif; ?>
        <div class="field"><label for="name">Name</label><input type="text" id="name" name="name" maxlength="120" minlength="2" required value="<?php echo escape_html($success === '' ? ($name ?? '') : ''); ?>"><span class="error-text" id="name-error"></span></div>
        <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" maxlength="190" required value="<?php echo escape_html($success === '' ? ($email ?? '') : ''); ?>"><span class="error-text" id="email-error"></span></div>
        <div class="field"><label for="subject">Subject</label><input type="text" id="subject" name="subject" maxlength="180" value="<?php echo escape_html($success === '' ? ($subject ?? '') : ''); ?>"></div>
        <div class="field"><label for="message">Message</label><textarea id="message" name="message" maxlength="5000" minlength="10" required><?php echo escape_html($success === '' ? ($message ?? '') : ''); ?></textarea><span class="error-text" id="message-error"></span></div>
        <button type="submit">Send Message</button>
      </form>
    </div>
  </section>
</main>
<footer>
  <div class="wrap footer-inner"><p>&copy; 2026 <?php echo escape_html($profile['full_name']); ?>. All Rights Reserved.</p></div>
</footer>
<button id="scroll-top-btn" aria-label="Scroll to top">&uarr;</button>
<script src="js/script.js"></script>
</body>
</html>

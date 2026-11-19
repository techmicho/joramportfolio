<?php

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';

try {
    $profileStatement = $pdo->prepare('SELECT full_name FROM profile WHERE id = 1');
    $profileStatement->execute();
    $profile = $profileStatement->fetch();
    $skills = $pdo->query(
        'SELECT name, category, description, level_label, level_percent
         FROM skills
         ORDER BY sort_order ASC, id DESC'
    )->fetchAll();
} catch (PDOException $error) {
    error_log($error->getMessage());
    $profile = false;
    $skills = [];
}
$profileName = is_array($profile) ? ($profile['full_name'] ?? 'Joram Rusiana') : 'Joram Rusiana';
$siteTitle = get_setting($pdo, 'site_title', $profileName . ' — Personal Portfolio');
$accentColor = get_setting($pdo, 'accent_color', '#ff7a00');
$siteLogo = get_setting($pdo, 'site_logo', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Skills — <?php echo escape_html($siteTitle); ?></title>
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
    <a href="index.php" class="logo"><?php echo $siteLogo !== '' ? '<img src="' . escape_html($siteLogo) . '" alt="' . escape_html($siteTitle) . '" style="max-height:42px">' : escape_html($profileName); ?></a>
    <nav id="main-nav">
      <ul>
        <li><a href="index.php" class="nav-link">Home</a></li>
        <li><a href="about.php" class="nav-link">About</a></li>
        <li><a href="skills.php" class="nav-link active">Skills</a></li>
        <li><a href="projects.php" class="nav-link">Projects</a></li>
        <li><a href="resume.php" class="nav-link">Resume</a></li>
        <li><a href="contact.php" class="nav-link">Contact</a></li>
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
    <h1>My Skills</h1>
    <p>A mix of technical and non-technical skills I've been building as a BSIT student.</p>
  </div>
  <section id="skills">
    <div class="wrap">
      <?php if (count($skills) === 0): ?>
        <p>No skills are currently available.</p>
      <?php else: ?>
        <div class="skills-grid">
          <?php foreach ($skills as $skill): ?>
            <article class="skill-card">
              <h3><?php echo escape_html($skill['name']); ?></h3>
              <?php if (!empty($skill['description'])): ?><p><?php echo nl2br(escape_html($skill['description'])); ?></p><?php endif; ?>
              <?php if (!empty($skill['level_label']) || (int) $skill['level_percent'] > 0): ?>
                <div class="skill-level-bar">
                  <div class="bar-label">
                    <span><?php echo escape_html($skill['level_label']); ?></span>
                    <span><?php echo (int) $skill['level_percent']; ?>%</span>
                  </div>
                  <div class="progress-track"><div class="progress-fill" style="width: <?php echo (int) $skill['level_percent']; ?>%;"></div></div>
                </div>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
<footer>
  <div class="wrap footer-inner">
    <p>&copy; 2026 <?php echo escape_html($profileName); ?>. All Rights Reserved.</p>
    <ul class="social-links">
      <li><a href="contact.php">Facebook</a></li>
      <li><a href="contact.php">Instagram</a></li>
      <li><a href="contact.php">LinkedIn</a></li>
    </ul>
  </div>
</footer>
<button id="scroll-top-btn" aria-label="Scroll to top">&uarr;</button>
<script src="js/script.js"></script>
</body>
</html>

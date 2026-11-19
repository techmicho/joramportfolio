<?php

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';

try {
    $statement = $pdo->prepare('SELECT * FROM profile WHERE id = 1');
    $statement->execute();
    $profile = $statement->fetch();
    $education = $pdo->query('SELECT institution, qualification FROM education ORDER BY sort_order ASC, id DESC LIMIT 3')->fetchAll();
} catch (PDOException $error) {
    error_log($error->getMessage());
    $profile = false;
    $education = [];
}

if (!$profile) {
    $profile = ['full_name' => 'Joram Rusiana', 'headline' => 'BSIT Student', 'introduction' => 'I am interested in technology, web development, and creative digital projects.', 'biography' => 'I enjoy learning new skills and improving my knowledge through school activities and projects.', 'email' => 'rusianajor@gmail.com', 'location' => 'Negros Oriental, Philippines'];
}
$siteTitle = get_setting($pdo, 'site_title', $profile['full_name'] . ' — Personal Portfolio');
$accentColor = get_setting($pdo, 'accent_color', '#ff7a00');
$siteLogo = get_setting($pdo, 'site_logo', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About — <?php echo escape_html($siteTitle); ?></title>
<link rel="stylesheet" href="css/style.css">
<style>:root { --accent: <?php echo escape_html($accentColor); ?>; }</style>
<script>(function(){if(localStorage.getItem('portfolio-theme')==='dark')document.documentElement.setAttribute('data-theme','dark');})();</script>
</head>
<body>
<header>
  <div class="nav-inner wrap">
    <a href="index.php" class="logo"><?php echo $siteLogo !== '' ? '<img src="' . escape_html($siteLogo) . '" alt="' . escape_html($siteTitle) . '" style="max-height:42px">' : escape_html($profile['full_name']); ?></a>
    <nav id="main-nav"><ul>
      <li><a href="index.php" class="nav-link">Home</a></li><li><a href="about.php" class="nav-link active">About</a></li>
      <li><a href="skills.php" class="nav-link">Skills</a></li><li><a href="projects.php" class="nav-link">Projects</a></li>
      <li><a href="resume.php" class="nav-link">Resume</a></li><li><a href="contact.php" class="nav-link">Contact</a></li>
    </ul></nav>
    <div class="nav-controls"><button id="theme-toggle" aria-label="Toggle dark mode">Dark</button><button id="nav-toggle" aria-label="Toggle menu" aria-expanded="false">Menu</button></div>
  </div>
</header>
<main>
  <div class="wrap page-header"><h1>About Me</h1><p>My background, education, goals, and interests.</p></div>
  <section id="about">
    <div class="wrap about-grid">
      <div>
        <h3>Background</h3>
        <p><?php echo nl2br(escape_html($profile['biography'])); ?></p>
        <p><?php echo nl2br(escape_html($profile['introduction'])); ?></p>
        <h3>Goals</h3>
        <p>My goal is to become a well-rounded IT professional who can build useful, well-designed software. I want to keep improving my web development and programming skills and contribute to meaningful projects.</p>
      </div>
      <div>
        <h3>Education</h3>
        <?php if ($education): ?>
          <?php foreach ($education as $item): ?><p><strong><?php echo escape_html($item['qualification']); ?></strong><br><?php echo escape_html($item['institution']); ?></p><?php endforeach; ?>
        <?php else: ?><p><?php echo escape_html($profile['headline']); ?></p><?php endif; ?>
        <h3>Interests / Hobbies</h3>
        <ul><li>Playing video games</li><li>Exploring new technology</li><li>Watching videos and tutorials</li><li>Working on creative digital projects</li><li>Learning about computers and IT</li></ul>
        <?php if (!empty($profile['location'])): ?><p><strong>Location:</strong> <?php echo escape_html($profile['location']); ?></p><?php endif; ?>
        <?php if (!empty($profile['email'])): ?><p><strong>Email:</strong> <a href="mailto:<?php echo escape_html($profile['email']); ?>"><?php echo escape_html($profile['email']); ?></a></p><?php endif; ?>
      </div>
    </div>
  </section>
</main>
<footer><div class="wrap footer-inner"><p>&copy; 2026 <?php echo escape_html($profile['full_name']); ?>. All Rights Reserved.</p><ul class="social-links"><li><a href="contact.php">Facebook</a></li><li><a href="contact.php">Instagram</a></li><li><a href="contact.php">LinkedIn</a></li></ul></div></footer>
<button id="scroll-top-btn" aria-label="Scroll to top">&uarr;</button>
<script src="js/script.js"></script>
</body>
</html>

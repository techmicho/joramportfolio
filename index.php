<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';

try {
    $statement = $pdo->prepare('SELECT * FROM profile WHERE id = 1');
    $statement->execute();
    $profile = $statement->fetch();
} catch (PDOException $error) {
    error_log($error->getMessage());
    $profile = false;
}
if (!$profile) {
    $profile = [
        'full_name' => 'Joram Rusiana',
        'headline' => 'BSIT Student',
        'introduction' => 'I am interested in web development, animation, and technology. Welcome to my personal portfolio — take a look around at my skills, projects, and background.',
        'email' => 'rusianajor@gmail.com',
        'phone' => '',
        'location' => 'Negros Oriental, Philippines',
        'profile_image' => 'images/avatar.svg',
    ];
}
$profileImage = !empty($profile['profile_image']) ? $profile['profile_image'] : 'images/avatar.svg';
$siteTitle = get_setting($pdo, 'site_title', $profile['full_name'] . ' — Personal Portfolio');
$accentColor = get_setting($pdo, 'accent_color', '#ff7a00');
$siteLogo = get_setting($pdo, 'site_logo', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo escape_html($siteTitle); ?></title>
<link rel="stylesheet" href="css/style.css?v=20261119-1">
<style>:root { --accent: <?php echo escape_html($accentColor); ?>; }</style>
<script>(function(){if(localStorage.getItem('portfolio-theme')==='dark')document.documentElement.setAttribute('data-theme','dark');})();</script>
</head>
<body>
<header><div class="nav-inner wrap">
  <a href="index.php" class="logo"><?php echo $siteLogo !== '' ? '<img src="' . escape_html($siteLogo) . '" alt="' . escape_html($siteTitle) . '" style="max-height:42px">' : escape_html($profile['full_name']); ?></a>
  <nav id="main-nav"><ul>
    <li><a href="index.php" class="nav-link active">Home</a></li><li><a href="about.php" class="nav-link">About</a></li>
    <li><a href="skills.php" class="nav-link">Skills</a></li><li><a href="projects.php" class="nav-link">Projects</a></li>
    <li><a href="resume.php" class="nav-link">Resume</a></li><li><a href="contact.php" class="nav-link">Contact</a></li>
  </ul></nav>
  <div class="nav-controls"><button id="theme-toggle" aria-label="Toggle dark mode">Dark</button><button id="nav-toggle" aria-label="Toggle menu" aria-expanded="false">Menu</button></div>
</div></header>
<main><section id="home" class="hero"><div class="wrap hero-grid">
  <img src="<?php echo escape_html($profileImage); ?>" alt="Portrait of <?php echo escape_html($profile['full_name']); ?>">
  <div class="hero-text">
    <h1>Hi, I'm <?php echo escape_html($profile['full_name']); ?></h1>
    <p class="hero-role">I'm a <span id="typing-role"><?php echo escape_html($profile['headline']); ?></span></p>
    <p class="hero-desc"><?php echo nl2br(escape_html($profile['introduction'])); ?></p>
    <div class="hero-actions"><a href="projects.php" class="btn btn-accent">View My Projects</a><a href="contact.php" class="btn btn-outline">Get In Touch</a></div>
  </div>
</div></section></main>
<footer><div class="wrap footer-inner"><p>&copy; 2026 <?php echo escape_html($profile['full_name']); ?>. All Rights Reserved.</p><ul class="social-links"><li><a href="contact.php">Facebook</a></li><li><a href="contact.php">Instagram</a></li><li><a href="contact.php">LinkedIn</a></li></ul></div></footer>
<button id="scroll-top-btn" aria-label="Scroll to top">&uarr;</button><script src="js/script.js?v=20261119-1"></script>
</body></html>

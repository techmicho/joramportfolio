<?php

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';

try {
    $profileStatement = $pdo->prepare('SELECT full_name, headline, email, phone, location FROM profile WHERE id = 1');
    $profileStatement->execute();
    $profile = $profileStatement->fetch();
    $education = $pdo->query('SELECT institution, qualification, details, start_date, end_date FROM education ORDER BY sort_order ASC, id DESC')->fetchAll();
    $experience = $pdo->query('SELECT title, organization, details, start_date, end_date FROM experience ORDER BY sort_order ASC, id DESC')->fetchAll();
    $achievements = $pdo->query('SELECT title, organization, details, image_path, achievement_date FROM achievements ORDER BY sort_order ASC, id DESC')->fetchAll();
} catch (PDOException $error) {
    error_log($error->getMessage());
    $profile = false;
    $education = [];
    $experience = [];
    $achievements = [];
}

if (!$profile) $profile = ['full_name' => 'Joram Rusiana', 'headline' => 'BSIT Student', 'email' => 'rusianajor@gmail.com', 'phone' => '', 'location' => 'Negros Oriental, Philippines'];
$siteTitle = get_setting($pdo, 'site_title', $profile['full_name'] . ' — Personal Portfolio');
$accentColor = get_setting($pdo, 'accent_color', '#ff7a00');
$siteLogo = get_setting($pdo, 'site_logo', '');

function resume_date(?string $date): string
{
    if (!$date) return '';
    $timestamp = strtotime($date);
    return $timestamp ? date('F Y', $timestamp) : '';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Resume — <?php echo escape_html($siteTitle); ?></title><link rel="stylesheet" href="css/style.css">
<style>:root { --accent: <?php echo escape_html($accentColor); ?>; }</style>
<script>(function(){if(localStorage.getItem('portfolio-theme')==='dark')document.documentElement.setAttribute('data-theme','dark');})();</script>
</head>
<body>
<header><div class="nav-inner wrap"><a href="index.php" class="logo"><?php echo $siteLogo !== '' ? '<img src="' . escape_html($siteLogo) . '" alt="' . escape_html($siteTitle) . '" style="max-height:42px">' : escape_html($profile['full_name']); ?></a><nav id="main-nav"><ul>
<li><a href="index.php" class="nav-link">Home</a></li><li><a href="about.php" class="nav-link">About</a></li><li><a href="skills.php" class="nav-link">Skills</a></li><li><a href="projects.php" class="nav-link">Projects</a></li><li><a href="resume.php" class="nav-link active">Resume</a></li><li><a href="contact.php" class="nav-link">Contact</a></li>
</ul></nav><div class="nav-controls"><button id="theme-toggle" aria-label="Toggle dark mode">Dark</button><button id="nav-toggle" aria-label="Toggle menu" aria-expanded="false">Menu</button></div></div></header>
<main>
  <div class="wrap page-header"><h1>Resume / Experience</h1><p>Education, activities, training, and certifications.</p></div>
  <section id="education"><div class="wrap"><h2>Education</h2>
    <?php if (!$education): ?><p>No education entries are currently available.</p><?php else: ?>
      <?php foreach ($education as $item): ?><article class="edu-card"><div><h3><?php echo escape_html($item['qualification']); ?></h3><p><?php echo escape_html($item['institution']); ?></p><?php if (!empty($item['details'])): ?><p><?php echo nl2br(escape_html($item['details'])); ?></p><?php endif; ?><span><?php echo escape_html(resume_date($item['start_date'])); ?><?php if (!empty($item['start_date']) && !empty($item['end_date'])): ?>–<?php endif; ?><?php echo escape_html(resume_date($item['end_date']) ?: (!empty($item['start_date']) ? 'Present' : '')); ?></span></div></article><?php endforeach; ?>
    <?php endif; ?>
  </div></section>
  <section id="experience"><div class="wrap"><h2>Activities &amp; Training</h2>
    <?php if (!$experience): ?><p>No activities or training entries are currently available.</p><?php else: ?><ul class="timeline">
      <?php foreach ($experience as $item): ?><li><h3><?php echo escape_html($item['title']); ?></h3><?php if (!empty($item['organization'])): ?><span><?php echo escape_html($item['organization']); ?></span><?php endif; ?><?php if (!empty($item['start_date']) || !empty($item['end_date'])): ?><p class="resume-date"><?php echo escape_html(resume_date($item['start_date'])); ?><?php if (!empty($item['start_date']) && !empty($item['end_date'])): ?> — <?php endif; ?><?php echo escape_html(resume_date($item['end_date']) ?: (!empty($item['start_date']) ? 'Present' : '')); ?></p><?php endif; ?><p><?php echo nl2br(escape_html($item['details'])); ?></p></li><?php endforeach; ?>
    </ul><?php endif; ?>
  </div></section>
  <section id="achievements"><div class="wrap"><h2>Achievements &amp; Certificates</h2>
    <?php if ($achievements): ?><ul class="timeline">
      <?php foreach ($achievements as $item): ?><li><h3><?php echo escape_html($item['title']); ?></h3><?php if (!empty($item['organization'])): ?><span><?php echo escape_html($item['organization']); ?></span><?php endif; ?><?php if (!empty($item['achievement_date'])): ?><p class="resume-date"><?php echo escape_html(resume_date($item['achievement_date'])); ?></p><?php endif; ?><?php if (!empty($item['details'])): ?><p><?php echo nl2br(escape_html($item['details'])); ?></p><?php endif; ?><?php if (!empty($item['image_path'])): ?><?php if (str_ends_with(strtolower($item['image_path']), '.pdf')): ?><p><a href="<?php echo escape_html($item['image_path']); ?>" target="_blank" rel="noopener noreferrer">View Certificate</a></p><?php else: ?><img class="achievement-image" src="<?php echo escape_html($item['image_path']); ?>" alt="<?php echo escape_html($item['title']); ?> certificate or achievement image" loading="lazy"><?php endif; ?><?php endif; ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div></section>
</main>
<footer><div class="wrap footer-inner"><p>&copy; 2026 <?php echo escape_html($profile['full_name']); ?>. All Rights Reserved.</p><ul class="social-links"><li><a href="contact.php">Facebook</a></li><li><a href="contact.php">Instagram</a></li><li><a href="contact.php">LinkedIn</a></li></ul></div></footer>
<button id="scroll-top-btn" aria-label="Scroll to top">&uarr;</button><script src="js/script.js"></script>
</body>
</html>

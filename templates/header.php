<?php
// ============================================================
//  templates/header.php
//
//  Erwartet folgende Variablen aus der einbindenden Datei:
//    $title      — Seitentitel (optional, Standard: 'Projekt-Timeline')
//    $theme      — 'dark' | 'light' (optional, Standard: 'dark')
//    $extra_css  — Pfad zu einer extra CSS-Datei (optional)
//    $body_class — extra CSS-Klassen für <body> (optional)
// ============================================================
$title      = $title      ?? 'Projekt-Timeline';
$theme      = $theme      ?? 'dark';
$extra_css  = $extra_css  ?? null;
$body_class = $body_class ?? '';
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="<?= htmlspecialchars($theme) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= $base_path ?? '' ?>favicon.svg">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <!-- App CSS -->
  <link rel="stylesheet" href="<?= $base_path ?? '' ?>assets/css/style.css">

  <?php if ($extra_css): ?>
  <!-- Extra CSS für diese Seite -->
  <link rel="stylesheet" href="<?= htmlspecialchars($extra_css) ?>">
  <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($body_class) ?>">
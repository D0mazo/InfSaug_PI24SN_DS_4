<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $pageTitle ?? 'RSA Parašas' ?></title>
    <link rel="stylesheet" href="style.css">
    <?= $pageStyles ?? '' ?>
</head>
<body>

<header class="site-header">
    <span class="prog-badge <?= $badgeClass ?? 'badge-1' ?>"><?= $badgeLabel ?? 'Programa' ?></span>
    <h1>RSA <span style="color:<?= $titleColor ?? 'var(--blue)' ?>"><?= $titleSub ?? '' ?></span></h1>
</header>

<nav class="nav-pills">
    <a href="program1.php" class="nav-pill <?= ($activePage ?? '') === '1' ? 'active' : '' ?>">① Pasirašymas</a>
    <a href="program2.php" class="nav-pill <?= ($activePage ?? '') === '2' ? 'active' : '' ?>">② Tarpininkė</a>
    <a href="program3.php" class="nav-pill <?= ($activePage ?? '') === '3' ? 'active' : '' ?>">③ Tikrinimas</a>
</nav>
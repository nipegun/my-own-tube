<!DOCTYPE html>
<html lang="<?= fE(fCurrentLocale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= fE(cAppName) ?></title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="empty" style="padding-top:120px">
    <div class="empty-icon"><?= fIconSettings(['size' => 52, 'stroke' => 'var(--text-3)']) ?></div>
    <h2><?= fE(fT('error.database_not_initialized')) ?></h2>
    <p><?= fE(fT('error.database_setup')) ?> <code>php backend/seed.php</code></p>
  </div>
</body>
</html>

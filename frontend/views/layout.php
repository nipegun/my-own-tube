<!DOCTYPE html>
<html lang="<?= fE(fCurrentLocale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= fE(cAppName) ?></title>
  <link rel="stylesheet" href="styles.css">
  <script src="ui.js" defer></script>
</head>
<body>
  <?php if (in_array($vPage, $aPublicPages, true)): ?>
    <main class="auth-main">
      <?= $vPageContent ?>
      <?= fToastsRender() ?>
    </main>
  <?php else: ?>
  <div class="app">
    <?= fTopbar($vPage) ?>
    <button type="button" class="sidebar-backdrop" data-sidebar-close aria-label="<?= fE(fT('nav.close_menu')) ?>"></button>
    <div class="<?= fE($vBodyClass) ?>">
      <?= fSidebar($vPage, $vIsWatch) ?>
      <main class="main">
        <?= $vPageContent ?>
      </main>
    </div>
    <?= fToastsRender() ?>
  </div>
  <?php endif; ?>
</body>
</html>

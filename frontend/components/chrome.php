<?php

function fTopbar($pCurrentPage) {
  $dNavigation = fNavigationData();
  $vQuery = $dNavigation['vQuery'];
  $ldNotifications = $dNavigation['ldNotifications'];
  $ldSuggestions = $dNavigation['ldSuggestions'];
  $dLocales = $dNavigation['dLocales'];
  $vLocale = $dNavigation['vLocale'];
  $vBack = $dNavigation['vBack'];
  $vUnread = $dNavigation['vUnread'];

  ob_start(); ?>
  <header class="topbar">
    <div class="topbar-left">
      <button class="menu-btn" type="button" aria-label="<?= fE(fT('nav.menu')) ?>" aria-controls="app-sidebar" aria-expanded="true" data-sidebar-toggle>
        <?= fIconMenu() ?>
      </button>
      <a class="logo" href="<?= fE(fUrl('home')) ?>">
        <span class="logo-mark" aria-hidden="true"></span>
        <span class="logo-text">MyOwn<span class="accent">Tube</span></span>
      </a>
    </div>

    <div class="topbar-center">
      <form class="search" method="post" action="actions/record-search.php" autocomplete="off">
        <?= fCsrfField() ?>
        <div class="search-input-wrap">
          <input
            class="search-input"
            type="text"
            name="q"
            maxlength="200"
            placeholder="<?= fE(fT('nav.search')) ?>"
            value="<?= fE($vQuery) ?>"
            id="tb-search-input"
            onfocus="this.parentElement.classList.add('focused')"
            onblur="setTimeout(function(){document.querySelector('.search-input-wrap').classList.remove('focused')}, 150)">
          <?php if (!empty($ldSuggestions)): ?>
            <div class="suggestions" id="tb-suggestions">
              <?php foreach ($ldSuggestions as $dSuggestion): ?>
                <a class="suggestion-item" href="<?= fE(fUrl('search', ['q' => $dSuggestion['text']])) ?>">
                  <span class="sicon"><?= (int) $dSuggestion['is_history'] === 1 ? fIconHistory(['size' => 16]) : fIconSearch(['size' => 16]) ?></span>
                  <span class="stext"><?= fE($dSuggestion['text']) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <button class="search-btn" type="submit" aria-label="<?= fE(fT('nav.search')) ?>"><?= fIconSearch(['size' => 20]) ?></button>
      </form>
    </div>

    <div class="topbar-right">
      <details class="tb-menu">
        <summary class="icon-btn" aria-label="<?= fE(fT('nav.create')) ?>"><?= fIconCreate(['size' => 22]) ?></summary>
        <div class="user-menu" style="width:240px">
          <a class="um-item" href="<?= fE(fUrl('upload')) ?>"><?= fIconCreate(['size' => 20]) ?> <?= fE(fT('nav.upload_video')) ?></a>
        </div>
      </details>

      <details class="tb-menu">
        <summary class="icon-btn" aria-label="<?= fE(fT('nav.notifications')) ?>">
          <?= fIconBell(['size' => 22]) ?>
          <?php if ($vUnread > 0): ?><span class="badge"><?= (int) $vUnread ?></span><?php endif; ?>
        </summary>
        <div class="dropdown">
          <div class="dropdown-head">
            <h4><?= fE(fT('nav.notifications')) ?></h4>
          </div>
          <div class="dropdown-body">
            <?php if (empty($ldNotifications)): ?>
              <p class="dropdown-empty"><?= fE(fT('notifications.empty')) ?></p>
            <?php else: ?>
              <?php foreach ($ldNotifications as $dNotification): ?>
                <?php $dChannel = ['name' => $dNotification['channel_name'], 'color' => $dNotification['channel_color'], 'verified' => 0]; ?>
                <div class="notif<?= (int) $dNotification['unread'] === 1 ? ' unread' : '' ?>">
                  <div class="notif-avatar"><?= fChannelAvatar($dChannel, 40) ?></div>
                  <div class="notif-body">
                    <p class="notif-text"><b><?= fE($dNotification['channel_name']) ?></b> <?= fE($dNotification['message']) ?></p>
                    <span class="notif-time"><?= fE(fFormatWhen($dNotification['created_at'])) ?></span>
                  </div>
                  <?php if (!empty($dNotification['thumb_variant'])): ?>
                    <div class="notif-thumb"><?= fPlaceholderThumb((int) $dNotification['thumb_variant'], '') ?></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </details>

      <details class="tb-menu">
        <summary class="avatar" aria-label="<?= fE(fT('nav.account')) ?>" style="background:<?= fE(fUserColor()) ?>"><?= fE(fUserInitial()) ?></summary>
        <div class="user-menu">
          <div class="um-head">
            <div class="av" style="background:<?= fE(fUserColor()) ?>"><?= fE(fUserInitial()) ?></div>
            <div class="info">
              <h5><?= fE(fUserName()) ?></h5>
              <p><?= fE(fUserEmail()) ?></p>
            </div>
          </div>
          <a class="um-item" href="<?= fE(fUrl('profile')) ?>"><?= fIconEye(['size' => 20]) ?> <?= fE(fT('nav.profile')) ?></a>
          <a class="um-item" href="<?= fE(fUrl('liked')) ?>"><?= fIconHeart(['size' => 20]) ?> <?= fE(fT('nav.liked')) ?></a>
          <a class="um-item" href="<?= fE(fUrl('watchlater')) ?>"><?= fIconClock(['size' => 20]) ?> <?= fE(fT('nav.watch_later')) ?></a>
          <a class="um-item" href="<?= fE(fUrl('history')) ?>"><?= fIconHistory(['size' => 20]) ?> <?= fE(fT('nav.history')) ?></a>
          <?php if (fUserIsAdmin()): ?>
            <a class="um-item" href="<?= fE(fUrl('users')) ?>"><?= fIconLock(['size' => 20]) ?> <?= fE(fT('nav.users')) ?></a>
          <?php endif; ?>
          <div class="um-sep"></div>
          <a class="um-item" href="<?= fE(fUrl('settings')) ?>"><?= fIconSettings(['size' => 20]) ?> <?= fE(fT('nav.settings')) ?></a>
          <form class="um-language" method="post" action="actions/set-language.php">
            <?= fCsrfField() ?>
            <input type="hidden" name="back" value="<?= fE($vBack) ?>">
            <label class="um-language-label">
              <?= fIconGlobe(['size' => 20]) ?>
              <span><?= fE(fT('nav.language')) ?></span>
              <select name="locale" aria-label="<?= fE(fT('nav.language')) ?>" onchange="this.form.submit()">
                <?php foreach ($dLocales as $vCode => $vName): ?>
                  <option value="<?= fE($vCode) ?>"<?= $vCode === $vLocale ? ' selected' : '' ?>><?= fE($vName) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </form>
          <div class="um-sep"></div>
          <form method="post" action="actions/logout.php">
            <?= fCsrfField() ?>
            <button type="submit" class="um-item"><?= fIconLogOut(['size' => 20]) ?> <?= fE(fT('nav.logout')) ?></button>
          </form>
        </div>
      </details>
    </div>
  </header>
  <?php
  return ob_get_clean();
}

function fSidebar($pCurrentPage, $pMini) {
  $vSidebarClass = 'sidebar' . ($pMini ? ' mini' : '');
  ob_start(); ?>
  <aside id="app-sidebar" class="<?= fE($vSidebarClass) ?>">
    <div class="sb-section">
      <?= fSbItem($pCurrentPage, 'home',    fIconHome(['size' => 22]),    fT('nav.home')) ?>
      <?= fSbItem($pCurrentPage, 'trending',fIconTrending(['size' => 22]),fT('nav.trending')) ?>
      <?php if ($pMini): ?>
        <?= fSbItem($pCurrentPage, 'history', fIconHistory(['size' => 22]), fT('nav.history')) ?>
      <?php endif; ?>
    </div>

    <?php if (!$pMini): ?>
      <div class="sb-section">
        <div class="sb-title"><?= fE(fT('nav.you')) ?></div>
        <?= fSbItem($pCurrentPage, 'history',    fIconHistory(['size' => 22]), fT('nav.history')) ?>
        <?= fSbItem($pCurrentPage, 'liked',      fIconHeart(['size' => 22]),   fT('nav.liked')) ?>
        <?= fSbItem($pCurrentPage, 'watchlater', fIconClock(['size' => 22]),   fT('nav.watch_later')) ?>
        <?= fSbItem($pCurrentPage, 'playlists',  fIconPlaylist(['size' => 22]),fT('nav.playlists')) ?>
      </div>

      <div class="sb-section">
        <div class="sb-title"><?= fE(fT('nav.explore')) ?></div>
        <a class="sb-item" href="<?= fE(fUrl('search', ['q' => 'Music'])) ?>"><span class="sb-icon"><?= fIconSparkles(['size' => 22]) ?></span><span class="sb-label"><?= fE(fT('nav.music')) ?></span></a>
        <a class="sb-item" href="<?= fE(fUrl('search', ['q' => 'Gaming'])) ?>"><span class="sb-icon"><?= fIconSparkles(['size' => 22]) ?></span><span class="sb-label"><?= fE(fT('nav.gaming')) ?></span></a>
        <a class="sb-item" href="<?= fE(fUrl('search', ['q' => 'Technology'])) ?>"><span class="sb-icon"><?= fIconSparkles(['size' => 22]) ?></span><span class="sb-label"><?= fE(fT('nav.technology')) ?></span></a>
        <a class="sb-item" href="<?= fE(fUrl('search', ['q' => 'Cooking'])) ?>"><span class="sb-icon"><?= fIconSparkles(['size' => 22]) ?></span><span class="sb-label"><?= fE(fT('nav.cooking')) ?></span></a>
        <a class="sb-item" href="<?= fE(fUrl('search', ['q' => 'Travel'])) ?>"><span class="sb-icon"><?= fIconSparkles(['size' => 22]) ?></span><span class="sb-label"><?= fE(fT('nav.travel')) ?></span></a>
      </div>

    <?php endif; ?>
  </aside>
  <?php
  return ob_get_clean();
}

function fSbItem($pCurrentPage, $pKey, $pIconHtml, $pLabel) {
  $vActive = $pCurrentPage === $pKey ? ' active' : '';
  $vHref = fUrl($pKey);
  return '<a class="sb-item' . $vActive . '" href="' . fE($vHref) . '">'
    . '<span class="sb-icon">' . $pIconHtml . '</span>'
    . '<span class="sb-label">' . fE($pLabel) . '</span>'
    . '</a>';
}

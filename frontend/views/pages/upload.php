<div class="section-head-art upload">
  <div style="width:72px;height:72px;border-radius:16px;background:rgba(0,0,0,0.3);display:grid;place-items:center">
    <?= fIconCreate(['size' => 40]) ?>
  </div>
  <div class="art-meta">
    <h1><?= fE(fT('upload.title')) ?></h1>
    <p class="art-sub"><?= fE(fT('upload.subtitle')) ?></p>
  </div>
</div>

<div class="admin-layout">
  <section class="admin-panel">
    <h2><?= fE(fT('upload.new_video')) ?></h2>
    <form class="admin-form" method="post" action="actions/upload-video.php" enctype="multipart/form-data">
      <?= fCsrfField() ?>
      <label>
        <span><?= fE(fT('upload.video_file')) ?></span>
        <input type="file" name="video" accept="<?= fE($vAcceptVideo) ?>" required>
      </label>
      <label>
        <span><?= fE(fT('upload.video_title')) ?></span>
        <input type="text" name="title" maxlength="180" placeholder="<?= fE(fT('upload.title_placeholder')) ?>">
      </label>
      <label>
        <span><?= fE(fT('upload.category')) ?></span>
        <input type="text" name="category" maxlength="80" value="Uploads">
      </label>
      <label>
        <span><?= fE(fT('upload.description')) ?></span>
        <textarea name="description" maxlength="3000" rows="5" placeholder="<?= fE(fT('upload.description_placeholder')) ?>"></textarea>
      </label>
      <button class="btn btn-primary" type="submit"><?= fIconCreate(['size' => 18]) ?> <?= fE(fT('upload.submit')) ?></button>
    </form>
  </section>

  <section class="admin-panel">
    <h2><?= fE(fT('upload.folder_title')) ?></h2>
    <div class="upload-info">
      <p><?= fE(fT('upload.folder_path_label')) ?></p>
      <code><?= fE($vVideosPath) ?></code>
      <p><?= fE(fT('upload.folder_help')) ?></p>
      <code>Example video.mp4</code>
      <code>Example video.json</code>
    </div>
    <?php if (fUserIsAdmin()): ?>
      <form method="post" action="actions/sync-videos.php" style="margin-top:16px">
        <?= fCsrfField() ?>
        <button class="btn btn-outline" type="submit"><?= fIconRefresh(['size' => 18]) ?> <?= fE(fT('upload.sync_folder')) ?></button>
      </form>
    <?php endif; ?>
  </section>
</div>

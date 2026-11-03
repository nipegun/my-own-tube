<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= fE($dSpec['info']['title']) ?></title>
  <link rel="stylesheet" href="/styles.css">
</head>
<body class="api-doc-body">
  <main class="api-doc">
    <header class="api-doc-head">
      <h1><?= fE($dSpec['info']['title']) ?> <span class="api-version"><?= fE($dSpec['info']['version']) ?></span></h1>
      <p><?= fE($dSpec['info']['description']) ?></p>
      <p class="api-doc-links">
        <a href="/api/doc/openapi.json">openapi.json</a>
        <a href="<?= fE(fUrl('home')) ?>"><?= fE(cAppName) ?></a>
      </p>
    </header>

    <?php foreach ($dTags as $vTag => $vTagDescription): ?>
      <section class="api-tag" id="tag-<?= fE($vTag) ?>">
        <h2><?= fE($vTag) ?></h2>
        <p class="api-tag-description"><?= fE($vTagDescription) ?></p>
        <?php foreach ($dSpec['paths'] as $vPath => $dOperations): ?>
          <?php foreach ($dOperations as $vMethod => $dOperation): ?>
            <?php if ($dOperation['tags'][0] !== $vTag) continue; ?>
            <details class="api-op api-op-<?= fE($vMethod) ?>" id="<?= fE($dOperation['operationId']) ?>">
              <summary>
                <span class="api-method"><?= fE(strtoupper($vMethod)) ?></span>
                <code class="api-path"><?= fE($vPath) ?></code>
                <span class="api-summary"><?= fE($dOperation['summary']) ?></span>
              </summary>
              <div class="api-op-body">
                <p class="api-access">Access: <b><?= fE(fOpenApiAccessLabel($dOperation['x-access'])) ?></b></p>

                <?php if (!empty($dOperation['parameters'])): ?>
                  <h3>Parameters</h3>
                  <div class="api-table-wrap">
                    <table class="api-table">
                      <thead><tr><th>Name</th><th>In</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                      <tbody>
                        <?php foreach ($dOperation['parameters'] as $dParameter): ?>
                          <tr>
                            <td><code><?= fE($dParameter['name']) ?></code></td>
                            <td><?= fE($dParameter['in']) ?></td>
                            <td><?= fE($dParameter['schema']['type']) ?></td>
                            <td><?= $dParameter['required'] ? 'yes' : 'no' ?></td>
                            <td><?= fE($dParameter['description']) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>

                <?php if (!empty($dOperation['requestBody'])): ?>
                  <?php foreach ($dOperation['requestBody']['content'] as $vContentType => $dContent): ?>
                    <h3>Request body <code><?= fE($vContentType) ?></code></h3>
                    <div class="api-table-wrap">
                      <table class="api-table">
                        <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                        <tbody>
                          <?php foreach ($dContent['schema']['properties'] as $vField => $dField): ?>
                            <tr>
                              <td><code><?= fE($vField) ?></code></td>
                              <td><?= fE(($dField['format'] ?? '') === 'binary' ? 'file' : $dField['type']) ?></td>
                              <td><?= in_array($vField, $dContent['schema']['required'] ?? [], true) ? 'yes' : 'no' ?></td>
                              <td><?= fE($dField['description']) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>

                <h3>Responses</h3>
                <div class="api-table-wrap">
                  <table class="api-table">
                    <thead><tr><th>Status</th><th>Description</th></tr></thead>
                    <tbody>
                      <?php foreach ($dOperation['responses'] as $vStatus => $dResponse): ?>
                        <tr>
                          <td><code><?= fE($vStatus) ?></code></td>
                          <td><?= fE($dResponse['description']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </details>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </main>
</body>
</html>

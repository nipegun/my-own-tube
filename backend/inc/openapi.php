<?php

require_once __DIR__ . '/routes.php';

function fOpenApiTags() {
  return [
    'auth'  => 'Start and end a session.',
    'user'  => 'Actions of the signed-in user: profile, library, comments, playlists, uploads, and media.',
    'admin' => 'Administrator actions: accounts, site settings, and the videos folder.',
    'doc'   => 'This documentation.',
  ];
}

function fOpenApiAccessLabel($pAccess) {
  $dLabels = [
    'public' => 'Public',
    'user'   => 'Signed-in user',
    'admin'  => 'Administrator',
  ];
  return $dLabels[$pAccess] ?? $pAccess;
}

function fOpenApiOperationId($pMethod, $pPath) {
  $vId = strtolower($pMethod) . '_' . trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($pPath)), '_');
  return $vId;
}

function fOpenApiSchemaType($pType) {
  if ($pType === 'integer') {
    return ['type' => 'integer'];
  }
  if ($pType === 'file') {
    return ['type' => 'string', 'format' => 'binary'];
  }
  return ['type' => 'string'];
}

function fOpenApiSpec() {
  $dPaths = [];

  foreach (fRouteApi() as $dRoute) {
    $dOperations = [];
    foreach ($dRoute['methods'] as $vMethod) {
      $dOperation = [
        'tags' => [$dRoute['tag']],
        'summary' => $dRoute['summary'],
        'operationId' => fOpenApiOperationId($vMethod, $dRoute['path']),
        'x-access' => $dRoute['access'],
        'security' => $dRoute['access'] === 'public' ? [] : [['sessionCookie' => []]],
        'responses' => [],
      ];

      $ldParameters = [];
      foreach ($dRoute['parameters'] ?? [] as $dParameter) {
        $ldParameters[] = [
          'name' => $dParameter['name'],
          'in' => $dParameter['in'],
          'required' => (bool) $dParameter['required'],
          'description' => $dParameter['description'],
          'schema' => fOpenApiSchemaType($dParameter['type']),
        ];
      }
      if (!empty($ldParameters)) {
        $dOperation['parameters'] = $ldParameters;
      }

      if (!empty($dRoute['fields'])) {
        $dProperties = [];
        $aRequired = [];
        foreach ($dRoute['fields'] as $dField) {
          $dProperties[$dField['name']] = array_merge(fOpenApiSchemaType($dField['type']), ['description' => $dField['description']]);
          if ($dField['required']) {
            $aRequired[] = $dField['name'];
          }
        }
        $dSchema = ['type' => 'object', 'properties' => $dProperties];
        if (!empty($aRequired)) {
          $dSchema['required'] = $aRequired;
        }
        $vContentType = $dRoute['content_type'] ?? 'application/x-www-form-urlencoded';
        $dOperation['requestBody'] = [
          'required' => true,
          'content' => [$vContentType => ['schema' => $dSchema]],
        ];
      }

      foreach ($dRoute['responses'] as $vStatus => $vDescription) {
        $dOperation['responses'][(string) $vStatus] = ['description' => $vDescription];
      }
      if (!isset($dOperation['responses']['405'])) {
        $dOperation['responses']['405'] = ['description' => 'Method not allowed; the `Allow` header lists the accepted methods.'];
      }

      $dOperations[strtolower($vMethod)] = $dOperation;
    }
    $dPaths[$dRoute['path']] = $dOperations;
  }

  $ldTags = [];
  foreach (fOpenApiTags() as $vName => $vDescription) {
    $ldTags[] = ['name' => $vName, 'description' => $vDescription];
  }

  return [
    'openapi' => '3.1.0',
    'info' => [
      'title' => cAppName . ' API',
      'version' => '1.0.0',
      'description' => 'Every endpoint under /api/. Form endpoints accept POST, validate the `csrf_token` of the session, and answer with a 303 redirect that stores a notice for the next page. Protected endpoints use the session cookie.',
    ],
    'servers' => [['url' => '/']],
    'tags' => $ldTags,
    'components' => [
      'securitySchemes' => [
        'sessionCookie' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => cSessionName],
      ],
    ],
    'paths' => $dPaths,
  ];
}

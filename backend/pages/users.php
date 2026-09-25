<?php

$dPagination = fPaginate('SELECT COUNT(*) FROM users', 'SELECT id, email, name, handle, initial, role, color, created_at FROM users ORDER BY created_at DESC, id');
$ldUsers = $dPagination['rows'];

require cFrontendPath . '/views/pages/users.php';

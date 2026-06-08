<?php
$session_id = null;

if (function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'X-Session-ID') === 0) {
            $session_id = trim($value);
            break;
        }
    }
}

if (!$session_id && isset($_SERVER['HTTP_X_SESSION_ID'])) {
    $session_id = trim($_SERVER['HTTP_X_SESSION_ID']);
}

// Session IDs are typically alphanumeric and can contain commas or dashes.
// Restricting characters prevents any security issues (like directory traversal).
if ($session_id && preg_match('/^[a-zA-Z0-9,-]+$/', $session_id)) {
    session_id($session_id);
}
?>

<?php
// Prevent execution via HTTP
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

// Read payload from stdin to avoid exposing data in process list and avoiding arg length limits
$payload = file_get_contents('php://stdin');

$data = json_decode($payload, true);
if ($data) {
    @mail($data["to"], $data["subject"], $data["message"], $data["headers"]);
}

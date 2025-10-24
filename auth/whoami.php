<?php
require_once __DIR__ . '/../../auth/guards.php';
header('Content-Type: application/json; charset=utf-8');
echo json_encode(optional_auth());
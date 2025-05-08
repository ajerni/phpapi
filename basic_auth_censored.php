<?php
// basic_auth.php

function requireBasicAuth($request, $response) {
    
    // Set your username and password here
    $validUser = 'your_username';
    $validPass = 'your_password';

    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="Blog API"');
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $username = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];

    if ($username !== $validUser || $password !== $validPass) {
        header('WWW-Authenticate: Basic realm="Blog API"');
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        exit;
    }

    return null; // Authenticated
} 
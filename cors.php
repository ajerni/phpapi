<?php
/**
 * CORS Handler
 * 
 * Add CORS support to allow your Astro blog to communicate with the API
 */

// Allow requests from the blog domain or all origins during development
$allowedOrigins = [
    'https://your-blog-domain.com',
    'http://localhost:4321',
    'http://localhost:3000'
];

// Get the origin header
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// Check if the origin is allowed
if (in_array($origin, $allowedOrigins) || getenv('ENVIRONMENT') === 'development') {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // If specific domain isn't in the list, allow all in development mode
    if (getenv('ENVIRONMENT') === 'development') {
        header("Access-Control-Allow-Origin: *");
    }
}

// Allow common HTTP methods
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Allow common headers
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Allow credentials (cookies)
header("Access-Control-Allow-Credentials: true");

// Set max age to 1 hour (3600 seconds)
header("Access-Control-Max-Age: 3600");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Return 200 OK status
    http_response_code(200);
    exit;
} 
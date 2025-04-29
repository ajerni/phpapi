<?php
/**
 * Route definitions
 * 
 * Define all your API routes in this file
 */

// Import database configuration
require_once 'dbconfig.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Root route
$app->get('/', function($request, $response) {
    $response->getBody()->write('Welcome to the lightweight PHP routing framework! View the source and documentation on <a href="https://github.com/ajerni/phpapi" target="_blank" rel="noopener noreferrer">GitHub</a>.');
    return $response;
});

// Basic parameter example
$app->get('/hello/{name}', function($request, $response, $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    return $response;
});

// --- REST API Examples ---

// GET all users
$app->get('/api/users', function($request, $response) use ($pdo) {
    try {
        $stmt = $pdo->query('SELECT id, name, email, created_at FROM users');
        $users = $stmt->fetchAll();
        
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode(['users' => $users, 'status' => 'success']));
        return $response;
    } catch (PDOException $e) {
        $response = $response->withStatus(500);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]));
        return $response;
    }
});

// GET single user
$app->get('/api/users/{id}', function($request, $response, $args) use ($pdo) {
    try {
        $id = $args['id'];
        $stmt = $pdo->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        $response = $response->withHeader('Content-Type', 'application/json');
        
        if (!$user) {
            $response = $response->withStatus(404);
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'User not found'
            ]));
            return $response;
        }
        
        $response->getBody()->write(json_encode(['user' => $user, 'status' => 'success']));
        return $response;
    } catch (PDOException $e) {
        $response = $response->withStatus(500);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]));
        return $response;
    }
});

// Create a new user
$app->post('/api/users', function($request, $response) use ($pdo) {
    // Log incoming request
    error_log('POST /api/users request received');
    
    // Get raw request body for debugging
    $rawBody = file_get_contents('php://input');
    error_log('Raw request body: ' . $rawBody);
    
    try {
        // Get parsed body and log it
        $data = $request->getParsedBody();
        error_log('Parsed body: ' . json_encode($data));
        
        // If data is null, try to parse the raw JSON manually
        if ($data === null) {
            error_log('Parsed body is null, trying manual JSON parse');
            $data = json_decode($rawBody, true);
            error_log('Manually parsed data: ' . json_encode($data));
        }
        
        // Validate required fields
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            error_log('Validation failed: Missing required fields');
            $response = $response->withStatus(400);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Name, email and password are required',
                'received_data' => $data
            ]));
            return $response;
        }
        
        // Hash the password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert the new user
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
        error_log('Executing query with params: ' . $data['name'] . ', ' . $data['email'] . ', [password_hash]');
        $result = $stmt->execute([$data['name'], $data['email'], $passwordHash]);
        error_log('Query execution result: ' . ($result ? 'true' : 'false'));
        $userId = $pdo->lastInsertId();
        error_log('Last insert ID: ' . $userId);
        
        $response = $response->withStatus(201);
        $response = $response->withHeader('Content-Type', 'application/json');
        $responseData = [
            'status' => 'success',
            'message' => 'User created successfully',
            'userId' => $userId
        ];
        error_log('Sending response: ' . json_encode($responseData));
        $response->getBody()->write(json_encode($responseData));
        return $response;
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        $response = $response->withStatus(500);
        $response = $response->withHeader('Content-Type', 'application/json');
        $errorMessage = $e->getMessage();
        
        // Check for duplicate email
        if (strpos($errorMessage, 'Duplicate entry') !== false && strpos($errorMessage, 'email') !== false) {
            error_log('Duplicate email detected');
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Email already exists'
            ]));
        } else {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $errorMessage
            ]));
        }
        return $response;
    } catch (Exception $e) {
        error_log('General error: ' . $e->getMessage());
        $response = $response->withStatus(500);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'General error: ' . $e->getMessage()
        ]));
        return $response;
    }
});

// Update a user
$app->put('/api/users/{id}', function($request, $response, $args) use ($pdo) {
    try {
        $id = $args['id'];
        $data = $request->getParsedBody();
        
        // Check if user exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $response = $response->withStatus(404);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'User not found'
            ]));
            return $response;
        }
        
        // Build update query dynamically based on provided fields
        $updateFields = [];
        $params = [];
        
        if (!empty($data['name'])) {
            $updateFields[] = 'name = ?';
            $params[] = $data['name'];
        }
        
        if (!empty($data['email'])) {
            $updateFields[] = 'email = ?';
            $params[] = $data['email'];
        }
        
        if (!empty($data['password'])) {
            $updateFields[] = 'password = ?';
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($updateFields)) {
            $response = $response->withStatus(400);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'No fields to update'
            ]));
            return $response;
        }
        
        // Add ID to parameters
        $params[] = $id;
        
        // Execute update
        $sql = 'UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'User ' . $id . ' updated successfully'
        ]));
        return $response;
    } catch (PDOException $e) {
        $response = $response->withStatus(500);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]));
        return $response;
    }
});

// Delete a user
$app->delete('/api/users/{id}', function($request, $response, $args) use ($pdo) {
    try {
        $id = $args['id'];
        
        // Check if user exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $response = $response->withStatus(404);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'User not found'
            ]));
            return $response;
        }
        
        // Delete user
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'User ' . $id . ' deleted successfully'
        ]));
        return $response;
    } catch (PDOException $e) {
        $response = $response->withStatus(500);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]));
        return $response;
    }
}); 
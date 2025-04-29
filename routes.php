<?php
/**
 * Route definitions
 * 
 * Define all your API routes in this file
 */

// Import database configuration
require_once 'dbconfig.php';

// Root route
$app->get('/', function($request, $response) {
    $response->getBody()->write('Welcome to the lightweight PHP routing framework!');
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
    try {
        $data = $request->getParsedBody();
        
        // Validate required fields
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            $response = $response->withStatus(400);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Name, email and password are required'
            ]));
            return $response;
        }
        
        // Hash the password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert the new user
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
        $stmt->execute([$data['name'], $data['email'], $passwordHash]);
        $userId = $pdo->lastInsertId();
        
        $response = $response->withStatus(201);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'User created successfully',
            'userId' => $userId
        ]));
        return $response;
    } catch (PDOException $e) {
        $response = $response->withStatus(500);
        $response = $response->withHeader('Content-Type', 'application/json');
        $errorMessage = $e->getMessage();
        
        // Check for duplicate email
        if (strpos($errorMessage, 'Duplicate entry') !== false && strpos($errorMessage, 'email') !== false) {
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
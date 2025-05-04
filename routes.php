<?php
/**
 * Route definitions
 * 
 * Define all your API routes in this file (or create your own additional files and include them in serve.php)
 */

// Import database configuration
require_once 'dbconfig.php';

// Root route
$app->get('/', function($request, $response) {
    $response->getBody()->write('Welcome to the lightweight PHP routing framework! <br><br> View the source code and documentation on <a href="https://github.com/ajerni/phpapi" target="_blank" rel="noopener noreferrer">GitHub</a>.');
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

// --- Blog Post API Endpoints ---

// GET all blog posts
$app->get('/api/posts', function($request, $response) use ($pdo) {
    try {
        $params = $request->getQueryParams();
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        // Get total count for pagination
        $countStmt = $pdo->query('SELECT COUNT(*) FROM blog_posts WHERE published = 1');
        $totalPosts = $countStmt->fetchColumn();
        
        // Query with pagination
        $stmt = $pdo->prepare('SELECT id, title, slug, excerpt, content, featured_image, published_date, 
                              updated_date, author_id, tags FROM blog_posts 
                              WHERE published = 1 
                              ORDER BY published_date DESC 
                              LIMIT ? OFFSET ?');
        $stmt->execute([$limit, $offset]);
        $posts = $stmt->fetchAll();
        
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'posts' => $posts, 
            'pagination' => [
                'total' => $totalPosts,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($totalPosts / $limit)
            ],
            'status' => 'success'
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

// GET single blog post by slug
$app->get('/api/posts/{slug}', function($request, $response, $args) use ($pdo) {
    try {
        $slug = $args['slug'];
        $stmt = $pdo->prepare('SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.featured_image, 
                              p.published_date, p.updated_date, p.tags, 
                              u.name as author_name, u.email as author_email 
                              FROM blog_posts p
                              LEFT JOIN users u ON p.author_id = u.id
                              WHERE p.slug = ? AND p.published = 1');
        $stmt->execute([$slug]);
        $post = $stmt->fetch();
        
        $response = $response->withHeader('Content-Type', 'application/json');
        
        if (!$post) {
            $response = $response->withStatus(404);
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Post not found'
            ]));
            return $response;
        }
        
        // If tags is stored as JSON string, decode it
        if (isset($post['tags']) && is_string($post['tags'])) {
            $post['tags'] = json_decode($post['tags'], true);
        }
        
        $response->getBody()->write(json_encode(['post' => $post, 'status' => 'success']));
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

// Create a new blog post
$app->post('/api/posts', function($request, $response) use ($pdo) {
    try {
        $data = $request->getParsedBody();
        
        // Validate required fields
        if (empty($data['title']) || empty($data['content']) || empty($data['author_id'])) {
            $response = $response->withStatus(400);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Title, content, and author_id are required'
            ]));
            return $response;
        }
        
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));
        }
        
        // Prepare tags as JSON if they're provided as an array
        $tags = isset($data['tags']) ? $data['tags'] : [];
        if (is_array($tags)) {
            $tags = json_encode($tags);
        }
        
        // Set default values
        $excerpt = isset($data['excerpt']) ? $data['excerpt'] : '';
        $featuredImage = isset($data['featured_image']) ? $data['featured_image'] : '';
        $published = isset($data['published']) ? (int)$data['published'] : 1;
        $now = date('Y-m-d H:i:s');
        
        // Insert the new post
        $stmt = $pdo->prepare('INSERT INTO blog_posts 
                              (title, slug, excerpt, content, featured_image, 
                               published_date, author_id, tags, published) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['title'], 
            $data['slug'], 
            $excerpt, 
            $data['content'], 
            $featuredImage,
            $now, 
            $data['author_id'], 
            $tags, 
            $published
        ]);
        
        $postId = $pdo->lastInsertId();
        
        $response = $response->withStatus(201);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Post created successfully',
            'postId' => $postId
        ]));
        return $response;
    } catch (PDOException $e) {
        $response = $response->withStatus(500);
        $response = $response->withHeader('Content-Type', 'application/json');
        $errorMessage = $e->getMessage();
        
        // Check for duplicate slug
        if (strpos($errorMessage, 'Duplicate entry') !== false && strpos($errorMessage, 'slug') !== false) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'A post with this slug already exists'
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

// Update a blog post
$app->put('/api/posts/{id}', function($request, $response, $args) use ($pdo) {
    try {
        $id = $args['id'];
        $data = $request->getParsedBody();
        
        // Check if post exists
        $stmt = $pdo->prepare('SELECT id FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $response = $response->withStatus(404);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Post not found'
            ]));
            return $response;
        }
        
        // Build update query dynamically based on provided fields
        $updateFields = [];
        $params = [];
        
        if (!empty($data['title'])) {
            $updateFields[] = 'title = ?';
            $params[] = $data['title'];
        }
        
        if (!empty($data['slug'])) {
            $updateFields[] = 'slug = ?';
            $params[] = $data['slug'];
        } else if (!empty($data['title']) && empty($data['slug'])) {
            // Auto-update slug if title changes and slug isn't explicitly provided
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));
            $updateFields[] = 'slug = ?';
            $params[] = $slug;
        }
        
        if (isset($data['excerpt'])) {
            $updateFields[] = 'excerpt = ?';
            $params[] = $data['excerpt'];
        }
        
        if (isset($data['content'])) {
            $updateFields[] = 'content = ?';
            $params[] = $data['content'];
        }
        
        if (isset($data['featured_image'])) {
            $updateFields[] = 'featured_image = ?';
            $params[] = $data['featured_image'];
        }
        
        if (isset($data['author_id'])) {
            $updateFields[] = 'author_id = ?';
            $params[] = $data['author_id'];
        }
        
        if (isset($data['tags'])) {
            $tags = $data['tags'];
            if (is_array($tags)) {
                $tags = json_encode($tags);
            }
            $updateFields[] = 'tags = ?';
            $params[] = $tags;
        }
        
        if (isset($data['published'])) {
            $updateFields[] = 'published = ?';
            $params[] = (int)$data['published'];
        }
        
        // Always update the updated_date
        $updateFields[] = 'updated_date = ?';
        $params[] = date('Y-m-d H:i:s');
        
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
        $sql = 'UPDATE blog_posts SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Post ' . $id . ' updated successfully'
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

// Delete a blog post
$app->delete('/api/posts/{id}', function($request, $response, $args) use ($pdo) {
    try {
        $id = $args['id'];
        
        // Check if post exists
        $stmt = $pdo->prepare('SELECT id FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $response = $response->withStatus(404);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Post not found'
            ]));
            return $response;
        }
        
        // Delete post
        $stmt = $pdo->prepare('DELETE FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Post ' . $id . ' deleted successfully'
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
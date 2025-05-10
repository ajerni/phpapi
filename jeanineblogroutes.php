<?php
/**
 * Blog Route definitions
 * 
 * Define all your blog API routes in this file
 */

require_once __DIR__ . '/basic_auth.php';

// GET all blog posts
$app->get('/api/jeanineposts', function($request, $response) use ($pdo) {
    // Handle CORS preflight
    $corsResponse = handleCORS($request, $response);
    if ($corsResponse) return $corsResponse;
    
    error_log("API /api/jeanineposts called"); // Debug log
    try {
        $params = $request->getQueryParams();
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        // Get total count for pagination
        $countStmt = $pdo->query('SELECT COUNT(*) FROM jeanine_blog_posts WHERE published = 1');
        $totalPosts = $countStmt->fetchColumn();
        
        // Query with pagination
        $query = 'SELECT id, title, slug, excerpt, content, featured_image, published_date, 
                  updated_date, tags FROM jeanine_blog_posts 
                  WHERE published = 1 
                  ORDER BY published_date DESC 
                  LIMIT ' . intval($limit) . ' OFFSET ' . intval($offset);
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse tags for each post if stored as JSON string
        foreach ($posts as &$post) {
            if (isset($post['tags']) && is_string($post['tags'])) {
                $post['tags'] = json_decode($post['tags'], true);
            }
            
            // Format dates for better frontend compatibility
            if (isset($post['published_date'])) {
                $date = new DateTime($post['published_date']);
                $post['published_date_formatted'] = $date->format('Y-m-d\TH:i:s\Z');
                $post['published_date_display'] = $date->format('F j, Y');
            }
            
            if (isset($post['updated_date']) && $post['updated_date']) {
                $date = new DateTime($post['updated_date']);
                $post['updated_date_formatted'] = $date->format('Y-m-d\TH:i:s\Z');
            }
        }
        
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
        error_log("Database error in /api/jeanineposts: " . $e->getMessage());
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
$app->get('/api/jeanineposts/{slug}', function($request, $response, $args) use ($pdo) {
    // Handle CORS preflight
    $corsResponse = handleCORS($request, $response);
    if ($corsResponse) return $corsResponse;
    
    try {
        $slug = $args['slug'];
        error_log("Getting post with slug: " . $slug);
        
        $stmt = $pdo->prepare('SELECT id, title, slug, excerpt, content, featured_image, 
                              published_date, updated_date, tags 
                              FROM jeanine_blog_posts
                              WHERE slug = ? AND published = 1');
        $stmt->execute([$slug]);
        $post = $stmt->fetch();
        
        $response = $response->withHeader('Content-Type', 'application/json');
        
        if (!$post) {
            error_log("Post not found with slug: " . $slug);
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
        
        // Format dates for better frontend compatibility
        if (isset($post['published_date'])) {
            $date = new DateTime($post['published_date']);
            $post['published_date_formatted'] = $date->format('Y-m-d\TH:i:s\Z');
            $post['published_date_display'] = $date->format('F j, Y');
        }
        
        if (isset($post['updated_date']) && $post['updated_date']) {
            $date = new DateTime($post['updated_date']);
            $post['updated_date_formatted'] = $date->format('Y-m-d\TH:i:s\Z');
        }
        
        error_log("Returning post data for slug: " . $slug);
        $response->getBody()->write(json_encode(['post' => $post, 'status' => 'success']));
        return $response;
    } catch (PDOException $e) {
        error_log("Database error in /api/jeanineposts/{slug}: " . $e->getMessage());
        $response = $response->withStatus(500);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]));
        return $response;
    }
});

// GET tags for filtering
$app->get('/api/jeaninetags', function($request, $response) use ($pdo) {
    // Handle CORS preflight
    $corsResponse = handleCORS($request, $response);
    if ($corsResponse) return $corsResponse;
    
    try {
        // Get all tags from published posts
        $stmt = $pdo->query('SELECT tags FROM jeanine_blog_posts WHERE published = 1');
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Extract and merge all tags
        $allTags = [];
        foreach ($result as $row) {
            if (isset($row['tags']) && $row['tags']) {
                $tags = is_string($row['tags']) ? json_decode($row['tags'], true) : $row['tags'];
                if (is_array($tags)) {
                    foreach ($tags as $tag) {
                        if (!in_array($tag, $allTags)) {
                            $allTags[] = $tag;
                        }
                    }
                }
            }
        }
        
        // Sort tags alphabetically
        sort($allTags);
        
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'tags' => $allTags,
            'status' => 'success'
        ]));
        return $response;
    } catch (PDOException $e) {
        error_log("Database error in /api/tags: " . $e->getMessage());
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
$app->post('/api/jeanineposts', function($request, $response) use ($pdo) {
    // Handle CORS preflight
    $corsResponse = handleCORS($request, $response);
    if ($corsResponse) return $corsResponse;

    requireBasicAuth($request, $response);
    
    try {
        $data = $request->getParsedBody();
        error_log("Received data for POST /api/jeaineposts: " . json_encode($data));
        
        // Validate required fields
        if (empty($data['title']) || empty($data['content'])) {
            $response = $response->withStatus(400);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Title and content are required'
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
        $stmt = $pdo->prepare('INSERT INTO jeanine_blog_posts 
                              (title, slug, excerpt, content, featured_image, 
                               published_date, tags, published) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['title'], 
            $data['slug'], 
            $excerpt, 
            $data['content'], 
            $featuredImage,
            $now, 
            $tags, 
            $published
        ]);
        
        $postId = $pdo->lastInsertId();
        error_log("Created new post with ID: " . $postId);
        
        $response = $response->withStatus(201);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Post created successfully',
            'postId' => $postId,
            'slug' => $data['slug']
        ]));
        return $response;
    } catch (PDOException $e) {
        error_log("Database error in POST /api/jeanineposts: " . $e->getMessage());
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
$app->put('/api/jeanineposts/{id}', function($request, $response, $args) use ($pdo) {
    // Handle CORS preflight
    $corsResponse = handleCORS($request, $response);
    if ($corsResponse) return $corsResponse;

    requireBasicAuth($request, $response);
    
    try {
        $id = $args['id'];
        $data = $request->getParsedBody();
        error_log("Updating post ID: " . $id . " with data: " . json_encode($data));
        
        // Check if post exists
        $stmt = $pdo->prepare('SELECT id FROM jeanine_blog_posts WHERE id = ?');
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
        $sql = 'UPDATE jeanine_blog_posts SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        error_log("Updated post ID: " . $id);
        
        // Get the updated slug to return
        $updatedSlug = "";
        if (!empty($data['slug'])) {
            $updatedSlug = $data['slug'];
        } elseif (!empty($data['title'])) {
            $updatedSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));
        } else {
            $stmtSlug = $pdo->prepare('SELECT slug FROM jeanine_blog_posts WHERE id = ?');
            $stmtSlug->execute([$id]);
            $postData = $stmtSlug->fetch();
            if ($postData && isset($postData['slug'])) {
                $updatedSlug = $postData['slug'];
            }
        }
        
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Post ' . $id . ' updated successfully',
            'slug' => $updatedSlug
        ]));
        return $response;
    } catch (PDOException $e) {
        error_log("Database error in PUT /api/jeanineposts/{id}: " . $e->getMessage());
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

// Delete a blog post
$app->delete('/api/jeanineposts/{id}', function($request, $response, $args) use ($pdo) {
    // Handle CORS preflight
    $corsResponse = handleCORS($request, $response);
    if ($corsResponse) return $corsResponse;

    requireBasicAuth($request, $response);
    
    try {
        $id = $args['id'];
        error_log("Deleting post ID: " . $id);
        
        // Check if post exists
        $stmt = $pdo->prepare('SELECT id FROM jeanine_blog_posts WHERE id = ?');
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
        $stmt = $pdo->prepare('DELETE FROM jeanine_blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        error_log("Deleted post ID: " . $id);
        
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Post ' . $id . ' deleted successfully'
        ]));
        return $response;
    } catch (PDOException $e) {
        error_log("Database error in DELETE /api/jeanineposts/{id}: " . $e->getMessage());
        $response = $response->withStatus(500);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]));
        return $response;
    }
}); 
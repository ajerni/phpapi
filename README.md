# Lightweight PHP Routing Framework

A minimalist PHP routing framework inspired by Slim that provides a simple way to build REST APIs with zero external dependencies. Runs on every simple Appache server without further installatioins.

## Live Demo running at:
[https://phpapi.andierni.ch](https://phpapi.andierni.ch)

## Features

- **Zero Dependencies**: No Composer or external libraries required
- **Simple API**: Clean, intuitive route definitions
- **REST Ready**: Built-in support for GET, POST, PUT, PATCH, and DELETE methods
- **URL Parameters**: Easy parameter extraction from URLs (e.g., `/users/{id}`)
- **Separation of Concerns**: Core framework code separated from route definitions
- **Single File Deployment**: Can be deployed as a standalone solution

## Getting Started

1. Upload the following files to your server:
   - `serve.php` - Core framework
   - `routes.php` - Your API route definitions (the only file you need to work on depending on your needs)
   - `index.php` - Simple entry point
   - `.htaccess` - URL rewriting for Apache

2. Update your Database settings:
   - `config_censored.php` - Add your DB connection settings here and rename the file to `censored.php` and upload it to your server.

3. Optional - Configure your web server (usually already done by your provider):
   - Apache: Ensure mod_rewrite is enabled
   - Nginx: Configure URL rewriting to route requests to index.php

## Example Usage (how to make your own routes)

Define routes in `routes.php`:

```php
// GET request to retrieve all users
$app->get('/api/users', function($request, $response) {
    $response->withHeader('Content-Type', 'application/json');
    $users = [
        ['id' => 1, 'name' => 'John Doe'],
        ['id' => 2, 'name' => 'Jane Smith']
    ];
    $response->getBody()->write(json_encode(['users' => $users]));
    return $response;
});

// Basic parameter example
$app->get('/hello/{name}', function($request, $response, $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    return $response;
});
```

## API Reference

### Basic Routes

#### Root Route
```
GET /

# Response
Welcome to the lightweight PHP routing framework!
```

#### Hello Route with Parameter
```
GET /hello/John

# Response
Hello, John
```

### User Management API

#### Get All Users
```bash
# Request
GET /api/users

# Response
{
  "users": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "created_at": "2023-10-15 12:34:56"
    },
    {
      "id": 2,
      "name": "Jane Smith",
      "email": "jane@example.com",
      "created_at": "2023-10-16 09:12:34"
    }
  ],
  "status": "success"
}
```

#### Get Single User
```bash
# Request
GET /api/users/1

# Success Response
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2023-10-15 12:34:56"
  },
  "status": "success"
}

# Error Response (User not found)
{
  "status": "error",
  "message": "User not found"
}
```

#### Create User
```bash
# Request
POST /api/users
Content-Type: application/json

{
  "name": "Alice Brown",
  "email": "alice@example.com",
  "password": "securepassword123"
}

# Success Response
{
  "status": "success",
  "message": "User created successfully",
  "userId": 3
}

# Error Response (Missing fields)
{
  "status": "error",
  "message": "Name, email and password are required"
}

# Error Response (Duplicate email)
{
  "status": "error",
  "message": "Email already exists"
}
```

#### Update User
```bash
# Request
PUT /api/users/1
Content-Type: application/json

{
  "name": "John Doe Updated",
  "email": "john.updated@example.com"
}

# Success Response
{
  "status": "success",
  "message": "User 1 updated successfully"
}

# Error Response (User not found)
{
  "status": "error",
  "message": "User not found"
}

# Error Response (No fields to update)
{
  "status": "error",
  "message": "No fields to update"
}
```

#### Delete User
```bash
# Request
DELETE /api/users/1

# Success Response
{
  "status": "success",
  "message": "User 1 deleted successfully"
}

# Error Response (User not found)
{
  "status": "error",
  "message": "User not found"
}
```

## Database Setup

Create the users table in your MySQL database:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Optional: Add some initial test data
INSERT INTO users (name, email, password) VALUES 
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Jane Smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
```

## Testing the API

You can use curl, Postman, or any HTTP client to test the API endpoints. Here are some curl examples:

```bash
# Get all users
curl -X GET http://localhost/api/users

# Get a specific user
curl -X GET http://localhost/api/users/1

# Create a new user
curl -X POST http://localhost/api/users \
  -H "Content-Type: application/json" \
  -d '{"name":"Bob Johnson","email":"bob@example.com","password":"password123"}'

# Update a user
curl -X PUT http://localhost/api/users/3 \
  -H "Content-Type: application/json" \
  -d '{"name":"Bob Updated"}'

# Delete a user
curl -X DELETE http://localhost/api/users/3
```

## Benefits

- **Lightweight**: Minimal overhead, fast execution
- **Easy to Understand**: Simple codebase that's easy to modify
- **No Vendor Lock-in**: No external dependencies means no breaking changes from third parties
- **Educational**: Great for learning how routing frameworks work
- **Quick Setup**: Get a REST API running in minutes without complex configuration 
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Lightweight PHP Routing Framework
 */

// PSR-7 Interfaces
interface ResponseInterface {
    public function getBody();
    public function withStatus($code, $reasonPhrase = '');
    public function withHeader($name, $value);
}

class Response implements ResponseInterface {
    private $body = '';
    private $statusCode = 200;
    private $headers = [];

    public function getBody() {
        return $this;
    }

    public function write($content) {
        $this->body .= $content;
    }

    public function withStatus($code, $reasonPhrase = '') {
        $this->statusCode = $code;
        return $this;
    }

    public function withHeader($name, $value) {
        $this->headers[$name] = [$value];
        return $this;
    }

    public function withAddedHeader($name, $value) {
        if (!isset($this->headers[$name])) {
            $this->headers[$name] = [];
        }
        $this->headers[$name][] = $value;
        return $this;
    }

    public function send() {
        http_response_code($this->statusCode);
        
        foreach ($this->headers as $name => $values) {
            foreach ($values as $value) {
                header("$name: $value");
            }
        }
        
        echo $this->body;
    }
}

interface ServerRequestInterface {
    public function getUri();
    public function getMethod();
    public function getAttribute($name, $default = null);
    public function getParsedBody();
}

class Request implements ServerRequestInterface {
    private $uri;
    private $method;
    private $attributes = [];
    private $parsedBody = null;
    
    public function __construct() {
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Parse request body for POST, PUT, and PATCH requests
        if (in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->parseBody();
        }
    }
    
    private function parseBody() {
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        
        // Parse JSON content
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                $this->parsedBody = json_decode($input, true);
                error_log('JSON parsed body in Request class: ' . json_encode($this->parsedBody));
            }
        } 
        // Parse form data
        else if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            $this->parsedBody = $_POST;
        }
        // Parse multipart form data (also handled by $_POST)
        else if (strpos($contentType, 'multipart/form-data') !== false) {
            $this->parsedBody = $_POST;
        }
    }
    
    public function getUri() {
        return $this->uri;
    }
    
    public function getMethod() {
        return $this->method;
    }
    
    public function getAttribute($name, $default = null) {
        return $this->attributes[$name] ?? $default;
    }
    
    public function withAttribute($name, $value) {
        $this->attributes[$name] = $value;
        return $this;
    }
    
    public function getParsedBody() {
        return $this->parsedBody;
    }

    // Add this method to support getQueryParams in blogroutes.php
    public function getQueryParams() {
        return $_GET;
    }
}

class App {
    private $routes = [];
    
    public function get($pattern, $callback) {
        $this->routes['GET'][$pattern] = $callback;
        return $this;
    }
    
    public function post($pattern, $callback) {
        $this->routes['POST'][$pattern] = $callback;
        return $this;
    }

    public function put($pattern, $callback) {
        $this->routes['PUT'][$pattern] = $callback;
        return $this;
    }
    
    public function patch($pattern, $callback) {
        $this->routes['PATCH'][$pattern] = $callback;
        return $this;
    }
    
    public function delete($pattern, $callback) {
        $this->routes['DELETE'][$pattern] = $callback;
        return $this;
    }
    
    public function run() {
        $request = new Request();
        $response = new Response();
        $method = $request->getMethod();
        $uri = $request->getUri();
        
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        $uri = rtrim($uri, '/');
        if (empty($uri)) {
            $uri = '/';
        }
        
        if (isset($this->routes[$method][$uri])) {
            $callback = $this->routes[$method][$uri];
            $callback($request, $response, []);
            $response->send();
            return;
        }
        
        foreach ($this->routes[$method] ?? [] as $pattern => $callback) {
            if (strpos($pattern, '{') !== false) {
                $patternRegex = preg_replace('/{([^\/]+)}/', '([^/]+)', $pattern);
                $patternRegex = '#^' . $patternRegex . '$#';
                
                if (preg_match($patternRegex, $uri, $matches)) {
                    array_shift($matches);
                    
                    preg_match_all('/{([^\/]+)}/', $pattern, $paramNames);
                    $params = [];
                    
                    foreach ($paramNames[1] as $index => $name) {
                        $params[$name] = $matches[$index];
                    }
                    
                    $callback($request, $response, $params);
                    $response->send();
                    return;
                }
            }
        }
        
        $response->withStatus(404);
        $response->getBody()->write('404 - Not Found');
        $response->send();
    }
}

// Create app instance
$app = new App();

// Add CORS middleware if the file exists
if (file_exists(__DIR__ . '/cors.php')) {
    require_once __DIR__ . '/cors.php';
}

// Include route definitions (add your own here)
require_once 'routes.php';
// Include blog routes
require_once 'blogroutes.php';
// Include Jeanine's blog routes
require_once 'jeanineblogroutes.php';

// Run application
$app->run(); 
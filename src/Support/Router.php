<?php
declare(strict_types=1);

/**
 * Routeur HTTP léger et déclaratif inspiré de Laravel
 */
class Router {
    private array $routes = [];

    /**
     * Enregistre une route GET
     * @param string $path
     * @param array{0: string, 1: string}|callable $handler [ControllerClass, 'methodName'] ou Closure
     */
    public function get(string $path, array|callable $handler): void {
        $this->add('GET', $path, $handler);
    }

    /**
     * Enregistre une route POST
     */
    public function post(string $path, array|callable $handler): void {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, array|callable $handler): void {
        $normalized = '/' . trim($path, '/');
        $this->routes[] = [
            'method'  => strtoupper($method),
            'path'    => $normalized === '//' ? '/' : $normalized,
            'handler' => $handler
        ];
    }

    /**
     * Analyse et exécute la requête HTTP courante
     */
    public function dispatch(?string $method = null, ?string $path = null): void {
        $request_method = strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        
        if ($path === null) {
            $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
            $parsed_url = parse_url($request_uri);
            $raw_path = $parsed_url['path'] ?? '/';

            // Retirer le préfixe BASE_URL si installé en sous-dossier
            $base_url = get_base_url();
            if ($base_url !== '' && str_starts_with($raw_path, $base_url)) {
                $raw_path = substr($raw_path, strlen($base_url));
            }
            $path = '/' . trim($raw_path, '/');
        }

        $cleanPath = $path === '//' ? '/' : $path;

        foreach ($this->routes as $route) {
            if ($route['method'] === $request_method && $route['path'] === $cleanPath) {
                $this->execute($route['handler']);
                return;
            }
        }

        // 404 Non Trouvé
        http_response_code(404);
        $page_title = "Page introuvable";
        $error_message = "La page que vous recherchez n'existe pas ou a été déplacée.";
        require dirname(__DIR__, 2) . '/views/error.php';
    }

    private function execute(array|callable $handler): void {
        if (is_callable($handler) && !is_array($handler)) {
            $handler();
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = new $class();
            $controller->$method();
            return;
        }

        throw new \RuntimeException("Gestionnaire de route non valide");
    }
}

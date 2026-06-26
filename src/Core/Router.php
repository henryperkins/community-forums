<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Micro-router. Routes are registered as (method, pattern, handler). Patterns
 * use {id} (digits only) and {name} ([^/]+) placeholders, e.g.
 * "/t/{id}-{slug}". Handlers are [ControllerClass, 'method'] pairs.
 */
final class Router
{
    /** @var array<int, array{method:string, regex:string, params:list<string>, handler:array{0:string,1:string}, name:?string}> */
    private array $routes = [];

    /** @param array{0:string,1:string} $handler */
    public function add(string $method, string $pattern, array $handler, ?string $name = null): self
    {
        [$regex, $params] = $this->compile($pattern);
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => $regex,
            'params' => $params,
            'handler' => $handler,
            'name' => $name,
        ];
        return $this;
    }

    /** @param array{0:string,1:string} $handler */
    public function get(string $pattern, array $handler, ?string $name = null): self
    {
        return $this->add('GET', $pattern, $handler, $name);
    }

    /** @param array{0:string,1:string} $handler */
    public function post(string $pattern, array $handler, ?string $name = null): self
    {
        return $this->add('POST', $pattern, $handler, $name);
    }

    /**
     * Match a request. Returns [handler, params] on success.
     *
     * @return array{0:array{0:string,1:string}, 1:array<string,string>}
     * @throws NotFoundException when no path matches
     * @throws HttpException 405 when the path matches but not the method
     */
    public function match(string $method, string $path): array
    {
        $method = strtoupper($method);
        // HEAD is handled by the matching GET route (the SAPI drops the body).
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $m) !== 1) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }
            $params = [];
            foreach ($route['params'] as $name) {
                $params[$name] = $m[$name] ?? '';
            }
            return [$route['handler'], $params];
        }

        if ($pathMatched) {
            throw new HttpException(405, 'Method not allowed');
        }
        throw new NotFoundException();
    }

    /**
     * @return array{0:string, 1:list<string>} compiled regex and param names
     */
    private function compile(string $pattern): array
    {
        $params = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function (array $m) use (&$params): string {
                $name = $m[1];
                $params[] = $name;
                $sub = $name === 'id' ? '\d+' : '[^/]+';
                return '(?P<' . $name . '>' . $sub . ')';
            },
            $pattern,
        );

        return ['#^' . $regex . '$#', $params];
    }
}

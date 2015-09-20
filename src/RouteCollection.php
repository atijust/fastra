<?php
namespace Fastra;

class RouteCollection
{
    /** @var array */
    private $elements = [];

    /** @var string */
    private $prefix = '';

    /** @var string[]|callable[] */
    private $middleware = [];

    /**
     * @param callable $callback
     * @return \Fastra\RouteCollection
     */
    public function group(callable $callback)
    {
        $subCollection = new static();

        $this->elements[] = $subCollection;

        call_user_func($callback, $subCollection);

        return $subCollection;
    }

    /**
     * @param string $prefix
     * @return $this
     */
    public function prefix($prefix)
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * @param string|string[]|callable|callable[] $middleware
     * @return $this
     */
    public function middleware($middleware)
    {
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }

        $this->middleware = array_merge($this->middleware, $middleware);

        return $this;
    }

    /**
     * @param string|string[] $methods
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function route($methods, $path, $handler)
    {
        if (!is_array($methods)) {
            $methods = [$methods];
        }

        $route = new Route($methods, $path, $handler);

        $this->elements[] = $route;

        return $route;
    }

    /**
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function get($path, $handler)
    {
        return $this->route('GET', $path, $handler);
    }

    /**
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function post($path, $handler)
    {
        return $this->route('POST', $path, $handler);
    }

    /**
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function put($path, $handler)
    {
        return $this->route('PUT', $path, $handler);
    }

    /**
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function delete($path, $handler)
    {
        return $this->route('DELETE', $path, $handler);
    }

    /**
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function options($path, $handler)
    {
        return $this->route('OPTIONS', $path, $handler);
    }

    /**
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function patch($path, $handler)
    {
        return $this->route('PATCH', $path, $handler);
    }

    /**
     * @return \Fastra\Route[]
     */
    public function getRoutes()
    {
        /** @var \Fastra\Route[] $routes */
        $routes = [];

        foreach ($this->elements as $element) {
            if ($element instanceof RouteCollection) {
                $routes = array_merge($routes, $element->getRoutes());
            } else {
                $routes[] = clone $element;
            }
        }

        foreach ($routes as $route) {
            $route->prefix($this->prefix);
            $route->middleware($this->middleware);
        }

        return $routes;
    }
}

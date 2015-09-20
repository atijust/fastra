<?php
namespace Fastra;

class Route
{
    /** @var string[] */
    public $methods;

    /** @var string */
    public $path;

    /** @var string|callable */
    public $handler;

    /** @var string[] */
    public $middleware = [];

    /**
     * @param array $properties
     * @return static
     */
    public static function __set_state(array $properties)
    {
        $route = new static($properties['methods'], $properties['path'], $properties['handler']);

        $route->middleware($properties['middleware']);

        return $route;
    }

    /**
     * @param string[] $methods
     * @param string $path
     * @param string|callable $handler
     */
    public function __construct(array $methods, $path, $handler)
    {
        $this->methods = $methods;
        $this->path = $path;
        $this->handler = $handler;
    }

    /**
     * @param string $prefix
     * @return $this
     */
    public function prefix($prefix)
    {
        $this->path = $prefix . $this->path;
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
}

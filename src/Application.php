<?php
namespace Fastra;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Illuminate\Container\Container;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class Application extends Container implements HttpKernelInterface
{
    /** @var \Fastra\ServiceProviderInterface[] */
    private $providers = [];

    /** @var \Fastra\RouteCollection */
    private $routeCollection;

    /** @var callable|string */
    private $exceptionHandler;

    /**
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        static::setInstance($this);

        $this->instance('kernel', $this);

        $this->alias('kernel', Application::class);

        $this->singleton('request', function () {
            throw new \RuntimeException('Outside of request scope.');
        });

        $this->alias('request', Request::class);

        $this['debug'] = false;

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }

        $this->routeCollection = new RouteCollection();
    }

    /**
     * @param \Fastra\ServiceProviderInterface $provider
     * @param array $values
     */
    public function register(ServiceProviderInterface $provider, array $values = [])
    {
        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }

        $provider->register($this);

        $this->providers[] = $provider;
    }

    /**
     * @return $this
     */
    public function boot()
    {
        foreach ($this->providers as $provider) {
            $provider->boot($this);
        }

        return $this;
    }

    /**
     * @param callable $callback
     * @return \Fastra\RouteCollection
     */
    public function group(callable $callback)
    {
        return $this->routeCollection->group($callback);
    }

    /**
     * @param string|string[] $method
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function route($method, $path, $handler)
    {
        return $this->routeCollection->route($method, $path, $handler);
    }

    /**
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function get($path, $handler)
    {
        return $this->routeCollection->get($path, $handler);
    }

    /**
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function post($path, $handler)
    {
        return $this->routeCollection->post($path, $handler);
    }

    /**
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function put($path, $handler)
    {
        return $this->routeCollection->put($path, $handler);
    }

    /**
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function delete($path, $handler)
    {
        return $this->routeCollection->delete($path, $handler);
    }

    /**
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function options($path, $handler)
    {
        return $this->routeCollection->options($path, $handler);
    }

    /**
     * @param string $path
     * @param string|callable $handler
     * @return \Fastra\Route
     */
    public function patch($path, $handler)
    {
        return $this->routeCollection->patch($path, $handler);
    }

    /**
     * @param callable $exceptionHandler
     * @return void
     */
    public function exception(callable $exceptionHandler)
    {
        $this->exceptionHandler = $exceptionHandler;
    }

    /**
     * @return void
     */
    public function run()
    {
        $this->make('kernel')->handle(Request::createFromGlobals())->send();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        $this->boot();

        $this->instance('request', $request);

        try {
            return $this->handleRaw($request);
        } catch (\Exception $e) {
            if ($catch) {
                return $this->handleException($e);
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function handleRaw(Request $request)
    {
        $routeInfo = $this->createDispatcher()->dispatch(
            $request->getMethod(),
            $request->getPathInfo()
        );

        switch ($routeInfo[0]) {
            case Dispatcher::FOUND:
                return $this->callRoute($routeInfo[1], $request, $routeInfo[2]);
            case Dispatcher::NOT_FOUND:
                throw new NotFoundHttpException();
            case Dispatcher::METHOD_NOT_ALLOWED:
                throw new MethodNotAllowedHttpException($routeInfo[1]);
        }
    }

    /**
     * @param \Exception $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function handleException(\Exception $e)
    {
        if ($this->exceptionHandler) {
            return call_user_func($this->exceptionHandler, $e);
        }

        return (new ExceptionHandler($this['debug']))->createResponse($e);
    }

    /**
     * @return \FastRoute\Dispatcher
     */
    private function createDispatcher()
    {
        $routeDefinition = function (RouteCollector $collector) {
            foreach ($this->routeCollection->getRoutes() as $route) {
                $collector->addRoute($route->methods, $route->path, $route);
            }
        };

        if (isset($this['route.cache_file'])) {
            return \FastRoute\cachedDispatcher($routeDefinition, [
                'cacheFile' => $this['route.cache_file'],
                'cacheDisabled' => $this['debug'],
            ]);
        } else {
            return \FastRoute\simpleDispatcher($routeDefinition);
        }
    }

    /**
     * @param \Fastra\Route $route
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param array $params
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function callRoute(Route $route, Request $request, array $params)
    {
        $action = function (Request $request) use ($route, $params) {
            $this->instance('request', $request);
            $response = $this->call($route->handler, $params);
            if ($response instanceof Response) {
                return $response;
            } else {
                return Response::create((string)$response);
            }
        };

        $reducer = function ($next, $middleware) {
            return function (Request $request) use ($next, $middleware) {
                if (is_callable($middleware)) {
                    return call_user_func($middleware, $request, $next);
                } else {
                    return $this->make($middleware)->handle($request, $next);
                }
            };
        };

        $callable = array_reduce(array_reverse($route->middleware), $reducer, $action);

        return call_user_func($callable, $request);
    }
}
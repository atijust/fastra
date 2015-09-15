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

    /** @var array */
    private $routes = [];

    /** @var callable|string */
    private $exceptionHandler = null;

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
     * @param string|string[] $method
     * @param string $route
     * @param string|callable $handler
     * @return void
     */
    public function route($method, $route, $handler)
    {
        $this->routes[] = [$method, $route, $handler];
    }

    /**
     * @param string $route
     * @param string|callable $handler
     * @return void
     */
    public function get($route, $handler)
    {
        $this->route('GET', $route, $handler);
    }

    /**
     * @param string $route
     * @param string|callable $handler
     * @return void
     */
    public function post($route, $handler)
    {
        $this->route('POST', $route, $handler);
    }

    /**
     * @param string $route
     * @param string|callable $handler
     * @return void
     */
    public function put($route, $handler)
    {
        $this->route('PUT', $route, $handler);
    }

    /**
     * @param string $route
     * @param string|callable $handler
     * @return void
     */
    public function delete($route, $handler)
    {
        $this->route('DELETE', $route, $handler);
    }

    /**
     * @param string $route
     * @param string|callable $handler
     * @return void
     */
    public function options($route, $handler)
    {
        $this->route('OPTIONS', $route, $handler);
    }

    /**
     * @param string $route
     * @param string|callable $handler
     * @return void
     */
    public function patch($route, $handler)
    {
        $this->route('PATCH', $route, $handler);
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
                $response = $this->call($routeInfo[1], $routeInfo[2]);
                if ($response instanceof Response) {
                    return $response;
                } else {
                    return Response::create((string)$response);
                }
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
        $routeDefinition = function (RouteCollector $router) {
            foreach ($this->routes as list($method, $route, $handler)) {
                $router->addRoute($method, $route, $handler);
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
}
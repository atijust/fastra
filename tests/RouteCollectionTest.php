<?php

use Fastra\Route;
use Fastra\RouteCollection;

class RouteCollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testRoute()
    {
        $router = new RouteCollection();
        $router->route(['GET', 'POST'], '/', 'handler');

        $routes = $router->getRoutes();

        $this->assertRoute(['GET', 'POST'], '/', 'handler', [], reset($routes));
        $this->assertFalse(next($routes));
    }

    public function testGet()
    {
        $router = new RouteCollection();
        $router->get('/', 'handler');

        $routes = $router->getRoutes();

        $this->assertRoute('GET', '/', 'handler', [], reset($routes));
        $this->assertFalse(next($routes));
    }

    public function testGroup()
    {
        $router = new RouteCollection();

        $router->route('GET', '/path1', 'handler1');
        $router->route('GET', '/path2', 'handler2');

        $router->group(function (RouteCollection $router) {
            $router->route('GET', '/path3', 'handler3');
            $router->route('GET', '/path4', 'handler4');
        })->prefix('/prefix1');

        $router->group(function (RouteCollection $router) {
            $router->route('GET', '/path5', 'handler5');
            $router->route('GET', '/path6', 'handler6');
        })->prefix('/prefix2');

        $router->route('GET', '/path7', 'handler7');
        $router->route('GET', '/path8', 'handler8');

        $routes = $router->getRoutes();

        $this->assertRoute('GET', '/path1', 'handler1', [], reset($routes));
        $this->assertRoute('GET', '/path2', 'handler2', [], next($routes));
        $this->assertRoute('GET', '/prefix1/path3', 'handler3', [], next($routes));
        $this->assertRoute('GET', '/prefix1/path4', 'handler4', [], next($routes));
        $this->assertRoute('GET', '/prefix2/path5', 'handler5', [], next($routes));
        $this->assertRoute('GET', '/prefix2/path6', 'handler6', [], next($routes));
        $this->assertRoute('GET', '/path7', 'handler7', [], next($routes));
        $this->assertRoute('GET', '/path8', 'handler8', [], next($routes));
        $this->assertFalse(next($routes));
    }

    public function testGroupNest()
    {
        $router = new RouteCollection();

        $router->group(function (RouteCollection $router) {
            $router->group(function (RouteCollection $router) {
                $router->route('GET', '/path', 'handler');
            })->prefix('/prefix2');
        })->prefix('/prefix1');

        $routes = $router->getRoutes();

        $this->assertRoute('GET', '/prefix1/prefix2/path', 'handler', [], reset($routes));
        $this->assertFalse(next($routes));
    }

    public function testRepeatGetRoute()
    {
        $router = new RouteCollection();

        $router->group(function (RouteCollection $router) {
            $router->route('GET', '/path', 'handler')->middleware('m1');
        })->middleware('m2')->prefix('/prefix');


        for ($i = 1; $i < 2; $i++) {
            $routes = $router->getRoutes();
            $this->assertRoute('GET', '/prefix/path', 'handler', ['m1', 'm2'], reset($routes));
            $this->assertFalse(next($routes));
        }
    }

    public function testMiddleware()
    {
        $router = new RouteCollection();

        $router->group(function (RouteCollection $router) {
            $router->group(function (RouteCollection $router) {
                $router->route('GET', '/path', 'handler')->middleware(['m1', 'm2']);
            })->middleware(['m3', 'm4']);
        })->middleware(['m5', 'm6']);

        $routes = $router->getRoutes();

        $this->assertRoute('GET', '/path', 'handler', ['m1', 'm2', 'm3', 'm4', 'm5', 'm6'], reset($routes));
        $this->assertFalse(next($routes));

        $router = new RouteCollection();

        $router->group(function (RouteCollection $router) {
            $router->group(function (RouteCollection $router) {
                $router->route('GET', '/path', 'handler')->middleware('m1')->middleware('m2');
            })->middleware('m3')->middleware('m4');
        })->middleware('m5')->middleware('m6');

        $routes = $router->getRoutes();

        $this->assertRoute('GET', '/path', 'handler', ['m1', 'm2', 'm3', 'm4', 'm5', 'm6'], reset($routes));
        $this->assertFalse(next($routes));
    }

    private function assertRoute($methods, $path, $handler, array $middleware, Route $route)
    {
        if (!is_array($methods)) {
            $methods = [$methods];
        }

        $this->assertSame($methods, $route->methods);
        $this->assertSame($path, $route->path);
        $this->assertSame($handler, $route->handler);
        $this->assertSame($middleware, $route->middleware);
    }
}
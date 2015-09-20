<?php

use Fastra\Route;

class RouteTest extends \PHPUnit_Framework_TestCase
{
    public function testVarExport()
    {
        $route = new Route(['GET'], '/path', 'handler');
        $route->prefix('/prefix');
        $route->middleware('middleware');

        $cache = eval('return ' . var_export($route, true) . ';');

        $this->assertEquals($route, $cache);
    }
}
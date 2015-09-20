<?php

use Fastra\Application;
use Fastra\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $values = ['key' => 'value'];
        $app = new Application($values);
        $this->assertSame($values['key'], $app['key']);
    }

    public function testServiceProviderRegistrationAndBootstrap()
    {
        $mock = $this->getMockBuilder(ServiceProviderInterface::class)
            ->setMethods(['register', 'boot'])
            ->getMock();

        $app = new Application();

        $values = ['key' => 'value'];

        $mock->expects($this->once())
            ->method('register')
            ->with($this->callback(function ($subject) use ($app, $values) {
                return $subject === $app && $subject['key'] === $values['key'];
            }));

        $app->register($mock, $values);

        $mock->expects($this->once())
            ->method('boot')
            ->with($this->identicalTo($app));

        $app->boot();
    }

    public function testOutsideOfRequestScope()
    {
        $this->setExpectedException(\RuntimeException::class);
        $app = new Application();
        $app->make('request');
    }

    public function testClassMiddleware()
    {
        $app = new Application();
        $app->get('/', function (Request $request) {
            return $request->get('p');
        })->middleware(TestMiddleware::class);

        $request = Request::create('/', 'GET');
        $this->assertSame('foobar', $app->handle($request)->getContent());
    }

    public function testClosureMiddleware()
    {
        $app = new Application();
        $app->get('/', function (Request $request) {
            return $request->get('p');
        })->middleware(function (Request $request, $next) {
            $request->query->set('p', 'foo');
            return Response::create($next($request)->getContent() . 'bar');
        });

        $request = Request::create('/', 'GET');
        $this->assertSame('foobar', $app->handle($request)->getContent());
    }

    public function testGroup()
    {
        $app = new Application();
        $app->group(function ($r) {
            $r->get('/path1', function () { return 'path1'; });
            $r->get('/path2', function () { return 'path2'; });
        })->prefix('/prefix');

        $request = Request::create('/prefix/path1', 'GET');
        $this->assertSame('path1', $app->handle($request)->getContent());

        $request = Request::create('/prefix/path2', 'GET');
        $this->assertSame('path2', $app->handle($request)->getContent());
    }

    public function testRoute()
    {
        $app = new Application();
        $app->route('GET', '/', function () {
            return 'OK';
        });

        $request = Request::create('/', 'GET');
        $this->assertSame('OK', $app->handle($request)->getContent());
    }

    public function testGetRequest()
    {
        $request = Request::create('/', 'GET');

        $app = new Application();
        $app->get('/', function (Request $r) use ($request) {
            return $r === $request ? 'OK' : 'NG';
        });

        $this->assertSame('OK', $app->handle($request)->getContent());
    }

    public function testPostRequest()
    {
        $request = Request::create('/', 'POST');

        $app = new Application();
        $app->post('/', function (Request $r) use ($request) {
            return $r === $request ? 'OK' : 'NG';
        });

        $this->assertSame('OK', $app->handle($request)->getContent());
    }

    public function testPutRequest()
    {
        $request = Request::create('/', 'PUT');

        $app = new Application();
        $app->put('/', function (Request $r) use ($request) {
            return $r === $request ? 'OK' : 'NG';
        });

        $this->assertSame('OK', $app->handle($request)->getContent());
    }

    public function testDeleteRequest()
    {
        $request = Request::create('/', 'DELETE');

        $app = new Application();
        $app->delete('/', function (Request $r) use ($request) {
            return $r === $request ? 'OK' : 'NG';
        });

        $this->assertSame('OK', $app->handle($request)->getContent());
    }

    public function testOptionsRequest()
    {
        $request = Request::create('/', 'OPTIONS');

        $app = new Application();
        $app->options('/', function (Request $r) use ($request) {
            return $r === $request ? 'OK' : 'NG';
        });

        $this->assertSame('OK', $app->handle($request)->getContent());
    }

    public function testPatchRequest()
    {
        $request = Request::create('/', 'PATCH');

        $app = new Application();
        $app->patch('/', function (Request $r) use ($request) {
            return $r === $request ? 'OK' : 'NG';
        });

        $this->assertSame('OK', $app->handle($request)->getContent());
    }

    public function testRouteParam()
    {
        $request = Request::create('/nico/yazawa', 'GET');

        $app = new Application();
        $app->get('/{first_name}/{last_name}', function (Request $r, $a, $b) use ($request) {
            return $r === $request && $a === 'nico' && $b === 'yazawa' ? 'OK' : 'NG';
        });

        $this->assertSame('OK', $app->handle($request)->getContent());

        $app = new Application();
        $app->get('/{first_name}/{last_name}', function ($last_name, Request $r, $first_name) use ($request) {
            return $r === $request && $first_name === 'nico' && $last_name === 'yazawa' ? 'OK' : 'NG';
        });

        $this->assertSame('OK', $app->handle($request)->getContent());
    }

    public function testReturnResponse()
    {
        $request = Request::create('/', 'GET');
        $response = Response::create('OK');

        $app = new Application();
        $app->get('/', function () use ($response) {
            return $response;
        });

        $this->assertSame($response, $app->handle($request));
    }

    public function testUncaughtException()
    {
        $this->setExpectedException(\RuntimeException::class);

        $request = Request::create('/', 'GET');

        $app = new Application();
        $app->get('/', function () {
            throw new \RuntimeException();
        });

        $app->handle($request, Application::MASTER_REQUEST, false);
    }

    public function testNotFound()
    {
        $this->setExpectedException(NotFoundHttpException::class);

        $request = Request::create('/', 'GET');

        $app = new Application();
        $app->get('/foo', function () {
            return 'OK';
        });

        $app->handle($request, Application::MASTER_REQUEST, false);
    }

    public function testMethodNotAllowed()
    {
        $this->setExpectedException(MethodNotAllowedHttpException::class);

        $request = Request::create('/', 'GET');

        $app = new Application();
        $app->post('/', function () {
            return 'OK';
        });

        $app->handle($request, Application::MASTER_REQUEST, false);
    }

    public function testDefaultExceptionHandler()
    {
        $request = Request::create('/', 'GET');

        $app = new Application();
        $app->get('/', function () {
            throw new \RuntimeException();
        });

        $this->assertSame(500, $app->handle($request)->getStatusCode());
    }

    public function testCustomExceptionHandler()
    {
        $request = Request::create('/', 'GET');

        $e = new \RuntimeException();
        $r = Response::create('OK', 200);

        $app = new Application();
        $app->get('/', function () use ($e) {
            throw $e;
        });

        $app->exception(function ($exception) use ($e, $r) {
            $this->assertSame($e, $exception);
            return $r;
        });

        $this->assertSame($r, $app->handle($request));
    }

    public function testRun()
    {
        $request = Request::create('/', 'GET');
        $request->overrideGlobals();

        $app = new Application();
        $app->get('/', function () {
            return 'OK';
        });

        $this->expectOutputString('OK');

        $app->run();
    }
}

class TestMiddleware
{
    public function handle(Request $request, callable $next)
    {
        $request->query->set('p', 'foo');
        $response = $next($request);
        return Response::create($response->getContent() . 'bar');
    }
}
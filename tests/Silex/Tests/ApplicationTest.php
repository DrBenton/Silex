<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silex\Tests;

use Silex\Application;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Application test cases.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    public function testMatchReturnValue()
    {
        $app = new Application();

        $returnValue = $app->match('/foo', function () {});
        $this->assertInstanceOf('Silex\Controller', $returnValue);

        $returnValue = $app->get('/foo', function () {});
        $this->assertInstanceOf('Silex\Controller', $returnValue);

        $returnValue = $app->post('/foo', function () {});
        $this->assertInstanceOf('Silex\Controller', $returnValue);

        $returnValue = $app->put('/foo', function () {});
        $this->assertInstanceOf('Silex\Controller', $returnValue);

        $returnValue = $app->delete('/foo', function () {});
        $this->assertInstanceOf('Silex\Controller', $returnValue);
    }

    public function testGetRequest()
    {
        $app = new Application();

        $app->get('/', function () {
            return 'root';
        });

        $request = Request::create('/');

        $app->handle($request);

        $this->assertEquals($request, $app['request']);
    }

    public function testGetRoutesWithNoRoutes()
    {
        $app = new Application();

        $routes = $app['routes'];
        $this->assertInstanceOf('Symfony\Component\Routing\RouteCollection', $routes);
        $this->assertEquals(0, count($routes->all()));
    }

    public function testgetRoutesWithRoutes()
    {
        $app = new Application();

        $app->get('/foo', function () {
            return 'foo';
        });

        $app->get('/bar', function () {
            return 'bar';
        });

        $routes = $app['routes'];
        $this->assertInstanceOf('Symfony\Component\Routing\RouteCollection', $routes);
        $this->assertEquals(0, count($routes->all()));
        $app->flush();
        $this->assertEquals(2, count($routes->all()));
    }

    public function testOnCoreController()
    {
        $app = new Application();

        $app->get('/foo/{foo}', function (\ArrayObject $foo) {
            return $foo['foo'];
        })->convert('foo', function ($foo) { return new \ArrayObject(array('foo' => $foo)); });

        $response = $app->handle(Request::create('/foo/bar'));
        $this->assertEquals('bar', $response->getContent());

        $app->get('/foo/{foo}/{bar}', function (\ArrayObject $foo) {
            return $foo['foo'];
        })->convert('foo', function ($foo, Request $request) { return new \ArrayObject(array('foo' => $foo.$request->attributes->get('bar'))); });

        $response = $app->handle(Request::create('/foo/foo/bar'));
        $this->assertEquals('foobar', $response->getContent());
    }

    public function testAbort()
    {
        $app = new Application();

        try {
            $app->abort(404);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertEquals(404, $e->getStatusCode());
        }
    }

    /**
    * @dataProvider escapeProvider
    */
    public function testEscape($expected, $text)
    {
        $app = new Application();

        $this->assertEquals($expected, $app->escape($text));
    }

    public function escapeProvider()
    {
        return array(
            array('&lt;', '<'),
            array('&gt;', '>'),
            array('&quot;', '"'),
            array("'", "'"),
            array('abc', 'abc'),
        );
    }

    public function testControllersAsMethods()
    {
        $app = new Application();

        $app->get('/{name}', 'Silex\Tests\FooController::barAction');

        $this->assertEquals('Hello Fabien', $app->handle(Request::create('/Fabien'))->getContent());
    }

    public function testHttpSpec()
    {
        $app = new Application();
        $app['charset'] = 'ISO-8859-1';

        $app->get('/', function () {
            return 'hello';
        });

        // content is empty for HEAD requests
        $response = $app->handle(Request::create('/', 'HEAD'));
        $this->assertEquals('', $response->getContent());

        // charset is appended to Content-Type
        $response = $app->handle(Request::create('/'));

        $this->assertEquals('text/html; charset=ISO-8859-1', $response->headers->get('Content-Type'));
    }

    public function testRoutesMiddlewares()
    {
        $app = new Application();

        $me = & $this;

        $middlewareTarget = array();
        $middleware1 = function(Request $request) use(& $middlewareTarget, $me) {
            $me->assertEquals('/reached', $request->getRequestUri());
            $middlewareTarget[] = 'middleware1_triggered';
        };
        $middleware2 = function(Request $request) use(& $middlewareTarget, $me) {
            $me->assertEquals('/reached', $request->getRequestUri());
            $middlewareTarget[] = 'middleware2_triggered';
        };
        $middleware3 = function(Request $request) use(& $middlewareTarget, $me) {
            throw new \Exception('This middleware shouldn\'run!');
            $middlewareTarget[] = 'middleware3_triggered';
        };

        $app->get('/reached', function () use (& $middlewareTarget) {
            $middlewareTarget[] = 'route_triggered';
            return 'hello';
        })
        ->addMiddleware($middleware1)
        ->addMiddleware($middleware2);

        $app->get('/never-reached', function () use (& $middlewareTarget) {
            $middlewareTarget[] = 'never_reached_triggered';
            return 'bye';
        })
        ->addMiddleware($middleware3);

        $result = $app->handle(Request::create('/reached'));

        $this->assertEquals('middleware1_triggered/middleware2_triggered/route_triggered', implode('/', $middlewareTarget) );
        $this->assertEquals('hello', $result->getContent());

    }

    /**
     * @expectedException RuntimeException
     */
    public function testAccessingRequestOutsideOfScopeShouldThrowRuntimeException()
    {
        $app = new Application();

        $request = $app['request'];
    }
}

class FooController
{
    public function barAction(Application $app, $name)
    {
        return 'Hello '.$app->escape($name);
    }
}

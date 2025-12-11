<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Application;
use BareMetalPHP\Http\Redirect;
use BareMetalPHP\Http\Request;
use BareMetalPHP\Routing\Router;
use BareMetalPHP\Support\Session;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Session::start();
        Session::flush();
    }

    protected function tearDown(): void
    {
        Session::destroy();
        parent::tearDown();
    }

    public function testCanCreateRedirectToUrl(): void
    {
        $redirect = Redirect::to('/dashboard');
        
        $this->assertSame(302, $redirect->getStatusCode());
        $this->assertSame('/dashboard', $redirect->getHeaders()['Location']);
        $this->assertEmpty($redirect->getBody());
    }

    public function testCanCreateRedirectWithCustomStatus(): void
    {
        $redirect = Redirect::to('/dashboard', 301);
        
        $this->assertSame(301, $redirect->getStatusCode());
    }

    public function testCanCreateRedirectWithCustomHeaders(): void
    {
        $redirect = Redirect::to('/dashboard', 302, ['X-Custom' => 'value']);
        
        $this->assertSame('value', $redirect->getHeaders()['X-Custom']);
        $this->assertSame('/dashboard', $redirect->getHeaders()['Location']);
    }

    public function testBackRedirectsToReferer(): void
    {
        $_SERVER['HTTP_REFERER'] = '/previous-page';
        
        $redirect = Redirect::back();
        
        $this->assertSame('/previous-page', $redirect->getHeaders()['Location']);
        
        unset($_SERVER['HTTP_REFERER']);
    }

    public function testBackUsesRequestRefererWhenProvided(): void
    {
        // Create a concrete Request instance with Referer header
        // Can't use createMock because Request has a method() method which conflicts with PHPUnit's method()
        $server = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
            'HTTP_REFERER' => '/request-referer',
        ];
        $request = Request::fromParts($server, '');
        
        $redirect = Redirect::back($request);
        
        $this->assertSame('/request-referer', $redirect->getHeaders()['Location']);
    }

    public function testBackFallsBackToRootWhenNoReferer(): void
    {
        unset($_SERVER['HTTP_REFERER']);
        
        $redirect = Redirect::back();
        
        $this->assertSame('/', $redirect->getHeaders()['Location']);
    }

    public function testRouteRedirectsToNamedRoute(): void
    {
        $router = $this->createMock(Router::class);
        $router->method('route')->with('users.show', ['id' => 1])->willReturn('/users/1');
        
        $this->app->bind(Router::class, fn() => $router);
        
        $redirect = Redirect::route('users.show', ['id' => 1]);
        
        $this->assertSame('/users/1', $redirect->getHeaders()['Location']);
    }

    public function testRouteThrowsExceptionWhenApplicationNotAvailable(): void
    {
        Application::setInstance(null);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Application instance not available');
        
        Redirect::route('users.show');
    }

    public function testWithFlashesDataToSession(): void
    {
        $redirect = Redirect::to('/dashboard');
        $redirect->with('message', 'Success!');
        
        $this->assertSame('Success!', Session::getFlash('message'));
    }

    public function testWithReturnsSelfForChaining(): void
    {
        $redirect = Redirect::to('/dashboard');
        $result = $redirect->with('key1', 'value1')->with('key2', 'value2');
        
        $this->assertSame($redirect, $result);
        $this->assertSame('value1', Session::getFlash('key1'));
        $this->assertSame('value2', Session::getFlash('key2'));
    }

    public function testWithErrorsFlashesErrorsToSession(): void
    {
        $redirect = Redirect::to('/form');
        $errors = ['email' => 'Invalid email', 'password' => 'Too short'];
        
        $redirect->withErrors($errors);
        
        $this->assertSame($errors, Session::getFlash('errors'));
    }

    public function testWithErrorsUsesCustomKey(): void
    {
        $redirect = Redirect::to('/form');
        $errors = ['email' => 'Invalid email'];
        
        $redirect->withErrors($errors, 'validation_errors');
        
        $this->assertSame($errors, Session::getFlash('validation_errors'));
    }
}

<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Slim\Http\ServerRequest;Shaarli\TestCase;
use Slim\Http\ServerRequest;
use Slim\Http\Response;

class TokenControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var TokenController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new TokenController($this->container);
    }

    public function testGetToken(): void
    {
        $request = $this->createMock(ServerRequest::class);
        $response = new Response();

        $this->container->sessionManager
            ->expects(static::once())
            ->method('generateToken')
            ->willReturn($token = 'token1234')
        ;

        $result = $this->controller->getToken($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame($token, (string) $result->getBody());
    }
}

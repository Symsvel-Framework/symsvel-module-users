<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use SymSvel\Module\Users\Controller\UserRegistrationController;

final class UserRegistrationControllerTest extends TestCase
{
    public function testInvalidRequestsAreRejectedBeforeAccessToServices(): void
    {
        $controller = (new \ReflectionClass(UserRegistrationController::class))->newInstanceWithoutConstructor();
        self::assertSame(415, $controller(Request::create('/apiopen/users/register', 'POST'))->getStatusCode());
        foreach (['{', '{"email":5}', '{"email":"a@example.test","password":12}', '[]'] as $body) {
            $request = Request::create('/apiopen/users/register', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
            self::assertSame(400, $controller($request)->getStatusCode());
        }
    }

    public function testRegistrationRouteIsPublicAndAppScoped(): void
    {
        $routes = (new \ReflectionMethod(UserRegistrationController::class, '__invoke'))
            ->getAttributes(\Symfony\Component\Routing\Attribute\Route::class);
        self::assertCount(1, $routes);
        self::assertSame('/apiopen/users/register', $routes[0]->newInstance()->getPath());
    }
}

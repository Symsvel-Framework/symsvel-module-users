<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;
use SymSvel\Module\Users\Controller\UsersPingController;

final class UsersPingControllerTest extends TestCase
{
    public function testPingResponseAndRouteContract(): void
    {
        $controller = new UsersPingController();
        self::assertSame(['module' => 'users', 'status' => 'ok'], json_decode($controller()->getContent(), true, 512, JSON_THROW_ON_ERROR));
        $routes = (new \ReflectionMethod(UsersPingController::class, '__invoke'))->getAttributes(Route::class);
        self::assertCount(1, $routes);
        $route = $routes[0]->newInstance();
        self::assertSame('/api/users/ping', $route->getPath());
        self::assertSame(['GET'], $route->getMethods());
    }
}

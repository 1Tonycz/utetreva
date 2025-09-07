<?php

declare(strict_types=1);

namespace App\Core\Router;

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Routing\Route;

final class RouterFactory
{
    use Nette\StaticClass;

    public static function createRouter(): RouteList
    {
        $router = new RouteList;

        $router
            ->withPath('system')
            ->addRoute('<presenter>/<action>[/<id>]', [
                'module' => 'Admin',
                'presenter' => [
                    Route::Value => 'Home',
                ],
                'action' => [
                    Route::Value => 'default',
                ],
            ])
            ->end();

        // FRONT – přidej locale prefix [cs|en]
        $router
            ->withModule('Front')
            ->addRoute('[<locale=cs en|cs|ru|de>/]', 'Home:default')
            ->addRoute('[<locale=cs en|cs|ru|de>/]<presenter>/<action>[/<id>]', [
                'presenter' => [
                    Route::Value => 'Home',
                ],
                'action' => [
                    Route::Value => 'default',
                ],
            ]);

        return $router;
    }
}

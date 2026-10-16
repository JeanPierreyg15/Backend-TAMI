<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\ProductoCreado;
use App\Events\ProductoActualizado;
use App\Events\ProductoEliminado;
use App\Listeners\DispatchFrontendDeploy;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ProductoCreado::class => [
            DispatchFrontendDeploy::class,
        ],
        ProductoActualizado::class => [
            DispatchFrontendDeploy::class,
        ],
        ProductoEliminado::class => [
            DispatchFrontendDeploy::class,
        ],
    ];
}

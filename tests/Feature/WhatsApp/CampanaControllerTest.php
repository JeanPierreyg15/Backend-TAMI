<?php

namespace Tests\Feature\WhatsApp;

use App\Http\Controllers\Api\V1\WhatsApp\CampanaController;
use App\Models\Campana;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CampanaControllerTest extends TestCase
{
    public function test_la_ruta_activar_resuelve_al_controlador_renombrado(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())->first(
            fn ($r) => $r->getControllerClass() === CampanaController::class
                && in_array('POST', $r->methods(), true)
        );

        $this->assertNotNull($route);
        $this->assertStringContainsString('whatsapp/campañas/activar', $route->uri());
    }

    public function test_el_modelo_campana_sigue_usando_la_tabla_campanas(): void
    {
        $this->assertSame('campanas', (new Campana)->getTable());
    }

    public function test_activar_sin_autenticacion_devuelve_401(): void
    {
        $this->postJson('/api/v1/whatsapp/campañas/activar')->assertUnauthorized();
    }
}

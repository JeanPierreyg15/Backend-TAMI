<?php

namespace Tests\Feature\Controller;

use App\Models\Cliente;
use App\Models\ClienteSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClienteControllerTest extends TestCase
{
    use RefreshDatabase;

    private ClienteSource $sourceInicio;
    private ClienteSource $sourceProducto;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'ADMIN']);
        Role::create(['name' => 'VENTAS']);

        $this->sourceInicio = ClienteSource::create(['name' => 'Inicio']);
        $this->sourceProducto = ClienteSource::create(['name' => 'Producto detalle']);

        Mail::fake();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('ADMIN');

        return $user;
    }

        #[Test]
    public function crea_un_cliente_desde_el_formulario_publico(): void
    {
        $this->postJson('/api/v1/clientes', [
            'name' => 'Ana Torres',
            'email' => 'ana.torres@example.com',
            'celular' => '+51 999-888-777',
            'source_id' => $this->sourceInicio->id,
        ])->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('clientes', [
            'email' => 'ana.torres@example.com',
            'source_id' => $this->sourceInicio->id,
        ]);
    }

    #[Test]
    public function rechaza_un_email_duplicado(): void
    {
        Cliente::factory()->create([
            'email' => 'duplicado@example.com',
            'celular' => '+51 999-111-222',
            'source_id' => $this->sourceInicio->id,
        ]);

        $this->postJson('/api/v1/clientes', [
            'name' => 'Otro Cliente',
            'email' => 'duplicado@example.com',
            'celular' => '+51 999-333-444',
            'source_id' => $this->sourceInicio->id,
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    #[Test]
    public function limita_los_intentos_del_endpoint_publico(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/clientes', [
                'name' => "Lead {$i}",
                'email' => "lead{$i}@example.com",
                'celular' => '+51 999-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT) . '-000',
                'source_id' => $this->sourceInicio->id,
            ]);
        }

        $this->postJson('/api/v1/clientes', [
            'name' => 'Lead 11',
            'email' => 'lead11@example.com',
            'celular' => '+51 999-011-000',
            'source_id' => $this->sourceInicio->id,
        ])->assertStatus(429);
    }

    #[Test]
    public function update_devuelve_404_si_no_existe(): void
    {
        $this->actingAs($this->admin(), 'sanctum');

        $this->putJson('/api/v1/clientes/999', [
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'celular' => '+51 999-999-999',
        ])->assertNotFound();
    }

    #[Test]
    public function destroy_devuelve_404_si_no_existe(): void
    {
        $this->actingAs($this->admin(), 'sanctum');

        $this->deleteJson('/api/v1/clientes/999')->assertNotFound();
    }

    #[Test]
    public function actualiza_el_cliente_existente_si_viene_de_producto_detalle(): void
    {
        $cliente = Cliente::factory()->create([
            'name' => 'Lead Original',
            'email' => 'lead@example.com',
            'celular' => '+51 999-222-333',
            'source_id' => $this->sourceInicio->id,
        ]);

        $this->postJson('/api/v1/clientes', [
            'name' => 'Lead Actualizado',
            'email' => 'lead@example.com',
            'celular' => '+51 999-222-333',
            'source_id' => $this->sourceProducto->id,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'name' => 'Lead Actualizado',
            'source_id' => $this->sourceProducto->id,
        ]);
    }

    #[Test]
    public function no_expone_el_mensaje_interno_si_falla_la_creacion(): void
    {
        config(['app.debug' => false]);

        Cliente::creating(function () {
            throw new \Exception('SQLSTATE[HY000]: fallo simulado');
        });

        try {
            $response = $this->postJson('/api/v1/clientes', [
                'name' => 'Ana Torres',
                'email' => 'error@example.com',
                'celular' => '+51 999-444-555',
                'source_id' => $this->sourceInicio->id,
            ]);

            $response->assertStatus(500)
                ->assertJsonPath('success', false)
                ->assertJsonMissingPath('error');

            $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        } finally {
            Cliente::flushEventListeners();
        }
    }

    #[Test]
    public function destroy_cierra_la_transaccion_si_falla_el_borrado(): void
    {
        $cliente = Cliente::factory()->create([
            'email' => 'rollback@example.com',
            'celular' => '+51 999-555-666',
            'source_id' => $this->sourceInicio->id,
        ]);

        $this->actingAs($this->admin(), 'sanctum');

        $nivelAntes = DB::transactionLevel();

        Cliente::deleting(function () {
            throw new \Exception('fallo simulado');
        });

        try {
            $this->deleteJson("/api/v1/clientes/{$cliente->id}")->assertStatus(500);
            $this->assertSame($nivelAntes, DB::transactionLevel());
        } finally {
            Cliente::flushEventListeners();
        }
    }
}
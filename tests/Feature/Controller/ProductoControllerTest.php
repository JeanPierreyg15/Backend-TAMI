<?php

namespace Tests\Feature\Controller;

use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductoControllerTest extends TestCase
{
    use RefreshDatabase;

    private function crearProducto(string $link = 'producto-demo', string $nombre = 'Producto Demo'): Producto
    {
        return Producto::create([
            'nombre' => $nombre,
            'titulo' => 'Titulo ' . $nombre,
            'precio' => 99.90,
            'link' => $link,
        ]);
    }

    #[Test]
    public function lista_los_productos(): void
    {
        $this->crearProducto('producto-uno', 'Uno');
        $this->crearProducto('producto-dos', 'Dos');

        $this->getJson('/api/v1/productos')
            ->assertOk()
            ->assertJsonCount(2);
    }

    #[Test]
    public function muestra_un_producto_por_id(): void
    {
        $producto = $this->crearProducto();

        $this->getJson("/api/v1/productos/{$producto->id}")
            ->assertOk()
            ->assertJsonPath('data.link', 'producto-demo');
    }

    #[Test]
    public function devuelve_404_si_el_producto_no_existe(): void
    {
        $this->getJson('/api/v1/productos/99999')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function muestra_un_producto_por_link(): void
    {
        $this->crearProducto('producto-por-link');

        $this->getJson('/api/v1/productos/link/producto-por-link')
            ->assertOk()
            ->assertJsonPath('data.link', 'producto-por-link');
    }

    #[Test]
    public function devuelve_404_si_el_link_no_existe(): void
    {
        $this->getJson('/api/v1/productos/link/no-existe')
            ->assertNotFound();
    }

    #[Test]
    public function devuelve_los_productos_relacionados(): void
    {
        $producto = $this->crearProducto();
        $relacionado = $this->crearProducto('producto-relacionado', 'Relacionado');

        $producto->productosRelacionados()->attach($relacionado->id);

        $this->getJson("/api/v1/productos/{$producto->id}/related")
            ->assertOk()
            ->assertJsonPath('producto', 'Producto Demo')
            ->assertJsonCount(1, 'relacionados');
    }

    #[Test]
    public function pagina_los_productos(): void
    {
        $this->crearProducto('producto-uno', 'Uno');
        $this->crearProducto('producto-dos', 'Dos');

        $this->getJson('/api/v1/productos/paginate?perPage=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}

<?php

namespace Tests\Feature\Controller;

use App\Models\Blog;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BlogControllerTest extends TestCase
{
    use RefreshDatabase;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'ADMIN']);

        Storage::fake('public');

        $this->producto = Producto::create([
            'nombre' => 'Producto Demo',
            'titulo' => 'Titulo Demo',
            'precio' => 99.90,
            'link' => 'producto-demo',
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('ADMIN');

        return $user;
    }

    private function crearBlog(string $link = 'blog-demo'): Blog
    {
        return Blog::create([
            'titulo' => 'Blog Demo',
            'producto_id' => $this->producto->id,
            'link' => $link,
            'subtitulo1' => 'Subtitulo uno',
            'subtitulo2' => 'Subtitulo dos',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'video_titulo' => 'Video demo',
            'miniatura' => '/storage/imagenes/miniatura.jpg',
        ]);
    }

    private function payloadCompleto(array $extra = []): array
    {
        return array_merge([
            'titulo' => 'Blog de prueba',
            'producto_id' => $this->producto->id,
            'link' => 'blog-de-prueba',
            'subtitulo1' => 'Subtitulo uno',
            'subtitulo2' => 'Subtitulo dos',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'video_titulo' => 'Video de prueba',
            'parrafos' => ['Parrafo uno', 'Parrafo dos'],
        ], $extra);
    }

    #[Test]
    public function lista_los_blogs(): void
    {
        $this->crearBlog('blog-uno');
        $this->crearBlog('blog-dos');

        $this->getJson('/api/v1/blogs')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function muestra_un_blog_por_id(): void
    {
        $blog = $this->crearBlog();

        $this->getJson("/api/v1/blogs/{$blog->id}")
            ->assertOk()
            ->assertJsonPath('data.link', 'blog-demo');
    }

    #[Test]
    public function muestra_un_blog_por_link(): void
    {
        $this->crearBlog('blog-por-link');

        $this->getJson('/api/v1/blogs/link/blog-por-link')
            ->assertOk()
            ->assertJsonPath('data.link', 'blog-por-link');
    }

    #[Test]
    public function pagina_los_blogs(): void
    {
        $this->crearBlog('blog-uno');
        $this->crearBlog('blog-dos');

        $this->getJson('/api/v1/blogs/paginate?perPage=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function crea_un_blog_con_imagenes_parrafos_y_etiqueta(): void
    {
        $this->actingAs($this->admin(), 'sanctum');

        $this->post('/api/v1/blogs', $this->payloadCompleto([
            'miniatura' => UploadedFile::fake()->image('portada.jpg', 600, 400),
            'imagenes' => [UploadedFile::fake()->image('galeria.jpg', 600, 400)],
            'img_alt' => ['Alt de galeria'],
            'img_nombre' => ['Nombre de galeria'],
            'img_tittle' => ['Titulo de galeria'],
            'meta_titulo' => 'Meta titulo de prueba',
            'meta_descripcion' => str_repeat('Descripcion SEO ', 4),
        ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $blog = Blog::where('link', 'blog-de-prueba')->firstOrFail();

        $this->assertDatabaseHas('blogs_parrafos', [
            'blog_id' => $blog->id,
            'parrafo' => 'Parrafo uno',
        ]);
        $this->assertDatabaseCount('blogs_parrafos', 2);
        $this->assertDatabaseCount('blogs_imagenes', 1);
        $this->assertDatabaseHas('blog_etiquetas', [
            'blog_id' => $blog->id,
            'meta_titulo' => 'Meta titulo de prueba',
        ]);

        Storage::disk('public')->assertExists(
            str_replace('/storage/', '', $blog->miniatura)
        );
    }

    #[Test]
    public function actualiza_un_blog_reemplazando_imagenes_y_parrafos(): void
    {
        $blog = $this->crearBlog();

        $imagenVieja = $blog->imagenes()->create([
            'ruta_imagen' => '/storage/imagenes/vieja.jpg',
            'img_alt' => 'alt vieja',
        ]);
        $blog->parrafos()->create(['parrafo' => 'Parrafo viejo']);

        Storage::disk('public')->put('imagenes/vieja.jpg', 'contenido');

        $this->actingAs($this->admin(), 'sanctum');

        $this->put("/api/v1/blogs/{$blog->id}", $this->payloadCompleto([
            'link' => 'blog-demo',
            'parrafos' => ['Parrafo nuevo uno', 'Parrafo nuevo dos'],
            'imagen_tipo' => ['existing', 'file'],
            'imagen_ids' => [$imagenVieja->id],
            'imagenes' => [UploadedFile::fake()->image('nueva.jpg', 600, 400)],
            'img_alt' => ['alt conservada', 'alt nueva'],
            'img_nombre' => ['nombre conservado', 'nombre nuevo'],
            'img_tittle' => ['titulo conservado', 'titulo nuevo'],
        ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $blog->refresh();

        $this->assertDatabaseCount('blogs_imagenes', 2);
        $this->assertDatabaseHas('blogs_imagenes', [
            'ruta_imagen' => '/storage/imagenes/vieja.jpg',
            'img_alt' => 'alt conservada',
        ]);
        $this->assertDatabaseMissing('blogs_parrafos', [
            'blog_id' => $blog->id,
            'parrafo' => 'Parrafo viejo',
        ]);
        $this->assertDatabaseHas('blogs_parrafos', [
            'blog_id' => $blog->id,
            'parrafo' => 'Parrafo nuevo dos',
        ]);
        Storage::disk('public')->assertExists('imagenes/vieja.jpg');
    }

    #[Test]
    public function elimina_un_blog_con_sus_relaciones_y_archivos(): void
    {
        $blog = $this->crearBlog();

        $blog->imagenes()->create(['ruta_imagen' => '/storage/imagenes/borrar.jpg']);
        $blog->parrafos()->create(['parrafo' => 'Parrafo']);
        $blog->etiqueta()->create(['meta_titulo' => 'Meta titulo de prueba']);

        Storage::disk('public')->put('imagenes/borrar.jpg', 'contenido');
        Storage::disk('public')->put('imagenes/miniatura.jpg', 'contenido');

        $this->actingAs($this->admin(), 'sanctum');

        $this->deleteJson("/api/v1/blogs/{$blog->id}")->assertOk();

        $this->assertDatabaseMissing('blogs', ['id' => $blog->id]);
        $this->assertDatabaseMissing('blogs_imagenes', ['blog_id' => $blog->id]);
        $this->assertDatabaseMissing('blogs_parrafos', ['blog_id' => $blog->id]);
        $this->assertDatabaseMissing('blog_etiquetas', ['blog_id' => $blog->id]);

        Storage::disk('public')->assertMissing('imagenes/borrar.jpg');
        Storage::disk('public')->assertMissing('imagenes/miniatura.jpg');
    }

    #[Test]
    public function acepta_campos_opcionales_cortos_y_parrafos_con_html_largo(): void
    {
        $this->actingAs($this->admin(), 'sanctum');

        $this->post('/api/v1/blogs', $this->payloadCompleto([
            'miniatura' => UploadedFile::fake()->image('portada.jpg', 600, 400),
            'miniatura_nombre' => 'Corto',
            'meta_titulo' => 'Corto',
            'meta_descripcion' => 'Corta',
            'popup_button_text' => 'Ok',
            'parrafos' => ['<p>' . str_repeat('Contenido con HTML. ', 150) . '</p>'],
        ]), ['Accept' => 'application/json'])
            ->assertCreated();
    }

    #[Test]
    public function rechaza_video_titulo_mayor_a_40_caracteres(): void
    {
        $this->actingAs($this->admin(), 'sanctum');

        $this->post('/api/v1/blogs', $this->payloadCompleto([
            'miniatura' => UploadedFile::fake()->image('portada.jpg', 600, 400),
            'video_titulo' => str_repeat('V', 41),
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('video_titulo');
    }

    #[Test]
    public function elimina_miniatura_y_hero_image_al_actualizar(): void
    {
        $blog = $this->crearBlog();
        $blog->update(['hero_image' => '/storage/imagenes/hero.jpg']);

        $imagen = $blog->imagenes()->create(['ruta_imagen' => '/storage/imagenes/vieja.jpg']);

        Storage::disk('public')->put('imagenes/miniatura.jpg', 'contenido');
        Storage::disk('public')->put('imagenes/hero.jpg', 'contenido');
        Storage::disk('public')->put('imagenes/vieja.jpg', 'contenido');

        $this->actingAs($this->admin(), 'sanctum');

        $this->put("/api/v1/blogs/{$blog->id}", $this->payloadCompleto([
            'link' => 'blog-demo',
            'parrafos' => ['Parrafo actualizado'],
            'imagen_tipo' => ['existing'],
            'imagen_ids' => [$imagen->id],
            'eliminar_miniatura' => true,
            'eliminar_hero_image' => true,
        ]), ['Accept' => 'application/json'])
            ->assertOk();

        $blog->refresh();

        $this->assertNull($blog->miniatura);
        $this->assertNull($blog->hero_image);
        Storage::disk('public')->assertMissing('imagenes/miniatura.jpg');
        Storage::disk('public')->assertMissing('imagenes/hero.jpg');
    }
}

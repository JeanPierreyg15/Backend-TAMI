<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            AssignPermissionsToRolesSeeder::class,
            UserSeeder::class,
            
            // Clientes
            ClienteSourcesSeeder::class,

            // Productos
            ProductoSeeder::class,
            // EspecificacionSeeder::class,
            DimensionSeeder::class,
            ProductoImagenSeeder::class,
            ProductoRelacionadoSeeder::class,

            // Blog
            BlogSeeder::class,
            BlogImagenesSeeder::class,
            BlogParrafosSeeder::class,
            // DetalleBlogSeeder::class,
            // ImagenBlogSeeder::class,
            //VideoBlogSeeder::class,

            // Reclamos
            Claims::class,

        ]);
    }
}

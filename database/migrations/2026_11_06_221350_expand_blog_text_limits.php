<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplía los campos de texto del blog para que el equipo de comunicación
     * pueda registrar títulos y enlaces con la longitud real de sus piezas.
     */
    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->string('link', 255)->nullable()->change();
            $table->string('video_url', 255)->nullable()->change();
            $table->string('video_titulo', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->string('link', 125)->nullable()->change();
            $table->string('video_url', 125)->nullable()->change();
            $table->string('video_titulo', 40)->nullable()->change();
        });
    }
};

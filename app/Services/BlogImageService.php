<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BlogImageService
{
    /**
     * Guarda una imagen en el disco público y devuelve su URL.
     */
    public function guardar(UploadedFile $archivo): string
    {
        $extension = strtolower($archivo->getClientOriginalExtension());
        $originalBase = pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._\-]+/', '_', trim($originalBase));

        if ($base === '' || $base === '.') {
            $base = 'imagen';
        }

        $nombre = $base . '_' . uniqid() . '.' . $extension;
        $path = $archivo->storeAs('imagenes', $nombre, 'public');

        return Storage::disk('public')->url($path);
    }

    public function borrar(?string $url): void
    {
        if (empty($url)) {
            return;
        }

        // La URL puede venir como /storage/imagenes/archivo,
        // como http://host/storage/imagenes/archivo o como imagenes/archivo.
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        if (!str_contains($path, '/storage/') && !str_starts_with(ltrim($path, '/'), 'imagenes/')) {
            // Es una URL externa (por ejemplo imgur): no hay archivo local que borrar.
            return;
        }

        $relativePath = ltrim(preg_replace('#^/storage/#', '', $path), '/');

        Storage::disk('public')->delete($relativePath);
    }
}

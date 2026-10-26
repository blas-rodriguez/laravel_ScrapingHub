<?php

namespace App\Services\Scraping;

use RuntimeException;

class ResultData
{
    /** @return array<int, array<string, mixed>> */
    public static function rows(mixed $data): array
    {
        if (! is_array($data)) {
            return [['value' => $data]];
        }
        if ($data === []) {
            return [];
        }
        if (! array_is_list($data)) {
            foreach (['items', 'products', 'results', 'records'] as $key) {
                if (isset($data[$key]) && is_array($data[$key]) && array_is_list($data[$key])) {
                    return self::rows($data[$key]);
                }
            }

            return [$data];
        }
        if (count($data) > 500) {
            throw new RuntimeException('La respuesta supera el límite local de 500 filas.');
        }

        return array_map(fn (mixed $row): array => is_array($row) ? $row : ['value' => $row], $data);
    }

    public static function opaqueId(mixed $id): string
    {
        if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9_-]{1,150}$/', $id)) {
            throw new RuntimeException('El proveedor no devolvió un identificador de tarea válido.');
        }

        return $id;
    }
}

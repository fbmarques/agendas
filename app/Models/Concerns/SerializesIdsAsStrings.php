<?php

namespace App\Models\Concerns;

/**
 * Serializa `id` e todas as FKs `*_id` como string ao converter para array/JSON.
 * Base44 (frontend original) tratava IDs como string; manter esse contrato
 * evita comparações estritas quebrarem no lado JS.
 */
trait SerializesIdsAsStrings
{
    public function toArray(): array
    {
        $array = parent::toArray();

        if (array_key_exists('id', $array) && $array['id'] !== null) {
            $array['id'] = (string) $array['id'];
        }

        foreach ($array as $key => $value) {
            if ($value !== null && str_ends_with($key, '_id') && (is_int($value) || is_numeric($value))) {
                $array[$key] = (string) $value;
            }
        }

        return $array;
    }
}

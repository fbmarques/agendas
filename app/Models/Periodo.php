<?php

namespace App\Models;

use App\Models\Concerns\SerializesIdsAsStrings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'data_inicio', 'data_fim', 'status'])]
class Periodo extends Model
{
    use HasFactory, SerializesIdsAsStrings;

    protected $table = 'periodos';

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date:Y-m-d',
            'data_fim' => 'date:Y-m-d',
        ];
    }
}

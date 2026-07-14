<?php

namespace App\Models;

use App\Models\Concerns\SerializesIdsAsStrings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'sigla', 'endereco', 'cidade', 'descricao', 'status'])]
class Campi extends Model
{
    use HasFactory, SerializesIdsAsStrings;

    protected $table = 'campi';
}

<?php

namespace Database\Factories;

use App\Models\Campi;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grupo>
 */
class GrupoFactory extends Factory
{
    protected $model = Grupo::class;

    public function definition(): array
    {
        return [
            'campi_id' => Campi::factory(),
            'nome' => 'Grupo '.$this->faker->word(),
            'descricao' => $this->faker->sentence(),
            'status' => 'ativo',
        ];
    }
}

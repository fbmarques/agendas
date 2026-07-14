<?php

namespace Database\Factories;

use App\Models\Campi;
use App\Models\Grupo;
use App\Models\Local;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Local>
 */
class LocalFactory extends Factory
{
    protected $model = Local::class;

    public function definition(): array
    {
        $campi = Campi::factory()->create();
        $grupo = Grupo::factory()->create(['campi_id' => $campi->id]);

        return [
            'campi_id' => $campi->id,
            'grupo_id' => $grupo->id,
            'nome' => 'Local '.$this->faker->word(),
            'tipo' => 'Sala de aula',
            'capacidade' => $this->faker->numberBetween(10, 100),
            'descricao' => $this->faker->sentence(),
            'recursos' => 'Projetor',
            'status' => 'ativo',
        ];
    }
}

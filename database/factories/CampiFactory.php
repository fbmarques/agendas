<?php

namespace Database\Factories;

use App\Models\Campi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campi>
 */
class CampiFactory extends Factory
{
    protected $model = Campi::class;

    public function definition(): array
    {
        return [
            'nome' => 'Campi '.$this->faker->company(),
            'sigla' => strtoupper($this->faker->lexify('???')),
            'endereco' => $this->faker->streetAddress(),
            'cidade' => $this->faker->city(),
            'descricao' => $this->faker->sentence(),
            'status' => 'ativo',
        ];
    }
}

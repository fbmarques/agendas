<?php

namespace Database\Factories;

use App\Models\Local;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reserva>
 */
class ReservaFactory extends Factory
{
    protected $model = Reserva::class;

    public function definition(): array
    {
        $local = Local::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);

        return [
            'user_id' => $user->id,
            'campi_id' => $local->campi_id,
            'grupo_id' => $local->grupo_id,
            'local_id' => $local->id,
            'titulo' => 'Reserva '.$this->faker->word(),
            'motivo' => 'Aula da disciplina de exemplo para a turma do primeiro semestre com duração completa',
            'tipo_local' => $local->tipo,
            'data_inicial' => '2026-08-01',
            'data_final' => '2026-08-01',
            'horario_inicial' => '08:00',
            'horario_final' => '10:00',
            'responsavel_nome' => $user->full_name ?? $user->email,
            'observacoes' => null,
            'status' => 'confirmada',
            'recorrente' => false,
        ];
    }
}

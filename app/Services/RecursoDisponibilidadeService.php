<?php

namespace App\Services;

use App\Models\Recurso;
use App\Models\Reserva;
use Illuminate\Support\Facades\DB;

class RecursoDisponibilidadeService
{
    /**
     * Verifica se o recurso está disponível na janela pedida na quantidade solicitada.
     * Retorna ['ok' => bool, 'motivo' => ?string].
     */
    public function verificar(
        int $recursoId,
        int $quantidade,
        string $dataInicial,
        string $dataFinal,
        string $horarioInicial,
        string $horarioFinal,
        ?int $ignorarReservaId = null,
    ): array {
        $recurso = Recurso::with('disponibilidades')->find($recursoId);
        if (! $recurso) return ['ok' => false, 'motivo' => 'Recurso não encontrado.'];
        if ($recurso->status !== 'ativo') return ['ok' => false, 'motivo' => "Recurso {$recurso->nome} está inativo."];
        if ($quantidade < 1) return ['ok' => false, 'motivo' => 'Quantidade inválida.'];

        // Coleta todos os dias da semana envolvidos entre data_inicial e data_final
        $diasEnvolvidos = [];
        for ($t = strtotime($dataInicial); $t <= strtotime($dataFinal); $t += 86400) {
            $diasEnvolvidos[(int) date('w', $t)] = true;
        }

        // Cada dia envolvido precisa estar coberto por PELO MENOS uma janela do recurso
        foreach (array_keys($diasEnvolvidos) as $dia) {
            $cobre = $recurso->disponibilidades->contains(function ($d) use ($dia, $horarioInicial, $horarioFinal) {
                $dias = is_array($d->dias_semana) ? $d->dias_semana : [];
                if (! in_array($dia, $dias, true)) return false;
                $hi = substr($d->horario_inicial, 0, 5);
                $hf = substr($d->horario_final, 0, 5);
                return $hi <= $horarioInicial && $horarioFinal <= $hf;
            });
            if (! $cobre) {
                return ['ok' => false, 'motivo' => "Recurso {$recurso->nome} não está disponível neste dia/horário."];
            }
        }

        // Verifica soma de quantidade já alocada no intervalo (excluindo canceladas e a reserva atual)
        $alocado = DB::table('reserva_recurso as rr')
            ->join('reservas as r', 'r.id', '=', 'rr.reserva_id')
            ->where('rr.recurso_id', $recursoId)
            ->where('r.status', '!=', 'cancelada')
            ->where('r.data_inicial', '<=', $dataFinal)
            ->where('r.data_final', '>=', $dataInicial)
            ->where('r.horario_inicial', '<', $horarioFinal)
            ->where('r.horario_final', '>', $horarioInicial)
            ->when($ignorarReservaId, fn ($q) => $q->where('r.id', '!=', $ignorarReservaId))
            ->sum('rr.quantidade');

        if ($alocado + $quantidade > $recurso->quantidade) {
            return [
                'ok' => false,
                'motivo' => "Recurso {$recurso->nome} esgotado neste horário (disponível: ".max(0, $recurso->quantidade - (int) $alocado)."/{$recurso->quantidade}).",
            ];
        }

        return ['ok' => true, 'motivo' => null];
    }
}

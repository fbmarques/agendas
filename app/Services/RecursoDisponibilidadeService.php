<?php

namespace App\Services;

use App\Models\Recurso;
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

        if (! $this->cobrirJanela($recurso, $dataInicial, $dataFinal, $horarioInicial, $horarioFinal)) {
            return ['ok' => false, 'motivo' => "Recurso {$recurso->nome} não está disponível neste dia/horário."];
        }

        $alocado = $this->quantidadeAlocada($recursoId, $dataInicial, $dataFinal, $horarioInicial, $horarioFinal, $ignorarReservaId);
        if ($alocado + $quantidade > $recurso->quantidade) {
            return [
                'ok' => false,
                'motivo' => "Recurso {$recurso->nome} esgotado neste horário (disponível: ".max(0, $recurso->quantidade - $alocado)."/{$recurso->quantidade}).",
            ];
        }

        return ['ok' => true, 'motivo' => null];
    }

    /**
     * Saldo disponível do recurso na janela (quantidade - já alocado).
     * Retorna 0 se estiver fora da janela ou o recurso estiver inativo.
     */
    public function saldoNaJanela(
        int $recursoId,
        string $dataInicial,
        string $dataFinal,
        string $horarioInicial,
        string $horarioFinal,
        ?int $ignorarReservaId = null,
    ): int {
        $recurso = Recurso::with('disponibilidades')->find($recursoId);
        if (! $recurso || $recurso->status !== 'ativo') return 0;
        if (! $this->cobrirJanela($recurso, $dataInicial, $dataFinal, $horarioInicial, $horarioFinal)) return 0;

        $alocado = $this->quantidadeAlocada($recursoId, $dataInicial, $dataFinal, $horarioInicial, $horarioFinal, $ignorarReservaId);
        return max(0, $recurso->quantidade - $alocado);
    }

    private function cobrirJanela(Recurso $recurso, string $di, string $df, string $hi, string $hf): bool
    {
        $diasEnvolvidos = [];
        for ($t = strtotime($di); $t <= strtotime($df); $t += 86400) {
            $diasEnvolvidos[(int) date('w', $t)] = true;
        }
        foreach (array_keys($diasEnvolvidos) as $dia) {
            $cobre = $recurso->disponibilidades->contains(function ($d) use ($dia, $hi, $hf) {
                $dias = is_array($d->dias_semana) ? $d->dias_semana : [];
                if (! in_array($dia, $dias, true)) return false;
                $dhi = substr($d->horario_inicial, 0, 5);
                $dhf = substr($d->horario_final, 0, 5);
                return $dhi <= $hi && $hf <= $dhf;
            });
            if (! $cobre) return false;
        }
        return true;
    }

    private function quantidadeAlocada(int $recursoId, string $di, string $df, string $hi, string $hf, ?int $ignorarReservaId): int
    {
        return (int) DB::table('reserva_recurso as rr')
            ->join('reservas as r', 'r.id', '=', 'rr.reserva_id')
            ->where('rr.recurso_id', $recursoId)
            ->where('r.status', '!=', 'cancelada')
            ->where('r.data_inicial', '<=', $df)
            ->where('r.data_final', '>=', $di)
            ->where('r.horario_inicial', '<', $hf)
            ->where('r.horario_final', '>', $hi)
            ->when($ignorarReservaId, fn ($q) => $q->where('r.id', '!=', $ignorarReservaId))
            ->sum('rr.quantidade');
    }
}

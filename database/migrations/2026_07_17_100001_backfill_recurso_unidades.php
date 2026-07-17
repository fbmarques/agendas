<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $recursos = DB::table('recursos')->get();
        foreach ($recursos as $r) {
            $existentes = DB::table('recurso_unidades')->where('recurso_id', $r->id)->count();
            if ($existentes > 0) continue;

            $qtd = max(1, (int) ($r->quantidade ?? 1));
            $now = now();
            $rows = [];
            for ($n = 1; $n <= $qtd; $n++) {
                $rows[] = [
                    'recurso_id' => $r->id,
                    'patrimonio' => "AUTO-{$r->id}-{$n}",
                    'status' => 'ativo',
                    'observacoes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('recurso_unidades')->insert($rows);
        }
    }

    public function down(): void
    {
        // Sem rollback: a migração acima não recria a coluna quantidade,
        // apenas popula unidades a partir dela; deletar aqui poderia apagar
        // dados legítimos que o usuário criou manualmente depois.
    }
};

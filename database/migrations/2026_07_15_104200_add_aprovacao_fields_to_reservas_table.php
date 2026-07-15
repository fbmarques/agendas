<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->text('motivo_cancelamento')->nullable()->after('observacoes');
            $table->foreignId('aprovada_por_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('aprovada_em')->nullable()->after('aprovada_por_id');
            $table->foreignId('cancelada_por_id')->nullable()->after('aprovada_em')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelada_em')->nullable()->after('cancelada_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropForeign(['aprovada_por_id']);
            $table->dropForeign(['cancelada_por_id']);
            $table->dropColumn([
                'motivo_cancelamento',
                'aprovada_por_id',
                'aprovada_em',
                'cancelada_por_id',
                'cancelada_em',
            ]);
        });
    }
};

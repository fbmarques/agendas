<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('campi_id')->constrained('campi')->cascadeOnDelete();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->foreignId('local_id')->constrained('locais')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('motivo');
            $table->string('tipo_local')->nullable();
            $table->date('data_inicial');
            $table->date('data_final');
            $table->time('horario_inicial');
            $table->time('horario_final');
            $table->string('responsavel_nome');
            $table->text('observacoes')->nullable();
            $table->string('status')->default('confirmada');
            $table->boolean('recorrente')->default(false);
            $table->timestamps();

            $table->index(['local_id', 'data_inicial', 'data_final']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};

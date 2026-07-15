<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_indisponibilidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_id')->constrained('locais')->cascadeOnDelete();
            $table->string('tipo'); // data_especifica | periodo | recorrente_semanal
            $table->date('data_inicial')->nullable();
            $table->date('data_final')->nullable();
            $table->json('dias_semana')->nullable(); // [0..6], 0=Domingo
            $table->time('horario_inicial')->nullable();
            $table->time('horario_final')->nullable();
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->index('local_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_indisponibilidades');
    }
};

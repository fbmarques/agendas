<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurso_disponibilidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurso_id')->constrained('recursos')->cascadeOnDelete();
            $table->json('dias_semana'); // array [0..6]
            $table->time('horario_inicial');
            $table->time('horario_final');
            $table->timestamps();

            $table->index('recurso_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurso_disponibilidades');
    }
};

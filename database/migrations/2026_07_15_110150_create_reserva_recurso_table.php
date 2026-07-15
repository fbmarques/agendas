<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserva_recurso', function (Blueprint $table) {
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('recurso_id')->constrained('recursos')->cascadeOnDelete();
            $table->unsignedInteger('quantidade')->default(1);
            $table->timestamps();

            $table->primary(['reserva_id', 'recurso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_recurso');
    }
};

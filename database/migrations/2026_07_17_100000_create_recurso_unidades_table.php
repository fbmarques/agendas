<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurso_unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurso_id')->constrained('recursos')->cascadeOnDelete();
            $table->string('patrimonio', 60);
            $table->string('status')->default('ativo');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique(['recurso_id', 'patrimonio']);
            $table->index(['recurso_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurso_unidades');
    }
};

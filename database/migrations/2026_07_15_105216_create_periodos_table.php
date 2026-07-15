<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->string('status')->default('ativo');
            $table->timestamps();

            $table->index(['status', 'data_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos');
    }
};

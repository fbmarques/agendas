<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurso_gerentes', function (Blueprint $table) {
            $table->foreignId('recurso_id')->constrained('recursos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['recurso_id', 'user_id']);
        });

        Schema::table('recursos', function (Blueprint $table) {
            $table->string('responsavel_nome')->nullable()->change();
            $table->string('responsavel_email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('recursos', function (Blueprint $table) {
            $table->string('responsavel_nome')->nullable(false)->change();
            $table->string('responsavel_email')->nullable(false)->change();
        });

        Schema::dropIfExists('recurso_gerentes');
    }
};

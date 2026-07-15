<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locais', function (Blueprint $table) {
            $table->boolean('requer_aprovacao')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('locais', function (Blueprint $table) {
            $table->dropColumn('requer_aprovacao');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        Schema::create('legacy_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 50);
            $table->unsignedBigInteger('source_id');
            $table->string('target_type', 50);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();
            $table->unique(['source_table', 'source_id']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_imports');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};

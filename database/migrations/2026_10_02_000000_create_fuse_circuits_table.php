<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('fuse.database.table', 'fuse_circuits');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->string('key', 191)->primary();
            $table->json('payload');
            $table->unsignedBigInteger('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('fuse.database.table', 'fuse_circuits'));
    }
};

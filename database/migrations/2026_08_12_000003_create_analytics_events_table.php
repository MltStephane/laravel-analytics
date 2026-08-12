<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('visitor_id');
            $table->ulid('session_id');
            $table->string('type', 20);
            $table->string('name', 50)->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('title', 255)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at');

            $table->foreign('visitor_id')
                ->references('id')
                ->on('analytics_visitors')
                ->cascadeOnDelete();

            $table->foreign('session_id')
                ->references('id')
                ->on('analytics_sessions')
                ->cascadeOnDelete();

            $table->index(['type', 'created_at']);
            $table->index('visitor_id');
            $table->index('session_id');
            $table->index('name');
        });
    }
};

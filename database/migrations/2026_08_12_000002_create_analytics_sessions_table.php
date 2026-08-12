<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('visitor_id');
            $table->string('hostname', 255);
            $table->string('path', 2048);
            $table->string('referrer', 2048)->nullable();
            $table->string('referrer_domain', 255)->nullable();
            $table->string('utm_source', 255)->nullable();
            $table->string('utm_medium', 255)->nullable();
            $table->string('utm_campaign', 255)->nullable();
            $table->string('utm_term', 255)->nullable();
            $table->string('utm_content', 255)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at')->nullable();
            $table->unsignedInteger('duration')->default(0);
            $table->boolean('bounced')->default(true);
            $table->unsignedInteger('pages_count')->default(1);
            $table->timestamps();

            $table->foreign('visitor_id')
                ->references('id')
                ->on('analytics_visitors')
                ->cascadeOnDelete();

            $table->index('started_at');
            $table->index('last_activity_at');
        });
    }
};

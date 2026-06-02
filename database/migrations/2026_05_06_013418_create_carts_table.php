<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_carts_table
 *
 * ERD spec:
 *   PK  id
 *       updated_at
 *       user_id (UNIQUE) — one cart per user, cross-service ref to Auth Service
 *
 * Note: created_at added alongside updated_at (Laravel convention, functionally needed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();                              // PK  — BIGINT AUTO INCREMENT

            // One cart per user. user_id is a cross-service reference (no local FK).
            $table->unsignedBigInteger('user_id')->unique();

            $table->timestamps();                      // created_at + updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};

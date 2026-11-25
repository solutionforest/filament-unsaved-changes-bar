<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->date('published_at')->nullable();
            $table->string('cover')->nullable();
            $table->json('tags')->nullable();
            $table->json('highlights')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_name');
            $table->string('support_email');
            $table->string('currency')->default('HKD');
            $table->boolean('maintenance_mode')->default(false);
            $table->timestamps();
        });
    }
};

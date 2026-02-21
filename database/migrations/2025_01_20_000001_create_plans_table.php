<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('hash', 64)->unique();
            $table->text('plan_text');
            $table->json('settings');
            $table->json('normalized_json')->nullable();
            $table->json('schedule')->nullable();
            $table->string('validation_status')->default('pending');
            $table->string('publish_status')->default('draft');
            $table->string('trello_board_id')->nullable();
            $table->string('trello_board_url')->nullable();
            $table->string('google_calendar_id')->nullable();
            $table->timestamps();

            $table->index('hash');
            $table->index('publish_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

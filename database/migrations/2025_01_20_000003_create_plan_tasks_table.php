<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_week_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('estimate_hours', 5, 2)->default(0);
            $table->string('status')->default('pending');
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();
            $table->string('trello_card_id')->nullable();
            $table->string('google_event_id')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'status']);
            $table->index('scheduled_date');
            $table->index('trello_card_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_tasks');
    }
};

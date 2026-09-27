<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_numbers', function (Blueprint $table) {
           $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('phone_number')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
 
            $table->index(['phone_number', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_numbers');
    }
};

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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId(column:'category_id')->references(column:'id')->on(table:'categories');
            $table->string('code')->unique(); 
            $table->string('name');
            $table->string('model')->nullable();
            $table->string('photo')->nullable(); 
            $table->decimal('price', 10, 2); 
            $table->enum('stock', ['available', 'empty'])->default('available');
            $table->text('description')->nullable();
            $table->timestamps();  
            #$table->foreignId(column:'category_id')->constraint('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

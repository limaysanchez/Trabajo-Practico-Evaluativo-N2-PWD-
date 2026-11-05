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
        Schema::create('imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destino_id')->constrained()->cascadeOnDelete();
            $table->string('titulo', 120);
            $table->char('hash_hex', 16);
            $table->string('mime_type', 50);
            // binary() crea un BLOB de 64 KB en MySQL, insuficiente para fotos de hasta 5 MB.
            $table->rawColumn('contenido', 'mediumblob');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imagenes');
    }
};

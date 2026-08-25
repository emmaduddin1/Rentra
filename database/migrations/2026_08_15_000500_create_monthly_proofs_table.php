<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 8.1 - monthly_proofs: proof attachments by payment category.
 *
 * NFR-SEC-007 - stored on a private disk and served through authorised
 * download routes only; `file_path` must never be publicly guessable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_record_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('disk')->default('local');
            $table->string('file_path');
            $table->string('mime_type', 128)->nullable();
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['monthly_record_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_proofs');
    }
};

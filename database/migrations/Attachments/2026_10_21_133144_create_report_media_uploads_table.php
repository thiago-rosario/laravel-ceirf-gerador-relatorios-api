<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_media_uploads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('report_id');
            $table->uuid('root_report_id');
            $table->unsignedBigInteger('uploaded_by');
            $table->string('file_name', 255);
            $table->string('storage_identifier', 255)->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64);
            $table->uuid('replaces_id')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['root_report_id', 'checksum'], 'uq_report_media_uploads_root_checksum');
            $table->index(['report_id', 'linked_at'], 'idx_report_media_uploads_pending');
            $table->foreign('report_id')->references('id')->on('reports')->noActionOnDelete()->noActionOnUpdate();
            $table->foreign('root_report_id')->references('uuid')->on('report_series')->noActionOnDelete()->noActionOnUpdate();
            $table->foreign('uploaded_by')->references('id')->on('users')->noActionOnDelete()->noActionOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_media_uploads');
    }
};

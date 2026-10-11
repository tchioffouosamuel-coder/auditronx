<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::create('central_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auteur_type')->nullable();
            $table->unsignedBigInteger('auteur_id')->nullable();
            $table->string('auteur_nom')->nullable();
            $table->string('auteur_email')->nullable();
            $table->string('auteur_accreditation')->nullable();
            $table->string('action');
            $table->string('sujet_type')->nullable();
            $table->unsignedBigInteger('sujet_id')->nullable();
            $table->string('sujet_libelle')->nullable();
            $table->json('changements')->nullable();
            $table->json('contexte')->nullable();
            $table->string('methode', 10)->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('route')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->unsignedSmallInteger('statut')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('action');
            $table->index('created_at');
            $table->index(['sujet_type', 'sujet_id']);
            $table->index(['auteur_type', 'auteur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_audit_logs');
    }
};

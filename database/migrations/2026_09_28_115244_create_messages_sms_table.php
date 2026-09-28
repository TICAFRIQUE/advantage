<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suivi de chaque SMS (OTP, alertes d'expiration). Le contenu est chiffré ;
     * celui d'un OTP est remplacé par une version masquée dès l'envoi réel.
     */
    public function up(): void
    {
        Schema::create('messages_sms', function (Blueprint $table) {
            $table->id();
            $table->string('telephone', 16)->index();
            $table->string('type', 30);
            $table->text('contenu');
            $table->string('statut', 20)->default('en_attente')->index();
            $table->string('fournisseur', 30);
            $table->string('reference_fournisseur')->nullable();
            $table->text('erreur')->nullable();
            $table->unsignedTinyInteger('tentatives')->default(0);
            $table->timestamp('envoye_le')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages_sms');
    }
};

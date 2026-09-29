<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relie chaque alerte d'expiration envoyée par SMS au message correspondant :
 * l'état de livraison réel (envoyé, en échec) se lit sur messages_sms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertes_expiration', function (Blueprint $table) {
            $table->foreignId('message_sms_id')->nullable()->after('canal')->constrained('messages_sms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alertes_expiration', function (Blueprint $table) {
            $table->dropConstrainedForeignId('message_sms_id');
        });
    }
};

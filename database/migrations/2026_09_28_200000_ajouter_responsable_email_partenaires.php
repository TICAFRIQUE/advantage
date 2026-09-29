<?php

use App\Services\Telephone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Responsable et email (facultatifs) du partenaire. Le contact devient un
     * téléphone au format E.164 (« +<indicatif><numéro> »).
     *
     * Reprise de l'existant : un contact reconnu comme téléphone est normalisé ;
     * sinon il est déplacé vers l'email ou le responsable, et le contact vidé.
     */
    public function up(): void
    {
        Schema::table('partenaires', function (Blueprint $table) {
            $table->string('responsable', 150)->nullable()->after('contact');
            $table->string('email', 190)->nullable()->after('responsable');
        });

        DB::table('partenaires')->whereNotNull('contact')->orderBy('id')->each(function (object $partenaire): void {
            $contact = trim($partenaire->contact);
            $telephone = Telephone::normaliser($contact);

            DB::table('partenaires')->where('id', $partenaire->id)->update(match (true) {
                $telephone !== null => ['contact' => $telephone],
                filter_var($contact, FILTER_VALIDATE_EMAIL) !== false => ['contact' => null, 'email' => mb_strtolower($contact)],
                default => ['contact' => null, 'responsable' => mb_substr($contact, 0, 150) ?: null],
            });
        });
    }

    public function down(): void
    {
        Schema::table('partenaires', function (Blueprint $table) {
            $table->dropColumn(['responsable', 'email']);
        });
    }
};

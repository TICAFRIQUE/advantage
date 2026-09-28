<?php

namespace App\Services\Sms;

use Illuminate\Support\Str;

/**
 * Pilote de simulation (en attendant l'API du fournisseur) : aucun envoi
 * réel ; le message reste consultable dans la boîte « SMS simulés ».
 * Interdit en production (voir AppServiceProvider).
 */
class PasserelleSmsSimulee implements PasserelleSms
{
    public function nom(): string
    {
        return 'simulation';
    }

    public function envoyer(string $telephone, string $message): ResultatEnvoiSms
    {
        return ResultatEnvoiSms::reussi('SIM-'.Str::upper(Str::random(12)));
    }
}

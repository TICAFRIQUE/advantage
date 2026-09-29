<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Pilote du fournisseur TICAFRIQUE SMS (API REST, jeton Bearer).
 *
 * Réponse attendue : HTTP 200 et { "success": true, "data": { "message_id": … } }.
 * Tout autre cas est un échec : le job d'envoi (EnvoyerSmsJob) retente
 * automatiquement. Ni le contenu du message ni la clé ne sont journalisés ;
 * le numéro n'apparaît jamais dans les messages d'erreur conservés.
 */
class PasserelleSmsTicafrique implements PasserelleSms
{
    public function __construct(
        private readonly string $url,
        #[\SensitiveParameter] private readonly string $cle,
        private readonly string $expediteur,
        private readonly int $delai = 10,
    ) {
        if ($url === '' || $cle === '') {
            throw new InvalidArgumentException('TICAFRIQUE SMS : renseignez TICAFRIQUE_SMS_API_URL et TICAFRIQUE_SMS_API_KEY.');
        }

        if (! str_starts_with($url, 'https://')) {
            throw new InvalidArgumentException('TICAFRIQUE SMS : l\'URL de l\'API doit être en HTTPS.');
        }
    }

    public function nom(): string
    {
        return 'ticafrique';
    }

    public function envoyer(string $telephone, string $message): ResultatEnvoiSms
    {
        try {
            $reponse = Http::withToken($this->cle)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout($this->delai)
                ->post($this->url, [
                    'to' => $telephone,
                    'message' => $message,
                    'sender_id' => $this->expediteur,
                ]);
        } catch (ConnectionException) {
            return ResultatEnvoiSms::echoue('Fournisseur SMS injoignable (délai dépassé ou réseau).');
        }

        $identifiant = $reponse->json('data.message_id');

        if ($reponse->status() === 200 && $reponse->json('success') === true && filled($identifiant)) {
            return ResultatEnvoiSms::reussi((string) $identifiant);
        }

        $detail = Str::limit(trim((string) ($reponse->json('message') ?? '')), 150);

        return ResultatEnvoiSms::echoue("Refus du fournisseur (HTTP {$reponse->status()})".($detail !== '' ? " : {$detail}" : '.'));
    }
}

<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Pilote du fournisseur TICAFRIQUE SMS (API REST, jeton Bearer).
 *
 * Réponse réelle (constatée le 29/09/2026) : HTTP 200 et
 * { "success": true, "message": "SMS sent successfully", "data": { "message_ids": ["…"],
 *   "recipient", "parts_sent", "total_segments", "total_cost", "currency" } }.
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

        $identifiant = collect(self::CHEMINS_IDENTIFIANT)->map(fn (string $chemin) => $reponse->json($chemin))->first(fn ($valeur) => is_scalar($valeur) && filled($valeur));

        if (! $this->estUnSucces($reponse->successful(), $reponse->json())) {
            $detail = Str::limit(trim((string) ($reponse->json('message') ?? '')), 150);

            return ResultatEnvoiSms::echoue("Refus du fournisseur (HTTP {$reponse->status()})".($detail !== '' ? " : {$detail}" : '.'));
        }

        // Succès déclaré par le fournisseur : on ne le transforme JAMAIS en échec
        // (le job retenterait et le titulaire recevrait le SMS en double).
        if ($identifiant === null) {
            Log::warning('TICAFRIQUE SMS : succès sans identifiant de message reconnu.', ['structure' => self::structure($reponse->json())]);

            return ResultatEnvoiSms::reussi('TICAFRIQUE-'.Str::upper(Str::random(10)));
        }

        return ResultatEnvoiSms::reussi((string) $identifiant);
    }

    /**
     * Emplacements possibles de l'identifiant du message dans la réponse.
     *
     * @var list<string>
     */
    private const CHEMINS_IDENTIFIANT = ['data.message_ids.0', 'data.message_id', 'data.messageId', 'data.id', 'data.sms_id', 'message_id', 'messageId', 'id', 'data.0.message_id', 'data.0.id'];

    /**
     * HTTP 2xx et succès déclaré (booléen ou statut texte), ou, à défaut
     * d'indicateur, un message explicite de réussite.
     */
    private function estUnSucces(bool $http2xx, mixed $corps): bool
    {
        if (! $http2xx || ! is_array($corps)) {
            return false;
        }

        $succes = $corps['success'] ?? $corps['status'] ?? null;

        if ($succes !== null) {
            return in_array(is_string($succes) ? mb_strtolower($succes) : $succes, [true, 1, '1', 'true', 'success', 'ok', 'sent'], true);
        }

        return str_contains(mb_strtolower((string) ($corps['message'] ?? '')), 'success');
    }

    /**
     * Structure d'une réponse (clés et types, jamais les valeurs) pour le
     * diagnostic, sans exposer numéro, contenu ni identifiants.
     *
     * @return array<string, mixed>|string
     */
    private static function structure(mixed $valeur): array|string
    {
        return is_array($valeur) ? array_map(self::structure(...), $valeur) : get_debug_type($valeur);
    }
}

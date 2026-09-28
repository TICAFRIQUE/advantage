<?php

namespace App\Jobs;

use App\Enums\StatutLivraison;
use App\Models\MessageSms;
use App\Services\Sms\PasserelleSms;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Envoie un SMS via le fournisseur configuré, avec nouvelles tentatives.
 *
 * La charge utile du job ne contient que l'identifiant du message (jamais
 * le code OTP en clair dans la table `jobs`). Idempotent : un message déjà
 * envoyé n'est jamais réexpédié.
 */
class EnvoyerSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(public MessageSms $message) {}

    public function handle(PasserelleSms $passerelle): void
    {
        $message = $this->message->fresh();

        if ($message === null || $message->statut === StatutLivraison::Envoyee) {
            return;
        }

        $message->increment('tentatives');
        $resultat = $passerelle->envoyer($message->telephone, $message->contenu);

        if (! $resultat->succes) {
            $message->forceFill(['erreur' => $resultat->erreur])->save();

            throw new RuntimeException("Échec d'envoi du SMS #{$message->id} : {$resultat->erreur}");
        }

        $message->forceFill([
            'statut' => StatutLivraison::Envoyee,
            'reference_fournisseur' => $resultat->reference,
            'envoye_le' => now(),
            'erreur' => null,
            // Un code OTP n'est jamais conservé lisible après un envoi réel.
            'contenu' => $message->type->contenuSensible() && $passerelle->nom() !== 'simulation'
                ? '[contenu masqué après envoi]'
                : $message->contenu,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $this->message->forceFill([
            'statut' => StatutLivraison::Echec,
            'erreur' => $exception?->getMessage(),
        ])->save();
    }
}

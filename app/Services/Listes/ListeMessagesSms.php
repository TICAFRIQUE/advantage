<?php

namespace App\Services\Listes;

use App\Enums\StatutLivraison;
use App\Enums\TypeSms;
use App\Models\MessageSms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Système › Historique des SMS (filtres : période, type, statut ; recherche
 * par numéro). Le contenu des messages n'est jamais affiché ni exporté.
 *
 * @extends Liste<MessageSms>
 */
class ListeMessagesSms extends Liste
{
    public function cle(): string
    {
        return 'historique-sms';
    }

    public function titre(): string
    {
        return 'Historique des SMS';
    }

    public function requete(): Builder
    {
        $f = $this->filtres;

        return MessageSms::query()
            ->when($f['du'] ?? null, fn ($q, string $du) => $q->where('created_at', '>=', $du.' 00:00:00'))
            ->when($f['au'] ?? null, fn ($q, string $au) => $q->where('created_at', '<=', $au.' 23:59:59'))
            ->when($f['type'] ?? null, fn ($q, string $type) => $q->where('type', $type))
            ->when($f['statut'] ?? null, fn ($q, string $statut) => $q->where('statut', $statut));
    }

    /**
     * Recherche par numéro : fin du numéro (« 0707123456 » retrouve « +2250707123456 »).
     */
    public function rechercher(Builder $query, string $recherche): void
    {
        $chiffres = (string) preg_replace('/\D/', '', $recherche);

        // Sans chiffre, rien ne peut correspondre à un numéro.
        $chiffres === '' ? $query->whereRaw('1 = 0') : $query->where('telephone', 'like', '%'.$chiffres);
    }

    protected function trier(Builder $query): Builder
    {
        return $query->latest('created_at')->latest('id');
    }

    public function colonnes(): array
    {
        return ['Date', 'Numéro', 'Type', 'Pilote', 'Statut', 'Essais', 'Envoyé le', 'Détail'];
    }

    /**
     * @param  MessageSms  $modele
     */
    public function ligne(Model $modele): array
    {
        return [
            $modele->created_at->format('d/m/Y H:i:s'),
            $modele->telephoneFormate(),
            $modele->type->libelle(),
            $modele->fournisseur,
            $modele->statut->libelle(),
            $modele->tentatives,
            $modele->envoye_le?->format('d/m/Y H:i:s'),
            $modele->erreur ?? $modele->reference_fournisseur,
        ];
    }

    public function filtresLisibles(): array
    {
        $f = $this->filtres;

        return array_filter([
            'Du' => $f['du'] ?? null,
            'Au' => $f['au'] ?? null,
            'Type' => filled($f['type'] ?? null) ? TypeSms::from($f['type'])->libelle() : null,
            'Statut' => filled($f['statut'] ?? null) ? StatutLivraison::from($f['statut'])->libelle() : null,
        ]);
    }
}

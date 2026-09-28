<x-layouts.app titre="Remise accordée" sous-titre="Espace Partenaire">
    <div class="parcours-caisse mx-auto">
        <ol class="etapes-caisse" aria-label="Étapes">
            <li class="fait">Carte</li>
            <li class="fait">Code</li>
            <li class="actif" aria-current="step">Remise</li>
        </ol>

        <section class="card border-0 shadow-sm resultat-caisse resultat-caisse--valide mb-3" role="status">
            <div class="card-body p-4 text-center">
                <i class="bi bi-patch-check-fill resultat-caisse__icone" aria-hidden="true"></i>
                <p class="fs-3 fw-bold mb-1">Remise de {{ rtrim(rtrim((string) $transaction->taux_applique, '0'), '.') }} % accordée</p>
                <p class="mb-0">{{ $transaction->partenaire->nom }} · {{ $transaction->validee_le->format('d/m/Y à H:i') }}</p>
            </div>
        </section>

        <section class="card border-0 shadow-sm mb-3" aria-labelledby="titre-titulaire">
            <div class="card-body p-4">
                <p class="small text-secondary mb-1">Titulaire</p>
                <h1 class="h4 fw-bold mb-3" id="titre-titulaire">{{ $transaction->carte->titulaire->nomComplet() }}</h1>
                <x-carte-visuelle :carte="$transaction->carte" class="mb-3" />
                <p class="small mb-0">Validée par <strong>{{ $transaction->validePar?->libelleActeur() ?? '—' }}</strong></p>
            </div>
        </section>

        <a href="{{ route('partenaire.verifier') }}" class="btn btn-or btn-lg w-100 py-3 mb-2">
            <i class="bi bi-plus-circle me-2" aria-hidden="true"></i>Nouvelle vérification
        </a>
        <a href="{{ route('partenaire.transactions.index') }}" class="btn btn-outline-primary btn-lg w-100">Historique des passages</a>
    </div>
</x-layouts.app>

<x-layouts.app titre="Supprimer la carte" sous-titre="Back-office · Cartes">
    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('gestion.cartes.index') }}">Cartes</a></li>
            <li class="breadcrumb-item"><a href="{{ route('gestion.cartes.show', $carte) }}">{{ $carte->numeroFormate() }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Suppression définitive</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-7 col-xl-6">
            <h1 class="h3 fw-bold mb-3">Supprimer définitivement la carte {{ $carte->numeroFormate() }}</h1>

            <div class="alert alert-danger d-flex gap-2" role="alert">
                <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                <div>
                    <strong>Action irréversible.</strong> Réservée aux cartes de test : pour une carte perdue ou volée, utilisez la révocation,
                    qui conserve l'historique. Seule une sauvegarde permet de revenir en arrière.
                </div>
            </div>

            <section class="card border-0 shadow-sm mb-4" aria-labelledby="titre-effacement">
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold" id="titre-effacement">Ce qui sera effacé</h2>
                    <ul class="small mb-0" id="liste-effacement">
                        <li>La carte <strong>{{ $carte->numeroFormate() }}</strong> — son numéro pourra être activé de nouveau.</li>
                        <li>
                            <strong>{{ $transactions }}</strong> transaction(s)
                            @if ($transactions > 0)
                                chez <strong>{{ $partenaires }}</strong> partenaire(s) : elles disparaîtront de leur historique et des rapports.
                            @endif
                        </li>
                        <li><strong>{{ $codes }}</strong> code(s) de validation, <strong>{{ $alertes }}</strong> alerte(s) d'expiration, <strong>{{ $operations }}</strong> opération(s) de l'historique.</li>
                        <li>
                            @if ($autresCartes === 0)
                                Le titulaire <strong>{{ $carte->titulaire->nomComplet() }}</strong> ({{ $carte->titulaire->telephoneFormate() }})
                                et les SMS qui lui ont été envoyés : il n'a aucune autre carte. Son téléphone pourra être réutilisé.
                            @else
                                Le titulaire <strong>{{ $carte->titulaire->nomComplet() }}</strong> est conservé : il a {{ $autresCartes }} autre(s) carte(s).
                            @endif
                        </li>
                    </ul>
                </div>
            </section>

            <form method="POST" action="{{ route('gestion.cartes.destroy', $carte) }}" class="card border-0 shadow-sm" novalidate
                  data-titre="Supprimer la carte {{ $carte->numeroFormate() }} ?"
                  data-confirmer="La carte et tout son historique seront effacés. Cette action est irréversible."
                  data-bouton-confirmer="Supprimer définitivement" data-danger="1">
                @csrf
                @method('DELETE')
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label for="motif" class="form-label fw-semibold">Motif de la suppression</label>
                        <input type="text" id="motif" name="motif" value="{{ old('motif') }}" maxlength="200" required
                               class="form-control @error('motif') is-invalid @enderror" aria-describedby="aide-motif" autofocus>
                        @error('motif')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div id="aide-motif" class="form-text">Conservé dans le registre permanent des cartes supprimées (ex. « carte de test avant ouverture »).</div>
                    </div>

                    <div class="mb-3">
                        <label for="numero_confirmation" class="form-label fw-semibold">Retapez le numéro de la carte pour confirmer</label>
                        <input type="text" id="numero_confirmation" name="numero_confirmation" inputmode="numeric" autocomplete="off" maxlength="9" required
                               class="form-control form-control-lg @error('numero_confirmation') is-invalid @enderror"
                               placeholder="{{ $carte->numeroFormate() }}">
                        @error('numero_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" class="btn btn-danger btn-lg w-100 mt-2">
                        <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Supprimer définitivement
                    </button>
                    <a href="{{ route('gestion.cartes.show', $carte) }}" class="btn btn-link w-100 mt-2">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>

<x-layouts.app titre="Espace partenaire" sous-titre="Espace Partenaire">
    <h1 class="h3 fw-bold mb-3">Espace partenaire</h1>

    @if ($peutChoisir)
        <section class="card border-0 shadow-sm mb-4" aria-labelledby="titre-choix">
            <div class="card-body">
                <h2 class="h6 fw-bold" id="titre-choix">
                    <i class="bi bi-person-badge me-1" aria-hidden="true"></i>Agir pour le compte d'un partenaire
                </h2>
                <p class="small text-secondary">
                    En tant qu'administrateur, choisissez le partenaire concerné. Chaque validation sera enregistrée
                    pour ce partenaire, à votre nom.
                </p>
                <form method="POST" action="{{ route('partenaire.partenaire-courant.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12 col-md-8">
                        <label for="partenaire_id" class="form-label fw-semibold">Partenaire</label>
                        <select id="partenaire_id" name="partenaire_id" required
                                class="form-select form-select-lg @error('partenaire_id') is-invalid @enderror">
                            <option value="">— Choisir —</option>
                            @foreach ($partenairesActifs as $choix)
                                <option value="{{ $choix->id }}" @selected($partenaire?->id === $choix->id)>
                                    {{ $choix->nom }}{{ $choix->localisation ? ' — '.$choix->localisation : '' }} ({{ rtrim(rtrim((string) $choix->taux_reduction, '0'), '.') }} %)
                                </option>
                            @endforeach
                        </select>
                        @error('partenaire_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4 d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Agir pour ce partenaire</button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    @if ($partenaire)
        <x-bandeau-partenaire :partenaire="$partenaire" />

        @can('verifier-carte')
            <a href="{{ route('partenaire.verifier') }}" class="btn btn-or btn-lg w-100 py-3 mb-4 fs-5">
                <i class="bi bi-credit-card-2-front me-2" aria-hidden="true"></i>Vérifier une carte
            </a>
        @endcan

        <div class="row g-3 mb-4">
            @foreach ([['Passages aujourd\'hui', $indicateurs['aujourd_hui']], ['Passages ce mois-ci', $indicateurs['ce_mois']]] as [$libelle, $valeur])
                <div class="col-6">
                    <div class="card border-0 shadow-sm fond-nuit h-100">
                        <div class="card-body">
                            <p class="small text-white-50 mb-1">{{ $libelle }}</p>
                            <p class="display-6 fw-bold texte-or mb-0">{{ $valeur }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @can('voir-ses-transactions')
            <a href="{{ route('partenaire.transactions.index') }}" class="btn btn-outline-primary btn-lg w-100">
                <i class="bi bi-clock-history me-2" aria-hidden="true"></i>Historique des passages
            </a>
        @endcan
    @elseif (! $peutChoisir)
        <div class="alert alert-warning" role="alert">Aucun partenaire actif n'est associé à ce compte.</div>
    @endif
</x-layouts.app>

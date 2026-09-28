<x-layouts.app :titre="$partenaire->nom" sous-titre="Back-office · Partenaires">
    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('gestion.partenaires.index') }}">Partenaires</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $partenaire->nom }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <section class="card border-0 shadow-sm mb-4" aria-labelledby="titre-partenaire">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <h1 class="h4 fw-bold mb-0" id="titre-partenaire">{{ $partenaire->nom }}</h1>
                        <span class="badge {{ $partenaire->estActif() ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $partenaire->statut->libelle() }}</span>
                    </div>
                    <p class="display-6 fw-bold texte-or-fonce mb-2">{{ $partenaire->tauxFormate() }}</p>
                    <dl class="row small mb-3">
                        <dt class="col-5">Secteur</dt><dd class="col-7">{{ $partenaire->secteur ?? '—' }}</dd>
                        <dt class="col-5">Localisation</dt><dd class="col-7">{{ $partenaire->localisation ?? '—' }}</dd>
                        <dt class="col-5">Contact</dt><dd class="col-7">{{ $partenaire->contact ?? '—' }}</dd>
                        <dt class="col-5">Passages</dt><dd class="col-7">{{ $partenaire->transactions_count }}</dd>
                    </dl>

                    @can('update', $partenaire)
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('gestion.partenaires.edit', $partenaire) }}" class="btn btn-outline-primary flex-grow-1">
                                <i class="bi bi-pencil me-1" aria-hidden="true"></i>Modifier
                            </a>
                            <form method="POST" action="{{ route('gestion.partenaires.statut', $partenaire) }}" class="flex-grow-1"
                                  data-titre="{{ $partenaire->estActif() ? 'Désactiver' : 'Réactiver' }} {{ $partenaire->nom }} ?"
                                  data-confirmer="{{ $partenaire->estActif() ? 'Ses utilisateurs ne pourront plus effectuer de transaction.' : 'Ses utilisateurs pourront de nouveau effectuer des transactions.' }}"
                                  data-bouton-confirmer="{{ $partenaire->estActif() ? 'Désactiver' : 'Réactiver' }}" @if ($partenaire->estActif()) data-danger="1" @endif>
                                @csrf
                                <input type="hidden" name="statut" value="{{ $partenaire->estActif() ? 'inactif' : 'actif' }}">
                                <button type="submit" class="btn w-100 {{ $partenaire->estActif() ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                    {{ $partenaire->estActif() ? 'Désactiver' : 'Réactiver' }}
                                </button>
                            </form>
                        </div>
                    @endcan
                </div>
            </section>

            <section class="card border-0 shadow-sm" aria-labelledby="titre-taux">
                <div class="card-body">
                    <h2 class="h6 fw-bold" id="titre-taux">Historique du taux</h2>
                    <ul class="list-unstyled small mb-0">
                        @forelse ($partenaire->historiqueTaux as $changement)
                            <li class="border-start border-3 border-warning ps-2 mb-2">
                                <span class="fw-semibold">
                                    {{ $changement->ancien_taux !== null ? rtrim(rtrim((string) $changement->ancien_taux, '0'), '.').' % → ' : 'Taux initial : ' }}{{ rtrim(rtrim((string) $changement->nouveau_taux, '0'), '.') }} %
                                </span>
                                <span class="d-block text-secondary">{{ $changement->modifie_le->format('d/m/Y à H:i') }} — {{ $changement->modifiePar?->libelleActeur() ?? 'Système' }}</span>
                            </li>
                        @empty
                            <li class="text-secondary">Aucun changement enregistré.</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-8">
            <section class="card border-0 shadow-sm mb-4" aria-labelledby="titre-operateurs">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-1" id="titre-operateurs">Utilisateurs du partenaire</h2>
                    <p class="small text-secondary mb-3">Comptes de connexion du personnel du partenaire (caissiers, gérants) : ils se connectent à l'espace partenaire pour vérifier les cartes et valider les remises. Chaque transaction indique quel utilisateur l'a validée.</p>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Nom</th>
                                    <th scope="col">Identifiant</th>
                                    <th scope="col">État</th>
                                    <th scope="col">Dernière connexion</th>
                                    <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($partenaire->operateurs as $operateur)
                                    <tr>
                                        <td>{{ $operateur->nom }}</td>
                                        <td class="font-monospace small">{{ '@'.$operateur->nom_utilisateur }}</td>
                                        <td>
                                            @if ($operateur->estVerrouille())
                                                <span class="badge text-bg-danger">Verrouillé</span>
                                            @elseif ($operateur->statut !== App\Enums\StatutUtilisateur::Actif)
                                                <span class="badge text-bg-secondary">Désactivé</span>
                                            @else
                                                <span class="badge text-bg-success">Actif</span>
                                            @endif
                                        </td>
                                        <td class="small">{{ $operateur->derniere_connexion_le?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                                        <td><x-actions-compte :compte="$operateur" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-secondary">Aucun utilisateur : ajoutez-en un pour que le partenaire puisse effectuer des transactions depuis son espace.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            @can('gererOperateurs', $partenaire)
                <form method="POST" action="{{ route('gestion.partenaires.operateurs.store', $partenaire) }}" novalidate
                      class="card border-0 shadow-sm" aria-labelledby="titre-nouvel-operateur">
                    @csrf
                    <div class="card-body">
                        <h2 class="h6 fw-bold" id="titre-nouvel-operateur">
                            <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Ajouter un utilisateur du partenaire
                        </h2>
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="nom" class="form-label fw-semibold">Nom complet</label>
                                <input type="text" id="nom" name="nom" value="{{ old('nom') }}" required maxlength="150"
                                       class="form-control @error('nom') is-invalid @enderror">
                                @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="nom_utilisateur" class="form-label fw-semibold">Nom d'utilisateur</label>
                                <input type="text" id="nom_utilisateur" name="nom_utilisateur" value="{{ old('nom_utilisateur') }}" required maxlength="50"
                                       autocapitalize="none" spellcheck="false" placeholder="ex. pharmacie.caisse1"
                                       class="form-control font-monospace @error('nom_utilisateur') is-invalid @enderror">
                                @error('nom_utilisateur')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="telephone" class="form-label fw-semibold">Téléphone <span class="text-secondary fw-normal">(facultatif)</span></label>
                                <input type="tel" id="telephone" name="telephone" value="{{ old('telephone') }}" inputmode="tel" maxlength="25"
                                       class="form-control @error('telephone') is-invalid @enderror">
                                @error('telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <p class="small text-secondary mt-2 mb-3">Un PIN à 5 chiffres sera généré et affiché une seule fois.</p>
                        <button type="submit" class="btn btn-primary">Créer l'utilisateur</button>
                    </div>
                </form>
            @endcan
        </div>
    </div>
</x-layouts.app>

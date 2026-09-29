<x-layouts.app titre="Journal d'audit" sous-titre="Back-office · Paramètres">
    @push('scripts')
        @vite('resources/js/tableaux.js')
    @endpush

    <h1 class="h3 fw-bold mb-1">Journal d'audit</h1>
    <p class="text-secondary mb-3">
        Toutes les actions des utilisateurs (connexions, cartes, transactions, comptes, droits), conservées {{ $retention }} jours
        puis purgées automatiquement chaque nuit. Le journal n'est jamais modifiable ; chaque purge est inscrite au registre.
    </p>

    {{-- Erreur de purge : on rouvre l'onglet du registre pour l'afficher. --}}
    @php($ongletPurges = $errors->hasAny(['avant', 'motif']))

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link @unless ($ongletPurges) active @endunless" id="onglet-entrees" data-bs-toggle="tab" data-bs-target="#panneau-entrees" type="button"
                    role="tab" aria-controls="panneau-entrees" aria-selected="{{ $ongletPurges ? 'false' : 'true' }}">Entrées</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link @if ($ongletPurges) active @endif" id="onglet-purges" data-bs-toggle="tab" data-bs-target="#panneau-purges" type="button"
                    role="tab" aria-controls="panneau-purges" aria-selected="{{ $ongletPurges ? 'true' : 'false' }}">Registre des purges</button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade @unless ($ongletPurges) show active @endunless" id="panneau-entrees" role="tabpanel" aria-labelledby="onglet-entrees" tabindex="0">
            <form method="GET" action="{{ route('gestion.journal.index') }}" id="filtres-journal"
                  class="card card-body shadow-sm border-0 mb-4" aria-label="Filtres du journal">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label for="du" class="form-label fw-semibold">Du</label>
                        <input type="date" id="du" name="du" value="{{ $filtres['du'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="au" class="form-label fw-semibold">Au</label>
                        <input type="date" id="au" name="au" value="{{ $filtres['au'] ?? '' }}" class="form-control @error('au') is-invalid @enderror">
                        @error('au')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="action" class="form-label fw-semibold">Action</label>
                        <select id="action" name="action" class="form-select">
                            <option value="">Toutes</option>
                            @foreach (App\Support\LibellesAudit::ACTIONS as $groupe => $actions)
                                <optgroup label="{{ $groupe }}">
                                    @foreach ($actions as $code => $libelle)
                                        <option value="{{ $code }}" @selected(($filtres['action'] ?? null) === $code)>{{ $libelle }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="type_entite" class="form-label fw-semibold">Élément</label>
                        <select id="type_entite" name="type_entite" class="form-select">
                            <option value="">Tous</option>
                            @foreach (array_unique(App\Support\LibellesAudit::ENTITES) as $type => $libelle)
                                <option value="{{ $type }}" @selected(($filtres['type_entite'] ?? null) === $type)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="acteur" class="form-label fw-semibold">Auteur</label>
                        <input type="text" id="acteur" name="acteur" value="{{ $filtres['acteur'] ?? '' }}" maxlength="50"
                               autocapitalize="none" placeholder="nom ou identifiant" class="form-control">
                    </div>
                    <div class="col-12 col-md-1 d-grid">
                        <button type="submit" class="btn btn-primary" aria-label="Appliquer les filtres"><i class="bi bi-funnel" aria-hidden="true"></i></button>
                    </div>
                </div>
            </form>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <table class="table table-striped align-middle w-100" id="tableau-journal"
                           data-source="{{ route('gestion.journal.donnees') }}" data-filtres="#filtres-journal">
                        <thead>
                            <tr>
                                <th scope="col" data-colonne="cree_le">Date</th>
                                <th scope="col" data-colonne="auteur" data-triable="false">Auteur</th>
                                <th scope="col" data-colonne="action_libelle" data-triable="false">Action</th>
                                <th scope="col" data-colonne="element" data-triable="false">Élément</th>
                                <th scope="col" data-colonne="adresse_ip" data-triable="false">Adresse IP</th>
                                <th scope="col" data-colonne="details" data-triable="false">Détails</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade @if ($ongletPurges) show active @endif" id="panneau-purges" role="tabpanel" aria-labelledby="onglet-purges" tabindex="0">
            @if ($peutPurger)
                <form method="POST" action="{{ route('gestion.journal.purger') }}" novalidate class="card border-danger-subtle shadow-sm mb-4"
                      data-titre="Purger le journal d'audit ?" data-confirmer="Les entrées antérieures à la date choisie seront définitivement supprimées."
                      data-bouton-confirmer="Purger" data-danger="1">
                    @csrf
                    <div class="card-body">
                        <h2 class="h6 fw-bold">Purge manuelle</h2>
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-3">
                                <label for="avant" class="form-label fw-semibold">Supprimer les entrées antérieures au</label>
                                <input type="date" id="avant" name="avant" value="{{ old('avant') }}" max="{{ now()->toDateString() }}" required
                                       class="form-control @error('avant') is-invalid @enderror">
                                @error('avant')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="motif" class="form-label fw-semibold">Motif</label>
                                <input type="text" id="motif" name="motif" value="{{ old('motif') }}" required minlength="5" maxlength="200"
                                       class="form-control @error('motif') is-invalid @enderror">
                                @error('motif')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-3 d-grid">
                                <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Purger</button>
                            </div>
                        </div>
                    </div>
                </form>
            @endif

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Par</th>
                                    <th scope="col">Entrées antérieures au</th>
                                    <th scope="col" class="text-end">Supprimées</th>
                                    <th scope="col">Motif</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($purges as $purge)
                                    <tr>
                                        <td class="text-nowrap">{{ $purge->cree_le->format('d/m/Y H:i') }}</td>
                                        <td>{{ $purge->type->libelle() }}</td>
                                        <td class="small">{{ $purge->purgePar?->libelleActeur() ?? 'Système' }}</td>
                                        <td class="text-nowrap">{{ $purge->supprime_avant->format('d/m/Y H:i') }}</td>
                                        <td class="text-end">{{ $purge->nombre_entrees }}</td>
                                        <td class="small">{{ $purge->motif ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-secondary">Aucune purge enregistrée.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $purges->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>

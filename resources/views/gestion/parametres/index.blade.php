<x-layouts.app titre="Paramètres" sous-titre="Back-office · Administration">
    <h1 class="h3 mb-1">Paramètres</h1>
    <p class="text-secondary mb-3">Identité de l'application{{ $peutSauvegarder ? ' et sauvegardes de la base de données' : '' }}.</p>

    <ul class="nav nav-tabs mb-3" role="tablist">
        @if ($peutIdentite)
            <li class="nav-item" role="presentation">
                <button @class(['nav-link', 'active' => $onglet === 'identite']) id="onglet-identite" data-bs-toggle="tab" data-bs-target="#panneau-identite"
                        type="button" role="tab" aria-controls="panneau-identite" aria-selected="{{ $onglet === 'identite' ? 'true' : 'false' }}">
                    <i class="bi bi-palette me-1" aria-hidden="true"></i>Identité
                </button>
            </li>
        @endif
        @if ($peutSauvegarder)
            <li class="nav-item" role="presentation">
                <button @class(['nav-link', 'active' => $onglet === 'sauvegardes']) id="onglet-sauvegardes" data-bs-toggle="tab" data-bs-target="#panneau-sauvegardes"
                        type="button" role="tab" aria-controls="panneau-sauvegardes" aria-selected="{{ $onglet === 'sauvegardes' ? 'true' : 'false' }}">
                    <i class="bi bi-database-down me-1" aria-hidden="true"></i>Sauvegardes
                </button>
            </li>
        @endif
    </ul>

    <div class="tab-content">
        @if ($peutIdentite)
            <div @class(['tab-pane fade', 'show active' => $onglet === 'identite']) id="panneau-identite" role="tabpanel" aria-labelledby="onglet-identite" tabindex="0">
                <form method="POST" action="{{ route('gestion.parametres.identite') }}" enctype="multipart/form-data" novalidate class="card border-0 shadow-sm">
                    @csrf
                    @method('PUT')
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <div class="col-12 col-lg-7">
                                <div class="mb-3">
                                    <label for="nom_application" class="form-label fw-semibold">Nom de l'application</label>
                                    <input type="text" id="nom_application" name="nom_application" required maxlength="40"
                                           value="{{ old('nom_application', App\Services\Parametres::nomApplication()) }}"
                                           class="form-control @error('nom_application') is-invalid @enderror">
                                    @error('nom_application')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text">Affiché dans la barre latérale, la page de connexion et le titre des pages.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="nom_organisation" class="form-label fw-semibold">Nom de l'organisation</label>
                                    <input type="text" id="nom_organisation" name="nom_organisation" required maxlength="60"
                                           value="{{ old('nom_organisation', App\Services\Parametres::nomOrganisation()) }}"
                                           class="form-control @error('nom_organisation') is-invalid @enderror">
                                    @error('nom_organisation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label for="logo" class="form-label fw-semibold">Nouveau logo <span class="fw-normal text-secondary">(facultatif)</span></label>
                                    <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"
                                           class="form-control @error('logo') is-invalid @enderror">
                                    @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text">PNG, JPG ou WebP, 1 Mo maximum, carré de préférence (au moins 64 × 64 px).</div>
                                </div>
                                @if (App\Services\Parametres::logoPersonnalise())
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" value="1" id="logo_defaut" name="logo_defaut">
                                        <label class="form-check-label" for="logo_defaut">Revenir au logo d'origine</label>
                                    </div>
                                @endif
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Enregistrer</button>
                            </div>
                            <div class="col-12 col-lg-5">
                                <p class="small fw-semibold text-secondary mb-2">Aperçu actuel</p>
                                <div class="apercu-identite">
                                    <img src="{{ App\Services\Parametres::logoUrl() }}" alt="Logo actuel" width="56" height="56" class="rounded">
                                    <div>
                                        <span class="d-block small texte-eau">{{ App\Services\Parametres::nomOrganisation() }}</span>
                                        <span class="d-block fs-5 fw-semibold text-white">{{ App\Services\Parametres::nomApplication() }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        @endif

        @if ($peutSauvegarder)
            <div @class(['tab-pane fade', 'show active' => $onglet === 'sauvegardes']) id="panneau-sauvegardes" role="tabpanel" aria-labelledby="onglet-sauvegardes" tabindex="0">
                <div class="row g-4">
                    <div class="col-12 col-xl-4">
                        <section class="card border-0 shadow-sm mb-4" aria-labelledby="titre-nouvelle-sauvegarde">
                            <div class="card-body">
                                <h2 class="h6" id="titre-nouvelle-sauvegarde"><i class="bi bi-database-add me-1" aria-hidden="true"></i>Sauvegarder maintenant</h2>
                                <p class="small text-secondary">
                                    Base de données complète (structure, données, protections). Les {{ $conserver }} sauvegardes les plus récentes sont conservées.
                                    {{ $automatique ? 'Une sauvegarde automatique est faite chaque nuit à 01:30.' : 'Sauvegarde automatique désactivée.' }}
                                </p>
                                <form method="POST" action="{{ route('gestion.parametres.sauvegardes.store') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-database-down me-1" aria-hidden="true"></i>Créer une sauvegarde</button>
                                </form>
                            </div>
                        </section>

                        <form method="POST" action="{{ route('gestion.parametres.sauvegardes.dossier') }}" novalidate class="card border-0 shadow-sm">
                            @csrf
                            @method('PUT')
                            <div class="card-body">
                                <h2 class="h6"><i class="bi bi-folder2 me-1" aria-hidden="true"></i>Dossier des sauvegardes</h2>
                                <label for="dossier" class="form-label small">Chemin absolu sur le serveur</label>
                                <input type="text" id="dossier" name="dossier" value="{{ old('dossier', $dossier) }}" required maxlength="255"
                                       class="form-control form-control-sm font-monospace @error('dossier') is-invalid @enderror">
                                @error('dossier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">
                                    Par défaut : <span class="font-monospace text-break">{{ $dossierParDefaut }}</span>.
                                    Hors du projet, ou dans <span class="font-monospace">storage/app/</span> : le reste du projet est remplacé à chaque déploiement.
                                    Jamais dans le dossier public. Il est créé s'il n'existe pas.
                                </div>
                                <button type="submit" class="btn btn-outline-primary btn-sm mt-2">Changer de dossier</button>
                            </div>
                        </form>
                    </div>

                    <div class="col-12 col-xl-8">
                        <section class="card border-0 shadow-sm" aria-labelledby="titre-sauvegardes">
                            <div class="card-body">
                                <h2 class="h6" id="titre-sauvegardes"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Sauvegardes disponibles</h2>
                                <ul class="list-group list-group-flush">
                                    @forelse ($sauvegardes as $sauvegarde)
                                        <li class="list-group-item px-0">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                                <div class="min-w-0">
                                                    <span class="d-block font-monospace small text-truncate">{{ $sauvegarde['nom'] }}</span>
                                                    <span class="small text-secondary">
                                                        {{ $sauvegarde['date']->format('d/m/Y à H:i') }} · {{ number_format($sauvegarde['taille'] / 1024, 0, ',', ' ') }} Ko
                                                        @if (str_contains($sauvegarde['nom'], 'avant-restauration'))
                                                            · <span class="badge text-bg-secondary">avant restauration</span>
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <a href="{{ route('gestion.parametres.sauvegardes.telecharger', $sauvegarde['nom']) }}" class="btn btn-sm btn-outline-secondary"
                                                       title="Télécharger" aria-label="Télécharger {{ $sauvegarde['nom'] }}"><i class="bi bi-download" aria-hidden="true"></i></a>
                                                    <form method="POST" action="{{ route('gestion.parametres.sauvegardes.supprimer', $sauvegarde['nom']) }}"
                                                          data-titre="Supprimer cette sauvegarde ?" data-confirmer="{{ $sauvegarde['nom'] }} sera définitivement supprimée."
                                                          data-bouton-confirmer="Supprimer" data-danger="1">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer" aria-label="Supprimer {{ $sauvegarde['nom'] }}">
                                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            <details class="mt-2">
                                                <summary class="small text-danger">Restaurer cette sauvegarde…</summary>
                                                <form method="POST" action="{{ route('gestion.parametres.sauvegardes.restaurer', $sauvegarde['nom']) }}" class="alert alert-danger mt-2 mb-0 small"
                                                      data-titre="Restaurer la base ?" data-confirmer="Toutes les données actuelles seront remplacées par celles du {{ $sauvegarde['date']->format('d/m/Y à H:i') }}. L'état actuel sera d'abord sauvegardé."
                                                      data-bouton-confirmer="Restaurer" data-danger="1">
                                                    @csrf
                                                    <p class="mb-2"><strong>Toutes les données actuelles seront remplacées.</strong> Une sauvegarde de l'état actuel est faite juste avant. Les utilisateurs connectés devront peut-être se reconnecter.</p>
                                                    <label for="confirmation-{{ $loop->index }}" class="form-label">Saisissez <strong>RESTAURER</strong> pour confirmer</label>
                                                    <div class="d-flex gap-2">
                                                        <input type="text" id="confirmation-{{ $loop->index }}" name="confirmation" required autocomplete="off" class="form-control form-control-sm">
                                                        <button type="submit" class="btn btn-sm btn-danger text-nowrap">Restaurer</button>
                                                    </div>
                                                </form>
                                            </details>
                                        </li>
                                    @empty
                                        <li class="list-group-item px-0 text-secondary">Aucune sauvegarde pour l'instant.</li>
                                    @endforelse
                                </ul>
                                @error('confirmation')<div class="alert alert-danger small mt-2 mb-0">{{ $message }}</div>@enderror
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>

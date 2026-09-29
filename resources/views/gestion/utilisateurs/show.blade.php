<x-layouts.app :titre="$compte->nom" sous-titre="Back-office · Paramètres">
    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('gestion.utilisateurs.index') }}">Utilisateurs</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $compte->nom }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <section class="card border-0 shadow-sm" aria-labelledby="titre-compte">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="avatar avatar--grand" aria-hidden="true">{{ $compte->initiales() }}</span>
                            <div>
                                <h1 class="h4 fw-bold mb-0" id="titre-compte">{{ $compte->nom }}</h1>
                                <span class="font-monospace small text-secondary">{{ '@'.$compte->nom_utilisateur }}</span>
                            </div>
                        </div>
                        <span class="badge {{ $etat === 'Actif' ? 'text-bg-success' : ($etat === 'Verrouillé' ? 'text-bg-danger' : 'text-bg-secondary') }}">{{ $etat }}</span>
                    </div>

                    <dl class="row small mb-3">
                        <dt class="col-5 col-sm-4">Rôle</dt><dd class="col-7 col-sm-8">{{ $compte->rolePrincipal()?->libelle() ?? '—' }}</dd>
                        <dt class="col-5 col-sm-4">Téléphone</dt>
                        <dd class="col-7 col-sm-8">{{ $compte->telephone ? App\Services\Telephone::formater($compte->telephone) : '—' }}</dd>
                        <dt class="col-5 col-sm-4">E-mail</dt><dd class="col-7 col-sm-8">{{ $compte->email ?? '—' }}</dd>
                        <dt class="col-5 col-sm-4">Dernière connexion</dt>
                        <dd class="col-7 col-sm-8">{{ $compte->derniere_connexion_le?->format('d/m/Y à H:i') ?? 'Jamais' }}</dd>
                        <dt class="col-5 col-sm-4">Créé le</dt>
                        <dd class="col-7 col-sm-8">{{ $compte->created_at->format('d/m/Y à H:i') }} par <strong>{{ $compte->creePar?->libelleActeur() ?? 'Système' }}</strong></dd>
                        @if ($compte->modifiePar)
                            <dt class="col-5 col-sm-4">Modifié le</dt>
                            <dd class="col-7 col-sm-8">{{ $compte->updated_at->format('d/m/Y à H:i') }} par <strong>{{ $compte->modifiePar->libelleActeur() }}</strong></dd>
                        @endif
                    </dl>

                    @can('gerer', $compte)
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <a href="{{ route('gestion.comptes.edit', $compte) }}" class="btn btn-outline-primary">
                                <i class="bi bi-pencil me-1" aria-hidden="true"></i>Modifier
                            </a>
                            <x-actions-compte :compte="$compte" :modifier="false" />
                        </div>
                    @else
                        <p class="small text-secondary mb-0">
                            @if ($compte->is(auth()->user()))
                                <i class="bi bi-person-circle me-1" aria-hidden="true"></i>C'est votre compte : il ne se modifie pas depuis cette page.
                                <a href="{{ route('profil') }}">Voir mon profil</a>.
                            @elseif ($compte->hasRole(App\Enums\Role::Superadmin))
                                <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Compte super administrateur : il se gère uniquement depuis la configuration du serveur.
                            @else
                                <i class="bi bi-lock me-1" aria-hidden="true"></i>Vous ne pouvez pas gérer ce compte (rang supérieur ou égal au vôtre, ou droits que vous ne détenez pas).
                            @endif
                        </p>
                    @endcan
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-5">
            <section class="card border-0 shadow-sm fond-nuit" aria-labelledby="titre-activite">
                <div class="card-body">
                    <h2 class="h6 fw-bold text-white mb-3" id="titre-activite">Activité</h2>
                    <div class="row g-3">
                        <div class="col-6">
                            <p class="small text-white-50 mb-1">Cartes activées</p>
                            <p class="h2 fw-bold texte-or mb-0">{{ $compte->cartes_activees_count }}</p>
                        </div>
                        <div class="col-6">
                            <p class="small text-white-50 mb-1">Transactions validées</p>
                            <p class="h2 fw-bold texte-or mb-0">{{ $compte->transactions_validees_count }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>

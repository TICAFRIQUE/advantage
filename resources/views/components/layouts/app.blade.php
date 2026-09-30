@props(['titre', 'sousTitre' => null])

@php
    $utilisateur = auth()->user();
    $role = $utilisateur->rolePrincipal();
    // État de la barre rendu côté serveur (cookie non chiffré, simple préférence d'affichage) : pas de clignotement.
    $barreReduite = request()->cookie('barre_reduite') === '1';
@endphp

<x-layouts.base :titre="$titre" :classe-body="'app-erp avec-menu-bas'.($barreReduite ? ' barre-reduite' : '')">
    <a href="#contenu-principal" class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-light z-3">Aller au contenu</a>

    <x-barre-laterale :reduite="$barreReduite" />

    <div class="app-erp__principal">
        <header class="barre-haut">
            <button type="button" class="btn barre-haut__icone d-lg-none" data-bs-toggle="offcanvas"
                    data-bs-target="#barre-laterale" aria-controls="barre-laterale" aria-label="Ouvrir le menu">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn barre-haut__icone d-none d-lg-inline-flex" data-basculer-barre
                    aria-controls="barre-laterale" aria-expanded="{{ $barreReduite ? 'false' : 'true' }}"
                    aria-label="Réduire ou étendre le menu">
                <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i>
            </button>

            <div class="barre-haut__titre">
                <span class="fw-bold text-truncate">{{ $titre }}</span>
                @if ($sousTitre)
                    <span class="small text-secondary text-truncate d-none d-sm-block">{{ $sousTitre }}</span>
                @endif
            </div>

            @if ($echeancesEntete ?? null)
                <x-cloche-echeances :echeances="$echeancesEntete" class="ms-auto me-1" />
            @endif

            <div @class(['dropdown', 'ms-auto' => ! ($echeancesEntete ?? null)])>
                <button type="button" class="btn barre-haut__utilisateur dropdown-toggle" data-bs-toggle="dropdown"
                        data-bs-display="static" aria-expanded="false" aria-label="Menu du compte {{ $utilisateur->nom }}">
                    <span class="avatar" aria-hidden="true">{{ $utilisateur->initiales() }}</span>
                    <span class="d-none d-md-block text-start lh-sm">
                        <span class="d-block fw-semibold text-truncate barre-haut__nom">{{ $utilisateur->nom }}</span>
                        <span class="d-block small text-secondary">{{ $role?->libelle() }}</span>
                    </span>
                </button>

                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 menu-compte">
                    <div class="menu-compte__entete">
                        <span class="avatar avatar--grand" aria-hidden="true">{{ $utilisateur->initiales() }}</span>
                        <div class="min-w-0">
                            <p class="fw-bold mb-0 text-truncate">{{ $utilisateur->nom }}</p>
                            <p class="small text-secondary mb-1 text-truncate">{{ '@'.$utilisateur->nom_utilisateur }}</p>
                            @if ($role)
                                <span class="badge rounded-pill text-bg-warning">{{ $role->libelle() }}</span>
                            @endif
                            @if ($utilisateur->partenaire)
                                <p class="small mb-0 mt-1 text-truncate"><i class="bi bi-shop me-1" aria-hidden="true"></i>{{ $utilisateur->partenaire->nom }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{ route('profil') }}">
                        <i class="bi bi-person-circle me-2" aria-hidden="true"></i>Mon profil
                    </a>
                    <a class="dropdown-item" href="{{ route('accueil-espace') }}">
                        <i class="bi bi-grid me-2" aria-hidden="true"></i>Mon espace
                    </a>
                    @if ($utilisateur->derniere_connexion_le)
                        <span class="dropdown-item-text small text-secondary">
                            <i class="bi bi-clock-history me-2" aria-hidden="true"></i>Connecté le {{ $utilisateur->derniere_connexion_le->format('d/m/Y à H:i') }}
                        </span>
                    @endif
                    <div x-data="installationApp" x-show="disponible" style="display: none">
                        <button type="button" class="dropdown-item" x-on:click="installer()">
                            <i class="bi bi-phone me-2" aria-hidden="true"></i>Installer l'application
                        </button>
                    </div>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger fw-semibold">
                            <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Déconnexion
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main id="contenu-principal" class="app-erp__contenu" tabindex="-1">
            <x-pin-genere />

            @if (session('succes'))
                <div class="alert alert-success d-flex gap-2 align-items-start" role="status">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>{{ session('succes') }}</span>
                </div>
            @endif

            @if (session('erreur'))
                <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span>{{ session('erreur') }}</span>
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="app-erp__pied">
            &copy; {{ date('Y') }} {{ App\Services\Parametres::nomOrganisation() }} — {{ App\Services\Parametres::nomApplication() }}
        </footer>
    </div>

    <x-menu-bas />
</x-layouts.base>

<x-layouts.app titre="Utilisateurs" sous-titre="Back-office · Paramètres">
    @push('scripts')
        @vite('resources/js/tableaux.js')
    @endpush

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
        <h1 class="h3 fw-bold mb-0">Utilisateurs du back-office</h1>
        @if (App\Services\Droits\GardeDroits::rolesGestionAttribuables(auth()->user()) !== [])
            <a href="{{ route('gestion.utilisateurs.create') }}" class="btn btn-or">
                <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Nouvel utilisateur
            </a>
        @endif
    </div>
    <p class="text-secondary mb-3">Administrateurs et agents. Les utilisateurs des partenaires se gèrent depuis la fiche de chaque partenaire.</p>

    <form method="GET" action="{{ route('gestion.utilisateurs.index') }}" id="filtres-utilisateurs"
          class="card card-body shadow-sm border-0 mb-4" aria-label="Filtres des utilisateurs">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label for="role" class="form-label fw-semibold">Rôle</label>
                <select id="role" name="role" class="form-select">
                    <option value="">Tous</option>
                    @foreach (App\Enums\Role::roleGestion() as $role)
                        <option value="{{ $role->value }}" @selected(($filtres['role'] ?? null) === $role->value)>{{ $role->libelle() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label for="etat" class="form-label fw-semibold">État</label>
                <select id="etat" name="etat" class="form-select">
                    <option value="">Tous</option>
                    @foreach (App\Http\Requests\Gestion\FiltrerUtilisateursRequest::ETATS as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected(($filtres['etat'] ?? null) === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3 d-grid">
                <button type="submit" class="btn btn-primary">Appliquer</button>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-striped align-middle w-100" id="tableau-utilisateurs"
                   data-source="{{ route('gestion.utilisateurs.donnees') }}" data-filtres="#filtres-utilisateurs" data-ordre="asc">
                <thead>
                    <tr>
                        <th scope="col" data-colonne="nom" data-lien="lien">Nom</th>
                        <th scope="col" data-colonne="identifiant" data-triable="false">Identifiant</th>
                        <th scope="col" data-colonne="role" data-triable="false">Rôle</th>
                        <th scope="col" data-colonne="etat" data-triable="false">État</th>
                        <th scope="col" data-colonne="derniere_connexion_le">Dernière connexion</th>
                        <th scope="col" data-colonne="cree_par" data-triable="false">Créé par</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</x-layouts.app>

<x-layouts.app titre="Nouvel utilisateur" sous-titre="Back-office · Paramètres">
    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('gestion.utilisateurs.index') }}">Utilisateurs</a></li>
            <li class="breadcrumb-item active" aria-current="page">Nouveau</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8 col-xl-7">
            <h1 class="h3 fw-bold mb-3">Nouvel utilisateur du back-office</h1>

            <form method="POST" action="{{ route('gestion.utilisateurs.store') }}" novalidate class="card border-0 shadow-sm">
                @csrf
                <div class="card-body p-4">
                    @include('gestion.comptes._champs', ['compte' => null])

                    <p class="small text-secondary mt-3 mb-4">
                        Un PIN à 5 chiffres sera généré et affiché une seule fois : transmettez-le à l'utilisateur.
                        Les utilisateurs d'un partenaire se créent depuis la fiche du partenaire.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Créer l'utilisateur</button>
                        <a href="{{ route('gestion.utilisateurs.index') }}" class="btn btn-link">Annuler</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>

<x-layouts.app :titre="'Modifier '.$compte->nom" sous-titre="Back-office · Comptes">
    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb mb-0">
            @if ($compte->partenaire_id)
                <li class="breadcrumb-item"><a href="{{ route('gestion.partenaires.index') }}">Partenaires</a></li>
            @else
                <li class="breadcrumb-item"><a href="{{ route('gestion.utilisateurs.index') }}">Utilisateurs</a></li>
            @endif
            <li class="breadcrumb-item"><a href="{{ $retour }}">{{ $compte->partenaire?->nom ?? $compte->nom }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Modifier</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8 col-xl-7">
            <h1 class="h3 fw-bold mb-3">Modifier le compte <span class="font-monospace">{{ '@'.$compte->nom_utilisateur }}</span></h1>

            <form method="POST" action="{{ route('gestion.comptes.update', $compte) }}" novalidate class="card border-0 shadow-sm">
                @csrf
                @method('PUT')
                <div class="card-body p-4">
                    @include('gestion.comptes._champs')

                    <p class="small text-secondary mt-3 mb-4">
                        Si le nom d'utilisateur change, l'utilisateur se connecte avec le nouveau dès maintenant ; son PIN ne change pas.
                        L'historique reste attaché au compte.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                        <a href="{{ $retour }}" class="btn btn-link">Annuler</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>

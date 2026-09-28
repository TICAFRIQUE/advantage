<x-layouts.app titre="Espace partenaire" sous-titre="Espace Partenaire">
    <h1 class="h3 fw-bold mb-1">Espace partenaire</h1>
    <p class="text-secondary mb-1">Bienvenue, {{ auth()->user()->nom }}.</p>
    @if (auth()->user()->partenaire)
        <p class="fw-semibold">{{ auth()->user()->partenaire->nom }}</p>
    @endif

    <div class="alert alert-info" role="status">
        La vérification de carte et la validation par code arrivent en phase 4.
    </div>
</x-layouts.app>

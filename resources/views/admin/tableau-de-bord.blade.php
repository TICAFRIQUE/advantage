<x-layouts.app titre="Administration" sous-titre="Espace Administration">
    <h1 class="h3 fw-bold mb-1">Tableau de bord</h1>
    <p class="text-secondary">Bienvenue, {{ auth()->user()->nom }}.</p>

    <div class="alert alert-info" role="status">
        Les modules d'administration (utilisateurs, cartes, partenaires, taux, journal d'audit) arrivent en phase 5.
    </div>
</x-layouts.app>

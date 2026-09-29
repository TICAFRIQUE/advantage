<x-layouts.app titre="Mise en production" sous-titre="Back-office · Système">
    <div class="card border-0 shadow-sm">
        <div class="card-body doc-commandes">
            <p class="small text-secondary mb-3">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>Source : <code>docs/production.md</code>, versionné avec le code.
            </p>
            {{-- Contenu Markdown de confiance (fichier du dépôt), HTML brut échappé à la conversion. --}}
            {!! $contenu !!}
        </div>
    </div>
</x-layouts.app>

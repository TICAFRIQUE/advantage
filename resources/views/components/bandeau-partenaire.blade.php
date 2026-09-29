{{--
    Rappel permanent du partenaire courant et de son taux.
    « changeable » (back-office) : bouton qui déplie le sélecteur de partenaire
    de la page (variable Alpine « changer » du parent).
--}}
@props(['partenaire', 'changeable' => false])

<div {{ $attributes->class(['bandeau-partenaire d-flex flex-wrap align-items-center gap-3 mb-3']) }}>
    <span class="bandeau-partenaire__icone" aria-hidden="true"><i class="bi bi-shop"></i></span>
    <div class="min-w-0 flex-grow-1">
        <p class="small text-secondary mb-0">{{ $changeable ? 'Transaction pour le compte de' : 'Partenaire' }}</p>
        <p class="fw-bold mb-0 text-truncate">{{ $partenaire->nom }}</p>
    </div>
    <span class="badge rounded-pill text-bg-warning fs-6">{{ $partenaire->tauxFormate() }}</span>
    @if ($changeable)
        <button type="button" class="btn btn-sm btn-outline-primary" x-on:click="changer = !changer"
                x-bind:aria-expanded="changer ? 'true' : 'false'" aria-controls="choix-partenaire">
            <i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>Changer de partenaire
        </button>
    @endif
</div>

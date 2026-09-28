@props(['partenaire'])

@php($peutChoisir = App\Services\PartenaireCourant::peutChoisir(auth()->user()))

<div {{ $attributes->class(['bandeau-partenaire d-flex align-items-center gap-3 mb-3']) }}>
    <span class="bandeau-partenaire__icone" aria-hidden="true"><i class="bi bi-shop"></i></span>
    <div class="min-w-0 flex-grow-1">
        <p class="small text-secondary mb-0">{{ $peutChoisir ? 'Vous agissez pour le compte de' : 'Partenaire' }}</p>
        <p class="fw-bold mb-0 text-truncate">{{ $partenaire->nom }}</p>
    </div>
    <span class="badge rounded-pill text-bg-warning fs-6">{{ rtrim(rtrim((string) $partenaire->taux_reduction, '0'), '.') }} %</span>
    @if ($peutChoisir)
        <a href="{{ route('partenaire.tableau-de-bord') }}" class="btn btn-sm btn-outline-primary" aria-label="Changer de partenaire">Changer</a>
    @endif
</div>

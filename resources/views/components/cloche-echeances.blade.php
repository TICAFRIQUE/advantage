{{--
    Cloche de l'en-tête : cartes actives arrivant à échéance (moins de 3 mois).
    Pastille rouge s'il y en a dans le mois, orange sinon ; menu avec les
    paliers (liens vers la liste filtrée) et les prochaines échéances.
    Données : App\Services\EcheancesCartes (en cache, voir AppServiceProvider).
--}}
@props(['echeances'])

@php
    $total = $echeances['paliers'][3];
    $urgent = $echeances['paliers'][1] > 0;
@endphp

<div {{ $attributes->merge(['class' => 'dropdown cloche-echeances']) }}>
    <button type="button" class="btn barre-haut__icone position-relative" data-bs-toggle="dropdown" data-bs-display="static"
            aria-expanded="false"
            aria-label="{{ $total > 0 ? $total.' carte(s) arrivent à échéance' : 'Aucune carte n\'arrive à échéance' }}">
        <i class="bi {{ $total > 0 ? 'bi-bell-fill' : 'bi-bell' }}" aria-hidden="true"></i>
        @if ($total > 0)
            <span @class(['cloche-echeances__pastille badge rounded-pill', 'text-bg-danger' => $urgent, 'text-bg-warning' => ! $urgent])
                  aria-hidden="true">{{ $total > 99 ? '99+' : $total }}</span>
        @endif
    </button>

    <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 cloche-echeances__menu">
        <div class="px-3 pt-2 pb-1">
            <p class="fw-bold mb-0"><i class="bi bi-hourglass-split texte-or-fonce me-1" aria-hidden="true"></i>Cartes bientôt expirées</p>
            <p class="small text-secondary mb-0">Titulaires prévenus par SMS à 3, 2 et 1 mois.</p>
        </div>

        <div class="d-flex gap-2 px-3 py-2">
            @foreach ([1 => ['1 mois', 'danger'], 2 => ['2 mois', 'warning'], 3 => ['3 mois', 'info']] as $mois => [$libelle, $niveau])
                <a href="{{ route('gestion.cartes.index', ['expire_dans' => $mois]) }}"
                   class="tuile-echeance tuile-echeance--{{ $niveau }} tuile-echeance--mini flex-fill text-decoration-none text-center"
                   aria-label="{{ $echeances['paliers'][$mois] }} carte(s) expirant sous {{ $mois }} mois">
                    <span class="tuile-echeance__valeur">{{ $echeances['paliers'][$mois] }}</span>
                    <span class="tuile-echeance__libelle">{{ $libelle }}</span>
                </a>
            @endforeach
        </div>

        @if ($echeances['prochaines'] !== [])
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Prochaines échéances</h6>
            @foreach ($echeances['prochaines'] as $carte)
                <a class="dropdown-item d-flex align-items-center justify-content-between gap-2" href="{{ route('gestion.cartes.show', $carte['id']) }}">
                    <span class="min-w-0">
                        <span class="d-block text-truncate fw-semibold">{{ $carte['titulaire'] }}</span>
                        <span class="small text-secondary font-monospace">{{ $carte['numero'] }}</span>
                    </span>
                    <span class="badge rounded-pill text-bg-{{ $carte['niveau'] }} flex-shrink-0">{{ $carte['libelle'] }}</span>
                </a>
            @endforeach
        @else
            <p class="px-3 small text-secondary mb-2"><i class="bi bi-check-circle text-success me-1" aria-hidden="true"></i>Aucune carte n'expire dans les 3 prochains mois.</p>
        @endif

        <div class="dropdown-divider"></div>
        <a class="dropdown-item text-center small fw-semibold" href="{{ route('gestion.cartes.index', ['expire_dans' => 3]) }}">
            Voir toutes les échéances <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
        </a>
    </div>
</div>

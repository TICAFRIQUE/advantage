<x-layouts.app titre="Tableau de bord" sous-titre="Back-office">
    <h1 class="h3 fw-bold mb-1">Bonjour, {{ auth()->user()->nom }}</h1>
    <p class="text-secondary mb-4">{{ now()->translatedFormat('l d F Y') }}</p>

    <div class="d-flex flex-wrap gap-2 mb-4">
        @can('activer-carte')
            <a href="{{ route('gestion.cartes.create') }}" class="btn btn-or btn-lg">
                <i class="bi bi-credit-card-2-front me-1" aria-hidden="true"></i>Activer une carte
            </a>
        @endcan
        @can('effectuer-transaction-partenaire')
            <a href="{{ route('gestion.transaction.nouvelle') }}" class="btn btn-primary btn-lg">
                <i class="bi bi-upc-scan me-1" aria-hidden="true"></i>Transaction
            </a>
        @endcan
    </div>

    @php
        $blocs = array_filter([
            $cartes ? ['Cartes', [
                ['Activations aujourd\'hui', $cartes['activations_du_jour']],
                ['Mes activations aujourd\'hui', $cartes['mes_activations_du_jour']],
                ['Cartes actives', $cartes['cartes_actives']],
            ]] : null,
            $transactions ? ['Transactions', [
                ['Passages aujourd\'hui', $transactions['passages_du_jour']],
                ['Passages ce mois-ci', $transactions['passages_du_mois']],
                ['Partenaires actifs', $transactions['partenaires_actifs']],
            ]] : null,
        ]);
    @endphp

    @foreach ($blocs as [$titre, $indicateurs])
        <h2 class="h6 fw-bold text-uppercase text-secondary mb-2">{{ $titre }}</h2>
        <div class="row g-3 mb-4">
            @foreach ($indicateurs as [$libelle, $valeur])
                <div class="col-12 col-sm-4">
                    <div class="card border-0 shadow-sm fond-nuit h-100">
                        <div class="card-body">
                            <p class="small text-white-50 mb-1">{{ $libelle }}</p>
                            <p class="display-6 fw-bold texte-or mb-0">{{ $valeur }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    @if ($expirations)
        <section class="card border-0 shadow-sm mb-4" aria-labelledby="titre-expirations">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h2 class="h6 fw-bold text-uppercase text-secondary mb-0" id="titre-expirations">
                        <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Cartes bientôt expirées
                    </h2>
                    <span class="small text-secondary">Les titulaires sont prévenus par SMS à 3, 2 et 1 mois.</span>
                </div>
                <div class="row g-3 mb-3">
                    @foreach ([1 => 'Dans le mois', 2 => 'Sous 2 mois', 3 => 'Sous 3 mois'] as $mois => $libelle)
                        <div class="col-4">
                            <p class="small text-secondary mb-1">{{ $libelle }}</p>
                            <p class="h3 fw-bold mb-0 {{ $mois === 1 && $expirations['paliers'][1] > 0 ? 'text-danger' : '' }}">{{ $expirations['paliers'][$mois] }}</p>
                        </div>
                    @endforeach
                </div>
                @if ($expirations['prochaines']->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <caption class="visually-hidden">Prochaines échéances</caption>
                            <thead>
                                <tr><th scope="col">Carte</th><th scope="col">Titulaire</th><th scope="col">Expire le</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($expirations['prochaines'] as $carte)
                                    <tr>
                                        <td class="font-monospace"><a href="{{ route('gestion.cartes.show', $carte) }}">{{ $carte->numeroFormate() }}</a></td>
                                        <td>{{ $carte->titulaire->nomComplet() }}</td>
                                        <td class="text-nowrap">{{ $carte->expire_le->format('d/m/Y') }} <span class="small text-secondary">({{ $carte->expire_le->diffForHumans() }})</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if ($dernieres->isNotEmpty())
        <h2 class="h5 fw-bold mb-3">Mes dernières activations</h2>
        <div class="row g-4">
            @foreach ($dernieres as $carte)
                <div class="col-12 col-md-6 col-xl-4">
                    <a href="{{ route('gestion.cartes.show', $carte) }}" class="text-decoration-none text-reset d-block"
                       aria-label="Carte {{ $carte->numeroFormate() }} de {{ $carte->titulaire->nomComplet() }}">
                        <x-carte-visuelle :carte="$carte" class="mb-2" />
                        <span class="fw-semibold">{{ $carte->titulaire->nomComplet() }}</span>
                        <span class="d-block small text-secondary">{{ $carte->active_le->format('d/m/Y à H:i') }}</span>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>

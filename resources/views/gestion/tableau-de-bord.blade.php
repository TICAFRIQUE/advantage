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
            <a href="{{ route('gestion.transaction.verifier') }}" class="btn btn-primary btn-lg">
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

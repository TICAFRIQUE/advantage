<x-layouts.app titre="Espace agent" sous-titre="Espace Agent">

    <h1 class="h3 fw-bold mb-1">Bonjour, {{ auth()->user()->nom }}</h1>
    <p class="text-secondary mb-4">{{ now()->translatedFormat('l d F Y') }}</p>

    <div class="row g-3 mb-4">
        @foreach ([
            ['Mes activations aujourd\'hui', $indicateurs['mes_activations_du_jour']],
            ['Mes activations (total)', $indicateurs['mes_activations']],
            ['Activations aujourd\'hui (tous agents)', $indicateurs['activations_du_jour']],
        ] as [$libelle, $valeur])
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

    <div class="d-flex flex-wrap gap-2 mb-4">
        @can('create', App\Models\Carte::class)
            <a href="{{ route('agent.cartes.create') }}" class="btn btn-or btn-lg">Activer une carte</a>
        @endcan
        @can('viewAny', App\Models\Carte::class)
            <a href="{{ route('agent.cartes.index') }}" class="btn btn-outline-primary btn-lg">Rechercher une carte</a>
        @endcan
    </div>

    @if ($dernieres->isNotEmpty())
        <h2 class="h5 fw-bold mb-3">Mes dernières activations</h2>
        <div class="row g-4">
            @foreach ($dernieres as $carte)
                <div class="col-12 col-md-6 col-xl-4">
                    <a href="{{ route('agent.cartes.show', $carte) }}" class="text-decoration-none text-reset d-block"
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

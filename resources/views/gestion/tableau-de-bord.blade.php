<x-layouts.app titre="Tableau de bord" sous-titre="Back-office">
    <h1 class="h3 fw-bold mb-1">Bonjour, {{ auth()->user()->nom }}</h1>
    <p class="text-secondary mb-4">{{ now()->translatedFormat('l d F Y') }}</p>

    {{-- Sur mobile, ces actions sont dans le menu du bas. --}}
    <div class="d-none d-lg-flex flex-wrap gap-2 mb-4">
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
                <div class="col-6 col-sm-4 indicateur-tableau">
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
        <section class="card border-0 shadow-sm mb-4 bloc-expirations" aria-labelledby="titre-expirations">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1" id="titre-expirations">
                            <i class="bi bi-hourglass-split texte-or-fonce me-1" aria-hidden="true"></i>Cartes bientôt expirées
                        </h2>
                        <p class="small text-secondary mb-0">
                            <i class="bi bi-chat-dots me-1" aria-hidden="true"></i>Les titulaires sont prévenus par SMS à 3, 2 et 1 mois de l'échéance.
                        </p>
                    </div>
                    <a href="{{ route('gestion.cartes.index', ['expire_dans' => 3]) }}" class="btn btn-sm btn-outline-primary">
                        Voir toutes <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                    </a>
                </div>

                <div class="row g-3 mb-4">
                    @foreach ([
                        1 => ['Dans le mois', 'danger', 'bi-exclamation-octagon'],
                        2 => ['Sous 2 mois', 'warning', 'bi-exclamation-triangle'],
                        3 => ['Sous 3 mois', 'info', 'bi-calendar-event'],
                    ] as $mois => [$libelle, $niveau, $icone])
                        <div class="col-12 col-sm-4">
                            <a href="{{ route('gestion.cartes.index', ['expire_dans' => $mois]) }}"
                               class="tuile-echeance tuile-echeance--{{ $niveau }} d-block text-decoration-none h-100"
                               aria-label="{{ $expirations['paliers'][$mois] }} carte(s) expirant {{ mb_strtolower($libelle) }}">
                                <span class="tuile-echeance__icone" aria-hidden="true"><i class="bi {{ $icone }}"></i></span>
                                <span class="tuile-echeance__valeur">{{ $expirations['paliers'][$mois] }}</span>
                                <span class="tuile-echeance__libelle">{{ $libelle }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>

                @if ($expirations['prochaines'] !== [])
                    <h3 class="h6 fw-bold text-uppercase text-secondary small mb-2">Prochaines échéances</h3>
                    <ul class="list-group list-group-flush">
                        @foreach ($expirations['prochaines'] as $carte)
                            <li class="list-group-item px-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <span class="avatar" aria-hidden="true">{{ collect(explode(' ', $carte['titulaire']))->filter()->take(2)->map(fn ($mot) => mb_strtoupper(mb_substr($mot, 0, 1)))->implode('') }}</span>
                                    <div class="min-w-0">
                                        <a href="{{ route('gestion.cartes.show', $carte['id']) }}" class="fw-semibold text-decoration-none d-block text-truncate">
                                            {{ $carte['titulaire'] }}
                                        </a>
                                        <span class="small text-secondary font-monospace">{{ $carte['numero'] }}</span>
                                        <span class="small text-secondary"> · le {{ $carte['expire_le'] }}</span>
                                    </div>
                                </div>
                                <span class="badge rounded-pill text-bg-{{ $carte['niveau'] }}">{{ $carte['libelle'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-secondary mb-0"><i class="bi bi-check-circle text-success me-1" aria-hidden="true"></i>Aucune carte n'expire dans les 3 prochains mois.</p>
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

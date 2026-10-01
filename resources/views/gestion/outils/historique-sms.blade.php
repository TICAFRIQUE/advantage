<x-layouts.app titre="Historique des SMS" sous-titre="Back-office · Système">
    @push('scripts')
        @vite('resources/js/tableaux.js')
    @endpush

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
        <h1 class="h3 fw-bold mb-0">Historique des SMS</h1>
        @can(App\Enums\Permission::TesterSms->value)
            <a href="{{ route('gestion.sms-test.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-send-check me-1" aria-hidden="true"></i>Test d'envoi
            </a>
        @endcan
    </div>
    <p class="text-secondary mb-3">
        Tous les SMS émis par la plateforme (codes de validation, alertes d'expiration, tests) avec leur statut, conservés {{ $retention }} jours.
        Pilote actuel : <strong>{{ $pilote }}</strong>. Le texte des messages n'est jamais affiché.
    </p>

    @if ($indicateurs['bloques'] > 0)
        <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
            <span>
                <strong>{{ $indicateurs['bloques'] }} SMS en attente depuis plus de {{ App\Http\Controllers\Gestion\HistoriqueSmsController::ATTENTE_ANORMALE_MINUTES }} minutes</strong> :
                le worker de la file d'attente ne tourne pas. Vérifiez la tâche cron <code>queue:work</code> (guide de mise en production, § 3).
            </span>
        </div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([
            ['En attente', $indicateurs['en_attente'], 'à envoyer'],
            ['Envoyés', $indicateurs['envoyes'], 'sur 24 heures'],
            ['Échecs', $indicateurs['echecs'], 'sur 24 heures'],
            ["File d'attente", $file === null ? '—' : $file['en_file'], $file === null ? 'envoi immédiat (sans file)' : $file['en_echec'].' tâche(s) en échec'],
        ] as [$libelle, $valeur, $precision])
            <div class="col-6 col-lg-3 indicateur-tableau">
                <div class="card border-0 shadow-sm fond-nuit h-100">
                    <div class="card-body">
                        <p class="small text-white-50 mb-1">{{ $libelle }}</p>
                        <p class="display-6 fw-bold texte-or mb-0">{{ $valeur }}</p>
                        <p class="small text-white-50 mb-0">{{ $precision }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('gestion.sms-historique.index') }}" id="filtres-sms"
          class="card card-body shadow-sm border-0 mb-4" aria-label="Filtres de l'historique des SMS">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label for="du" class="form-label fw-semibold">Du</label>
                <input type="date" id="du" name="du" value="{{ $filtres['du'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label for="au" class="form-label fw-semibold">Au</label>
                <input type="date" id="au" name="au" value="{{ $filtres['au'] ?? '' }}" class="form-control @error('au') is-invalid @enderror">
                @error('au')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="type" class="form-label fw-semibold">Type</label>
                <select id="type" name="type" class="form-select">
                    <option value="">Tous</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($filtres['type'] ?? null) === $type->value)>{{ $type->libelle() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="statut" class="form-label fw-semibold">Statut</label>
                <select id="statut" name="statut" class="form-select">
                    <option value="">Tous</option>
                    @foreach ($statuts as $statut)
                        <option value="{{ $statut->value }}" @selected(($filtres['statut'] ?? null) === $statut->value)>{{ $statut->libelle() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <x-boutons-filtre :reinitialiser="route('gestion.sms-historique.index')" />
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-striped align-middle w-100" id="tableau-sms"
                   data-source="{{ route('gestion.sms-historique.donnees') }}" data-filtres="#filtres-sms">
                <thead>
                    <tr>
                        <th scope="col" data-colonne="created_at">Date</th>
                        <th scope="col" data-colonne="telephone" data-triable="false">Numéro</th>
                        <th scope="col" data-colonne="type" data-triable="false">Type</th>
                        <th scope="col" data-colonne="fournisseur" data-triable="false">Pilote</th>
                        <th scope="col" data-colonne="statut" data-triable="false">Statut</th>
                        <th scope="col" data-colonne="tentatives" data-triable="false">Essais</th>
                        <th scope="col" data-colonne="envoye_le" data-triable="false">Envoyé le</th>
                    </tr>
                </thead>
            </table>
            <p class="small text-secondary mt-2 mb-0">
                Recherche : saisissez un numéro (ou ses derniers chiffres). « En attente » qui dure : le worker de la file d'attente ne tourne pas.
            </p>
        </div>
    </div>
</x-layouts.app>

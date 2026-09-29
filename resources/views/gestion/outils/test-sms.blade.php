<x-layouts.app titre="Test d'envoi SMS" sous-titre="Back-office · Système">
    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <h1 class="h3 fw-bold mb-1">Test d'envoi SMS</h1>
            <p class="text-secondary">
                Envoie un vrai SMS par la chaîne complète (file d'attente puis fournisseur) pour vérifier la configuration.
            </p>

            @if ($pilote === 'simulation')
                <div class="alert alert-info" role="status">
                    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                    Pilote de <strong>simulation</strong> : aucun SMS réel ne partira. Passez <code>SMS_DRIVER=ticafrique</code> pour un envoi réel.
                </div>
            @else
                <div class="alert alert-warning" role="status">
                    <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
                    Pilote <strong>{{ $pilote }}</strong>, expéditeur « {{ $expediteur }} » : <strong>chaque test consomme une unité</strong>.
                    Limite : 5 envois par heure.
                </div>
            @endif

            <form method="POST" action="{{ route('gestion.sms-test.store') }}" novalidate class="card border-0 shadow-sm"
                  data-titre="Envoyer un SMS de test ?"
                  data-confirmer="{{ $pilote === 'simulation' ? 'Aucun SMS réel ne partira (simulation).' : 'Un vrai SMS va partir : une unité sera consommée.' }}"
                  data-bouton-confirmer="Envoyer">
                @csrf
                <div class="card-body p-4">
                    <label for="telephone" class="form-label fw-semibold">Numéro destinataire</label>
                    <div class="input-group has-validation">
                        <label for="pays_telephone" class="visually-hidden">Pays du téléphone</label>
                        <select id="pays_telephone" name="pays_telephone" class="form-select flex-grow-0 selecteur-pays">
                            @foreach ($pays as $code => $config)
                                <option value="{{ $code }}" @selected(old('pays_telephone', App\Services\Telephone::paysParDefaut()) === $code)>+{{ $config['indicatif'] }}</option>
                            @endforeach
                        </select>
                        <input type="tel" id="telephone" name="telephone" value="{{ old('telephone') }}" inputmode="tel" required maxlength="25"
                               autocomplete="off" class="form-control @error('telephone') is-invalid @enderror">
                        @error('telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <p class="small text-secondary mt-2 mb-3">Texte envoyé : « {{ App\Http\Controllers\Gestion\TestSmsController::texte() }} »</p>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send me-1" aria-hidden="true"></i>Envoyer le SMS de test
                    </button>
                </div>
            </form>
        </div>

        <div class="col-12 col-lg-7">
            <section class="card border-0 shadow-sm" aria-labelledby="titre-essais">
                <div class="card-body">
                    <h2 class="h6 fw-bold" id="titre-essais">Derniers tests</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr><th scope="col">Date</th><th scope="col">Numéro</th><th scope="col">Pilote</th><th scope="col">Statut</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($essais as $essai)
                                    <tr>
                                        <td class="text-nowrap">{{ $essai->created_at->format('d/m/Y H:i:s') }}</td>
                                        <td class="font-monospace small">{{ App\Services\Telephone::masquer($essai->telephone) }}</td>
                                        <td>{{ $essai->fournisseur }}</td>
                                        <td>
                                            <span @class(['badge', 'text-bg-success' => $essai->statut === App\Enums\StatutLivraison::Envoyee,
                                                'text-bg-danger' => $essai->statut === App\Enums\StatutLivraison::Echec,
                                                'text-bg-secondary' => $essai->statut === App\Enums\StatutLivraison::EnAttente])>
                                                {{ $essai->statut->libelle() }}
                                            </span>
                                            @if ($essai->erreur)
                                                <span class="d-block small text-danger">{{ $essai->erreur }}</span>
                                            @elseif ($essai->reference_fournisseur)
                                                <span class="d-block small text-secondary font-monospace">{{ $essai->reference_fournisseur }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-secondary">Aucun test pour l'instant.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-secondary mt-2 mb-0">« En attente » qui dure : le worker de la file d'attente ne tourne pas (voir le cron du guide de mise en production).</p>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>

<x-layouts.app titre="SMS simulés" sous-titre="Outils de test">
    @push('styles')
        {{-- Rafraîchissement automatique : les SMS partent via la file d'attente. --}}
        <meta http-equiv="refresh" content="15">
    @endpush

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h3 fw-bold mb-0">SMS simulés</h1>
        <a href="{{ route('admin.sms-simules.index') }}" class="btn btn-outline-primary">
            <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Rafraîchir
        </a>
    </div>

    <div class="alert alert-warning d-flex gap-2" role="note">
        <i class="bi bi-cone-striped" aria-hidden="true"></i>
        <div>
            <strong>Mode simulation</strong> : aucun SMS n'est réellement envoyé. Les messages (codes de validation compris)
            arrivent ici en attendant le branchement de l'API du fournisseur. Cette page n'existe pas en production.
            Les messages partent via la file d'attente : lancez <code>php artisan queue:work</code> (ou <code>composer run dev</code>).
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Destinataire</th>
                                <th scope="col">Type</th>
                                <th scope="col">Message</th>
                                <th scope="col">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($messages as $message)
                                <tr>
                                    <td class="small text-nowrap">{{ $message->created_at->format('d/m H:i:s') }}</td>
                                    <td class="text-nowrap">{{ $message->telephoneFormate() }}</td>
                                    <td class="small">{{ $message->type->libelle() }}</td>
                                    <td class="font-monospace small">{{ $message->contenu }}</td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'text-bg-success' => $message->statut === App\Enums\StatutLivraison::Envoyee,
                                            'text-bg-danger' => $message->statut === App\Enums\StatutLivraison::Echec,
                                            'text-bg-secondary' => $message->statut === App\Enums\StatutLivraison::EnAttente,
                                        ])>{{ $message->statut->libelle() }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-secondary py-4">Aucun SMS pour le moment.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <form method="POST" action="{{ route('admin.sms-simules.store') }}" class="card border-0 shadow-sm">
                @csrf
                <div class="card-body">
                    <h2 class="h6 fw-bold">Envoyer un SMS de test</h2>

                    <div class="mb-3">
                        <label for="telephone" class="form-label">Téléphone</label>
                        <div class="input-group">
                            <label for="pays_telephone" class="visually-hidden">Pays</label>
                            <select id="pays_telephone" name="pays_telephone" class="form-select flex-grow-0 selecteur-pays">
                                @foreach (App\Services\Telephone::tousLesPays() as $code => $config)
                                    <option value="{{ $code }}" @selected(old('pays_telephone', App\Services\Telephone::paysParDefaut()) === $code)>+{{ $config['indicatif'] }}</option>
                                @endforeach
                            </select>
                            <input type="tel" id="telephone" name="telephone" value="{{ old('telephone') }}" inputmode="tel"
                                   class="form-control @error('telephone') is-invalid @enderror" required maxlength="25">
                            @error('telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea id="message" name="message" rows="3" maxlength="160" required
                                  class="form-control @error('message') is-invalid @enderror">{{ old('message', 'Test ADVANTAGE') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Envoyer</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>

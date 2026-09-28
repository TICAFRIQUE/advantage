<x-layouts.app titre="Transaction" :sous-titre="$peutChoisir ? 'Back-office · Partenaires' : 'Espace partenaire'">
    <div class="parcours-caisse mx-auto">
        @if ($peutChoisir)
            {{-- Back-office : choix du partenaire pour le compte duquel on agit (option A). --}}
            <form method="POST" action="{{ route('gestion.transaction.partenaire-courant.store') }}"
                  class="card border-0 shadow-sm mb-3" aria-labelledby="titre-choix">
                @csrf
                <div class="card-body">
                    <h1 class="h6 fw-bold" id="titre-choix">
                        <i class="bi bi-shop me-1" aria-hidden="true"></i>Transaction pour le compte de
                    </h1>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <label for="partenaire_id" class="visually-hidden">Partenaire</label>
                        <select id="partenaire_id" name="partenaire_id" required
                                class="form-select form-select-lg @error('partenaire_id') is-invalid @enderror">
                            <option value="">— Choisir un partenaire —</option>
                            @foreach ($partenairesActifs as $choix)
                                <option value="{{ $choix->id }}" @selected($partenaire?->id === $choix->id)>
                                    {{ $choix->nom }}{{ $choix->localisation ? ' — '.$choix->localisation : '' }} ({{ rtrim(rtrim((string) $choix->taux_reduction, '0'), '.') }} %)
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary btn-lg text-nowrap">{{ $partenaire ? 'Changer' : 'Choisir' }}</button>
                    </div>
                    @error('partenaire_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <p class="small text-secondary mb-0 mt-2">La remise sera enregistrée pour ce partenaire, à votre nom.</p>
                </div>
            </form>
        @endif

        @if (! $partenaire)
            <div class="alert alert-info" role="status">Choisissez d'abord un partenaire pour effectuer une transaction.</div>
        @else
        <x-bandeau-partenaire :partenaire="$partenaire" />

        <ol class="etapes-caisse" aria-label="Étapes">
            <li class="actif" aria-current="step">Carte</li>
            <li>Code</li>
            <li>Remise</li>
        </ol>

        <form method="POST" action="{{ App\Services\EspaceTransaction::route('verifier.store') }}" class="card border-0 shadow-sm mb-3"
              x-data="{ numero: '' }" novalidate>
            @csrf
            <div class="card-body p-4">
                <label for="numero_carte" class="form-label fw-bold fs-5">Numéro de la carte</label>
                <input type="text" id="numero_carte" name="numero_carte" inputmode="numeric" autocomplete="off"
                       class="form-control form-control-lg champ-caisse @error('numero_carte') is-invalid @enderror"
                       x-model="numero" x-on:input="numero = numero.replace(/\D/g, '').slice(0, 7)"
                       required pattern="\d{7}" maxlength="7" placeholder="0000000" autofocus
                       aria-describedby="aide-numero erreur-numero">
                <div class="invalid-feedback" id="erreur-numero">@error('numero_carte'){{ $message }}@enderror</div>
                <div class="form-text" id="aide-numero">Les 7 chiffres imprimés sur la carte du client.</div>

                <button type="submit" class="btn btn-primary btn-lg w-100 mt-3 py-3" x-bind:disabled="numero.length !== 7">
                    Vérifier
                </button>
            </div>
        </form>

        @if ($verification)
            @if ($verification['valide'])
                <section class="card border-0 shadow-sm resultat-caisse resultat-caisse--valide" role="status" aria-live="polite">
                    <div class="card-body p-4 text-center">
                        <i class="bi bi-check-circle-fill resultat-caisse__icone" aria-hidden="true"></i>
                        <p class="fs-4 fw-bold mb-1">Carte valide</p>
                        <p class="mb-3">Carte {{ $verification['numero_formate'] }} · remise de <strong>{{ rtrim(rtrim($verification['taux'], '0'), '.') }} %</strong></p>

                        <form method="POST" action="{{ App\Services\EspaceTransaction::route('codes.store') }}">
                            @csrf
                            <input type="hidden" name="numero_carte" value="{{ $verification['numero_carte'] }}">
                            <button type="submit" class="btn btn-or btn-lg w-100 py-3">
                                <i class="bi bi-chat-dots me-2" aria-hidden="true"></i>Envoyer le code au client
                            </button>
                        </form>
                        <p class="small text-secondary mt-2 mb-0">Le client reçoit un code par SMS, à saisir à l'étape suivante.</p>
                    </div>
                </section>
            @else
                <section class="card border-0 shadow-sm resultat-caisse resultat-caisse--refus" role="alert">
                    <div class="card-body p-4 text-center">
                        <i class="bi bi-x-circle-fill resultat-caisse__icone" aria-hidden="true"></i>
                        <p class="fs-4 fw-bold mb-1">Carte non valide</p>
                        <p class="mb-0">Carte {{ $verification['numero_formate'] }} : aucune remise ne peut être accordée.
                            Invitez le client à se rapprocher d'une agence ADVANTAGE.</p>
                    </div>
                </section>
            @endif
        @endif
        @endif
    </div>
</x-layouts.app>

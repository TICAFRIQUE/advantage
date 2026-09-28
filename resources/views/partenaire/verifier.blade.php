<x-layouts.app titre="Vérifier une carte" sous-titre="Espace Partenaire">
    <div class="parcours-caisse mx-auto">
        <x-bandeau-partenaire :partenaire="$partenaire" />

        <ol class="etapes-caisse" aria-label="Étapes">
            <li class="actif" aria-current="step">Carte</li>
            <li>Code</li>
            <li>Remise</li>
        </ol>

        <form method="POST" action="{{ route('partenaire.verifier.store') }}" class="card border-0 shadow-sm mb-3"
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

                        <form method="POST" action="{{ route('partenaire.codes.store') }}">
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
    </div>
</x-layouts.app>

<x-layouts.app titre="Code de validation" sous-titre="Espace Partenaire">
    <div class="parcours-caisse mx-auto">
        <ol class="etapes-caisse" aria-label="Étapes">
            <li class="fait">Carte</li>
            <li class="actif" aria-current="step">Code</li>
            <li>Remise</li>
        </ol>

        <section class="card border-0 shadow-sm"
                 x-data="{
                     restant: {{ $utilisable ? max(0, (int) now()->diffInSeconds($demande->expire_le, false)) : 0 }},
                     renvoi: {{ $renvoiPossibleDans }},
                     code: '',
                     get minutes() { return String(Math.floor(this.restant / 60)).padStart(2, '0') + ':' + String(this.restant % 60).padStart(2, '0') },
                     init() { setInterval(() => { if (this.restant > 0) this.restant--; if (this.renvoi > 0) this.renvoi--; }, 1000) }
                 }">
            <div class="card-body p-4">
                <p class="text-secondary mb-1">Carte {{ $demande->carte->numeroFormate() }}</p>
                <h1 class="h4 fw-bold mb-3">Saisissez le code reçu par le client</h1>

                @if ($utilisable)
                    <p class="mb-3" role="timer" aria-live="off">
                        <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
                        <span x-show="restant > 0">Code valable encore <strong x-text="minutes">05:00</strong></span>
                        <span x-show="restant === 0" class="text-danger fw-semibold" style="display: none">Code expiré : demandez-en un nouveau.</span>
                    </p>

                    <form method="POST" action="{{ route('partenaire.codes.valider', $demande) }}" novalidate>
                        @csrf
                        <label for="code" class="visually-hidden">Code à 6 chiffres</label>
                        <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
                               class="form-control form-control-lg champ-code @error('code') is-invalid @enderror"
                               x-model="code" x-on:input="code = code.replace(/\D/g, '').slice(0, 6)"
                               required pattern="\d{6}" maxlength="6" placeholder="••••••" autofocus
                               aria-describedby="erreur-code">
                        <div class="invalid-feedback text-center fs-6" id="erreur-code" role="alert">@error('code'){{ $message }}@enderror</div>

                        <button type="submit" class="btn btn-or btn-lg w-100 mt-3 py-3"
                                x-bind:disabled="code.length !== 6 || restant === 0">
                            Valider la remise
                        </button>
                    </form>
                @else
                    <div class="alert alert-danger" role="alert">
                        @error('code'){{ $message }}@else Ce code n'est plus valide. Demandez un nouveau code. @enderror
                    </div>
                @endif

                <form method="POST" action="{{ route('partenaire.codes.renvoyer', $demande) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-lg w-100" x-bind:disabled="renvoi > 0">
                        <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
                        <span x-show="renvoi > 0">Renvoyer un code (<span x-text="renvoi"></span> s)</span>
                        <span x-show="renvoi === 0">Renvoyer un nouveau code</span>
                    </button>
                </form>

                <a href="{{ route('partenaire.verifier') }}" class="btn btn-link w-100 mt-2">Annuler</a>
            </div>
        </section>
    </div>
</x-layouts.app>

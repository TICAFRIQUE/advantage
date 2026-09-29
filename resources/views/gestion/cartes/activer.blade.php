<x-layouts.app titre="Activer une carte" sous-titre="Back-office">

    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-7 col-xl-6">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h1 class="h3 fw-bold mb-0">Activer une carte</h1>
                <a href="{{ route('gestion.cartes.index') }}" class="btn btn-outline-primary">
                    <i class="bi bi-list-ul me-1" aria-hidden="true"></i> Liste des cartes
                </a>
            </div>

            @error('activation')
                <div class="alert alert-danger" role="alert">{{ $message }}</div>
            @enderror

            {{-- Configuration du composant (JSON non exécuté : compatible CSP). --}}
            <script type="application/json" id="config-activation">@json($configuration)</script>

            <form method="POST" action="{{ route('gestion.cartes.store') }}" novalidate
                  class="card border-0 shadow-sm"
                  x-data="activationCarte('config-activation')"
                  x-on:submit="soumettre($event)">
                @csrf

                <div class="card-body p-4">
                    {{-- Étape 1 : titulaire --}}
                    <fieldset class="mb-4">
                        <legend class="h6 fw-bold texte-or-fonce">1. Titulaire</legend>

                        <div class="mb-3">
                            <label for="telephone" class="form-label fw-semibold">Téléphone mobile</label>
                            <div class="input-group input-group-lg has-validation">
                                <label for="pays_telephone" class="visually-hidden">Pays du téléphone</label>
                                <select id="pays_telephone" name="pays_telephone" class="form-select flex-grow-0 selecteur-pays"
                                        x-model="pays" x-on:change="rechercherTitulaire()">
                                    @foreach (App\Services\Telephone::tousLesPays() as $code => $config)
                                        <option value="{{ $code }}" @selected(old('pays_telephone', App\Services\Telephone::paysParDefaut()) === $code)>
                                            {{ collect(str_split($code))->map(fn ($lettre) => mb_chr(0x1F1E6 + ord($lettre) - 65))->implode('') }} +{{ $config['indicatif'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="tel" id="telephone" name="telephone" inputmode="tel" autocomplete="off"
                                       class="form-control @error('telephone') is-invalid @enderror"
                                       x-model="telephone" x-on:change="rechercherTitulaire()"
                                       x-bind:class="telephone !== '' && !telephoneValide ? 'is-invalid' : ''"
                                       x-bind:placeholder="exempleTelephone"
                                       required maxlength="25" placeholder="07 07 12 34 56"
                                       aria-describedby="aide-telephone erreur-telephone">
                                <div class="invalid-feedback" id="erreur-telephone">
                                    @error('telephone')
                                        {{ $message }}
                                    @else
                                        <span x-text="messageTelephone">Le numéro doit comporter 10 chiffres.</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="form-text" id="aide-telephone">
                                <span x-text="configPays.nom">Côte d'Ivoire</span> ·
                                <span x-text="configPays.longueur">10</span> chiffres.
                                Le titulaire recevra les codes de validation sur ce numéro.
                            </div>
                        </div>

                        <div x-show="recherche" class="small text-secondary mb-3" role="status" style="display: none">
                            <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Recherche du titulaire…
                        </div>

                        <template x-if="titulaire">
                            <div class="alert mb-3" x-bind:class="titulaire.carte_en_circulation ? 'alert-danger' : 'alert-info'" role="status">
                                <p class="fw-semibold mb-1">Titulaire déjà enregistré : renouvellement.</p>
                                <p class="mb-1 small" x-show="titulaire.carte_en_circulation">
                                    Carte <strong x-text="titulaire.carte_en_circulation"></strong> encore en circulation :
                                    déclarez-la perdue avant d'en activer une nouvelle.
                                </p>
                                <ul class="small mb-0 ps-3">
                                    <template x-for="carte in titulaire.cartes" x-bind:key="carte.numero">
                                        <li><span x-text="carte.numero"></span> — <span x-text="carte.statut"></span> (expire le <span x-text="carte.expire_le"></span>)</li>
                                    </template>
                                </ul>
                            </div>
                        </template>

                        <div class="row g-3">
                            <div class="col-12 col-sm-5">
                                <label for="nom" class="form-label fw-semibold">Nom</label>
                                <input type="text" id="nom" name="nom" x-model="nom" autocomplete="off"
                                       class="form-control form-control-lg text-uppercase @error('nom') is-invalid @enderror"
                                       required minlength="2" maxlength="100" pattern="[A-Za-zÀ-ÖØ-öø-ÿ][A-Za-zÀ-ÖØ-öø-ÿ '’\-]*"
                                       aria-describedby="erreur-nom">
                                <div class="invalid-feedback" id="erreur-nom">
                                    @error('nom') {{ $message }} @else Nom obligatoire (lettres uniquement). @enderror
                                </div>
                            </div>
                            <div class="col-12 col-sm-7">
                                <label for="prenom" class="form-label fw-semibold">Prénoms</label>
                                <input type="text" id="prenom" name="prenom" x-model="prenom" autocomplete="off"
                                       class="form-control form-control-lg @error('prenom') is-invalid @enderror"
                                       required minlength="2" maxlength="150" pattern="[A-Za-zÀ-ÖØ-öø-ÿ][A-Za-zÀ-ÖØ-öø-ÿ '’\-]*"
                                       aria-describedby="erreur-prenom">
                                <div class="invalid-feedback" id="erreur-prenom">
                                    @error('prenom') {{ $message }} @else Prénoms obligatoires (lettres uniquement). @enderror
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    {{-- Étape 2 : carte --}}
                    <fieldset>
                        <legend class="h6 fw-bold texte-or-fonce">2. Carte</legend>

                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label for="numero_carte" class="form-label fw-semibold">Numéro de la carte</label>
                                <input type="text" id="numero_carte" name="numero_carte" inputmode="numeric" autocomplete="off"
                                       class="form-control form-control-lg font-monospace @error('numero_carte') is-invalid @enderror"
                                       x-model="numero" x-on:input="filtrerChiffres('numero', 7)"
                                       x-bind:class="numero !== '' && !numeroValide ? 'is-invalid' : ''"
                                       required pattern="\d{7}" maxlength="7" placeholder="0000001"
                                       aria-describedby="erreur-numero">
                                <div class="invalid-feedback" id="erreur-numero">
                                    @error('numero_carte') {{ $message }} @else 7 chiffres, tels qu'imprimés sur la carte. @enderror
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="numero_carte_confirmation" class="form-label fw-semibold">Confirmer le numéro</label>
                                <input type="text" id="numero_carte_confirmation" name="numero_carte_confirmation" inputmode="numeric" autocomplete="off"
                                       class="form-control form-control-lg font-monospace"
                                       x-model="confirmation" x-on:input="filtrerChiffres('confirmation', 7)"
                                       x-on:paste.prevent
                                       x-bind:class="confirmation !== '' && !numerosIdentiques ? 'is-invalid' : (numerosIdentiques ? 'is-valid' : '')"
                                       required pattern="\d{7}" maxlength="7"
                                       aria-describedby="erreur-confirmation">
                                <div class="invalid-feedback" id="erreur-confirmation">Les deux saisies ne correspondent pas.</div>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <div class="card-footer bg-transparent border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-or btn-lg w-100"
                            x-bind:disabled="titulaire && titulaire.carte_en_circulation">
                        Activer la carte
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>

<x-layouts.base titre="Connexion" classe-body="fond-nuit min-vh-100 d-flex align-items-center">
    <main class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
                <div class="text-center mb-4">
                    <img src="{{ App\Services\Parametres::logoUrl() }}" alt="{{ App\Services\Parametres::nomOrganisation() }}" width="72" height="72" class="rounded mb-3 logo-marque">
                    <p class="texte-eau fw-semibold mb-0">{{ App\Services\Parametres::nomOrganisation() }}</p>
                    <h1 class="fw-bolder text-white mb-0">{{ App\Services\Parametres::nomApplication() }}</h1>
                </div>

                <div class="card border-0 shadow-lg bordure-or">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-3 text-center">Connexion</h2>

                        @if ($errors->any())
                            <div class="alert alert-danger py-2" role="alert" id="erreurs-connexion">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login.store') }}" novalidate
                              x-data="connexion"
                              x-on:submit="envoi = true">
                            @csrf

                            {{-- Piège à robots : hors écran et ignoré des lecteurs d'écran, un humain le laisse vide. --}}
                            <div class="champ-piege" aria-hidden="true">
                                <label for="{{ \App\Http\Requests\Auth\ConnexionRequest::CHAMP_PIEGE }}">Laissez ce champ vide</label>
                                <input type="text" id="{{ \App\Http\Requests\Auth\ConnexionRequest::CHAMP_PIEGE }}"
                                       name="{{ \App\Http\Requests\Auth\ConnexionRequest::CHAMP_PIEGE }}"
                                       value="" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="mb-3">
                                <label for="nom_utilisateur" class="form-label fw-semibold">Nom d'utilisateur</label>
                                <input type="text" id="nom_utilisateur" name="nom_utilisateur"
                                       value="{{ old('nom_utilisateur') }}"
                                       class="form-control form-control-lg @error('nom_utilisateur') is-invalid @enderror"
                                       autocomplete="username" autocapitalize="none" spellcheck="false"
                                       required maxlength="50" autofocus
                                       @error('nom_utilisateur') aria-describedby="erreurs-connexion" @enderror>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold">Mot de passe</label>
                                <div class="input-group input-group-lg">
                                    <input x-bind:type="afficher ? 'text' : 'password'" type="password" x-ref="pin"
                                           id="password" name="password"
                                           class="form-control @error('password') is-invalid @enderror"
                                           inputmode="numeric" autocomplete="current-password" required maxlength="72">
                                    <button type="button" class="btn btn-outline-secondary"
                                            x-on:click="afficher = !afficher"
                                            x-bind:aria-label="afficher ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                                            aria-label="Afficher le mot de passe" aria-controls="password">
                                        <span x-text="afficher ? 'Masquer' : 'Afficher'">Afficher</span>
                                    </button>
                                </div>
                            </div>


                            <button type="submit" class="btn btn-encre btn-lg w-100" x-bind:disabled="envoi">
                                <span x-show="envoi" class="spinner-border spinner-border-sm me-2" aria-hidden="true" style="display: none"></span>
                                <span x-text="envoi ? 'Connexion…' : 'Se connecter'">Se connecter</span>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <img src="{{ asset('images/papillon-or.png') }}" alt="" width="90" aria-hidden="true">
                    <p class="small text-white-50 mt-2 mb-0">Mot de passe oublié ou compte verrouillé&nbsp;? Contactez votre administrateur.</p>

                    <div x-data="installationApp">
                        <button type="button" class="btn btn-outline-light mt-3" x-show="disponible" x-on:click="installer()" style="display: none">
                            <i class="bi bi-phone me-1" aria-hidden="true"></i>Installer l'application
                        </button>
                        <p class="small text-white-50 mt-3 mb-0" x-show="iphone" style="display: none">
                            Installer sur iPhone : touchez <i class="bi bi-box-arrow-up" aria-label="Partager"></i>
                            puis « Sur l'écran d'accueil ».
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</x-layouts.base>

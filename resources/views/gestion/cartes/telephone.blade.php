<x-layouts.app titre="Changer le téléphone" sous-titre="Back-office · Cartes">
    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('gestion.cartes.show', $carte) }}">{{ $carte->numeroFormate() }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('gestion.cartes.titulaire.edit', $carte) }}">Titulaire</a></li>
            <li class="breadcrumb-item active" aria-current="page">Téléphone</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-7 col-xl-6">
            <h1 class="h3 fw-bold mb-3">Changer le téléphone de {{ $carte->titulaire->nomComplet() }}</h1>

            <div class="alert alert-warning d-flex gap-2" role="note">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                <div>
                    Les codes de validation seront désormais envoyés au nouveau numéro. Vérifiez l'identité du titulaire.
                    Un SMS d'information sera envoyé à l'ancien numéro ({{ $carte->titulaire->telephoneFormate() }}) et les codes en cours seront annulés.
                </div>
            </div>

            <form method="POST" action="{{ route('gestion.cartes.titulaire.telephone.update', $carte) }}" class="card border-0 shadow-sm" novalidate
                  data-confirmer="Le téléphone du titulaire va être remplacé." data-titre="Confirmer le changement de numéro ?"
                  data-bouton-confirmer="Changer le numéro" data-danger="1">
                @csrf
                @method('PUT')
                <div class="card-body p-4">
                    <label for="telephone" class="form-label fw-semibold">Nouveau numéro</label>
                    <div class="input-group input-group-lg has-validation">
                        <label for="pays_telephone" class="visually-hidden">Pays du téléphone</label>
                        <select id="pays_telephone" name="pays_telephone" class="form-select flex-grow-0 selecteur-pays">
                            @foreach ($pays as $code => $config)
                                <option value="{{ $code }}" @selected(old('pays_telephone', App\Services\Telephone::paysParDefaut()) === $code)>+{{ $config['indicatif'] }}</option>
                            @endforeach
                        </select>
                        <input type="tel" id="telephone" name="telephone" value="{{ old('telephone') }}" inputmode="tel" autocomplete="off"
                               class="form-control @error('telephone') is-invalid @enderror" required maxlength="25" autofocus>
                        @error('telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" class="btn btn-danger btn-lg w-100 mt-4">Changer le numéro</button>
                    <a href="{{ route('gestion.cartes.titulaire.edit', $carte) }}" class="btn btn-link w-100 mt-2">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>

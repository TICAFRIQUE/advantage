{{--
    Champs d'un compte (création d'un utilisateur, modification d'une fiche).
    Variables : $compte (?User), $roles (list<Role>, vide = rôle non modifiable), $pays.
--}}
@php
    $telephone = old('telephone', $compte?->telephone ? App\Services\Telephone::formater($compte->telephone) : null);
    $paysTelephone = old('pays_telephone', $compte?->telephone
        ? App\Services\Telephone::paysDepuisIndicatif($compte->telephone)
        : App\Services\Telephone::paysParDefaut());
@endphp

<div class="row g-3">
    <div class="col-12 col-sm-6">
        <label for="nom" class="form-label fw-semibold">Nom complet <span class="text-secondary fw-normal">(facultatif)</span></label>
        <input type="text" id="nom" name="nom" value="{{ old('nom', $compte?->nom) }}" maxlength="150" autocomplete="off"
               class="form-control @error('nom') is-invalid @enderror">
        @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-sm-6">
        <label for="nom_utilisateur" class="form-label fw-semibold">Nom d'utilisateur</label>
        <input type="text" id="nom_utilisateur" name="nom_utilisateur" value="{{ old('nom_utilisateur', $compte?->nom_utilisateur) }}"
               required minlength="3" maxlength="50" pattern="[a-z0-9._\-]+" autocapitalize="none" spellcheck="false" autocomplete="off"
               placeholder="ex. awa.kone" class="form-control font-monospace @error('nom_utilisateur') is-invalid @enderror"
               aria-describedby="aide-nom-utilisateur">
        <div id="aide-nom-utilisateur" class="form-text">Identifiant de connexion : minuscules, chiffres, point, tiret.</div>
        @error('nom_utilisateur')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-sm-6">
        <label for="telephone" class="form-label fw-semibold">Téléphone <span class="text-secondary fw-normal">(facultatif)</span></label>
        <div class="input-group has-validation">
            <label for="pays_telephone" class="visually-hidden">Pays du téléphone</label>
            <select id="pays_telephone" name="pays_telephone" class="form-select flex-grow-0 selecteur-pays">
                @foreach ($pays as $code => $config)
                    <option value="{{ $code }}" @selected($paysTelephone === $code)>+{{ $config['indicatif'] }}</option>
                @endforeach
            </select>
            <input type="tel" id="telephone" name="telephone" value="{{ $telephone }}" inputmode="tel" maxlength="25" autocomplete="off"
                   class="form-control @error('telephone') is-invalid @enderror">
            @error('telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-12 col-sm-6">
        <label for="email" class="form-label fw-semibold">E-mail <span class="text-secondary fw-normal">(facultatif)</span></label>
        <input type="email" id="email" name="email" value="{{ old('email', $compte?->email) }}" maxlength="150" autocomplete="off"
               class="form-control @error('email') is-invalid @enderror">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @if ($roles !== [])
        <div class="col-12">
            <label for="role" class="form-label fw-semibold">Rôle</label>
            <select id="role" name="role" required class="form-select @error('role') is-invalid @enderror">
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $compte?->rolePrincipal()?->value ?? App\Enums\Role::Agent->value) === $role->value)>
                        {{ $role->libelle() }}
                    </option>
                @endforeach
            </select>
            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endif
</div>

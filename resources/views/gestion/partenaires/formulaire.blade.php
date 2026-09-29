@php($creation = ! $partenaire->exists)

<x-layouts.app :titre="$creation ? 'Nouveau partenaire' : 'Modifier '.$partenaire->nom" sous-titre="Back-office · Partenaires">
    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('gestion.partenaires.index') }}">Partenaires</a></li>
            @unless ($creation)
                <li class="breadcrumb-item"><a href="{{ route('gestion.partenaires.show', $partenaire) }}">{{ $partenaire->nom }}</a></li>
            @endunless
            <li class="breadcrumb-item active" aria-current="page">{{ $creation ? 'Nouveau' : 'Modifier' }}</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8 col-xl-6">
            <h1 class="h3 fw-bold mb-3">{{ $creation ? 'Nouveau partenaire' : 'Modifier le partenaire' }}</h1>

            <form method="POST" novalidate class="card border-0 shadow-sm"
                  action="{{ $creation ? route('gestion.partenaires.store') : route('gestion.partenaires.update', $partenaire) }}">
                @csrf
                @unless ($creation) @method('PUT') @endunless

                <div class="card-body p-4">
                    <div class="mb-3">
                        <label for="nom" class="form-label fw-semibold">Nom du partenaire</label>
                        <input type="text" id="nom" name="nom" value="{{ old('nom', $partenaire->nom) }}" required maxlength="150"
                               class="form-control form-control-lg @error('nom') is-invalid @enderror" autofocus>
                        @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="secteur" class="form-label fw-semibold">Secteur</label>
                            <input type="text" id="secteur" name="secteur" value="{{ old('secteur', $partenaire->secteur) }}" maxlength="100"
                                   class="form-control @error('secteur') is-invalid @enderror" placeholder="Pharmacie, restaurant…">
                            @error('secteur')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="localisation" class="form-label fw-semibold">Localisation</label>
                            <input type="text" id="localisation" name="localisation" value="{{ old('localisation', $partenaire->localisation) }}" maxlength="150"
                                   class="form-control @error('localisation') is-invalid @enderror" placeholder="Cocody, Abidjan">
                            @error('localisation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Contact : pays + numéro national (le numéro enregistré est au format E.164). --}}
                    {{-- Forme courte uniquement : un bloc php/endphp serait confondu avec le php() de la 1re ligne. --}}
                    @php($paysStocke = $partenaire->contact ? App\Services\Telephone::paysDepuisIndicatif($partenaire->contact) : null)
                    @php($paysContact = old('pays_contact', $paysStocke ?? App\Services\Telephone::paysParDefaut()))
                    {{-- Indicatif inconnu : le numéro « +… » complet est affiché, il est reconnu comme international. --}}
                    @php($numeroContact = old('contact', $paysStocke !== null ? substr($partenaire->contact, 1 + strlen(App\Services\Telephone::tousLesPays()[$paysStocke]['indicatif'])) : (string) $partenaire->contact))
                    <div class="mb-3">
                        <label for="contact" class="form-label fw-semibold">Contact (téléphone)</label>
                        <div class="input-group has-validation">
                            <label for="pays_contact" class="visually-hidden">Pays du contact</label>
                            <select id="pays_contact" name="pays_contact" class="form-select flex-grow-0 selecteur-pays @error('pays_contact') is-invalid @enderror">
                                @foreach (App\Services\Telephone::tousLesPays() as $code => $config)
                                    <option value="{{ $code }}" title="{{ $config['nom'] }}" @selected($paysContact === $code)>
                                        {{ collect(str_split($code))->map(fn ($lettre) => mb_chr(0x1F1E6 + ord($lettre) - 65))->implode('') }} +{{ $config['indicatif'] }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="tel" id="contact" name="contact" value="{{ $numeroContact }}" inputmode="tel" autocomplete="off"
                                   required maxlength="25" placeholder="07 07 12 34 56"
                                   class="form-control @error('contact') is-invalid @enderror">
                            @error('contact')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="responsable" class="form-label fw-semibold">Responsable <span class="fw-normal text-secondary">(facultatif)</span></label>
                            <input type="text" id="responsable" name="responsable" value="{{ old('responsable', $partenaire->responsable) }}" maxlength="150"
                                   autocomplete="off" class="form-control @error('responsable') is-invalid @enderror" placeholder="Nom du responsable">
                            @error('responsable')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="email" class="form-label fw-semibold">Email <span class="fw-normal text-secondary">(facultatif)</span></label>
                            <input type="email" id="email" name="email" value="{{ old('email', $partenaire->email) }}" maxlength="190"
                                   autocomplete="off" inputmode="email" class="form-control @error('email') is-invalid @enderror" placeholder="contact@exemple.ci">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="taux_reduction" class="form-label fw-semibold">Taux de réduction</label>
                        <div class="input-group input-group-lg has-validation" style="max-width: 14rem">
                            <input type="text" id="taux_reduction" name="taux_reduction" inputmode="decimal" required
                                   value="{{ old('taux_reduction', $partenaire->exists ? rtrim(rtrim((string) $partenaire->taux_reduction, '0'), '.') : '') }}"
                                   class="form-control @error('taux_reduction') is-invalid @enderror" aria-describedby="aide-taux">
                            <span class="input-group-text">%</span>
                            @error('taux_reduction')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-text" id="aide-taux">
                            @if ($creation)
                                Entre 0 et 100 %, deux décimales maximum.
                            @else
                                Tout changement est historisé ; les transactions passées gardent leur taux.
                            @endif
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 mt-4">{{ $creation ? 'Créer le partenaire' : 'Enregistrer' }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>

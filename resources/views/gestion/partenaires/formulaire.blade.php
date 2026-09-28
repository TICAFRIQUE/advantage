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

                    <div class="mb-3">
                        <label for="contact" class="form-label fw-semibold">Contact</label>
                        <input type="text" id="contact" name="contact" value="{{ old('contact', $partenaire->contact) }}" maxlength="150"
                               class="form-control @error('contact') is-invalid @enderror" placeholder="Nom, téléphone ou email du responsable">
                        @error('contact')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

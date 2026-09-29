{{--
    Boutons d'un formulaire de filtres : « filtrer » (icône) et, collé à lui,
    « réinitialiser » visible dès qu'un filtre est présent dans l'URL. Sur
    les tableaux Yajra (filtrage sans rechargement), resources/js/tableaux.js
    met à jour l'URL et l'affichage du bouton de réinitialisation.
--}}
@props(['reinitialiser'])

@php($actif = collect(request()->query())->except(['page', 'page_purges'])->filter(fn ($valeur) => filled($valeur))->isNotEmpty())

<div {{ $attributes->merge(['class' => 'd-flex gap-1 boutons-filtre']) }}>
    <button type="submit" class="btn btn-primary" title="Filtrer" aria-label="Appliquer les filtres">
        <i class="bi bi-funnel-fill" aria-hidden="true"></i>
    </button>
    <a href="{{ $reinitialiser }}" @class(['btn btn-outline-secondary', 'd-none' => ! $actif]) data-reinitialiser
       title="Réinitialiser les filtres" aria-label="Réinitialiser les filtres">
        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
    </a>
</div>

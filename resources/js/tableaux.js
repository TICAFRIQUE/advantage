/**
 * Tableaux Yajra DataTables (traitement côté serveur).
 * Chargé uniquement sur les pages qui en ont besoin (jQuery hors du bundle principal).
 *
 * Usage : <table data-source="URL" data-filtres="#formulaire"> avec des
 * <th data-colonne="nom" data-triable="false">.
 */
import $ from 'jquery';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

window.$ = window.jQuery = $;

const francais = {
    emptyTable: 'Aucune donnée disponible',
    info: '_START_ à _END_ sur _TOTAL_',
    infoEmpty: '0 résultat',
    infoFiltered: '(filtré sur _MAX_)',
    lengthMenu: '_MENU_ par page',
    loadingRecords: 'Chargement…',
    processing: 'Chargement…',
    search: 'Rechercher :',
    zeroRecords: 'Aucun résultat',
    paginate: { first: 'Premier', last: 'Dernier', next: 'Suivant', previous: 'Précédent' },
    aria: { orderable: 'Trier par cette colonne' },
};

document.querySelectorAll('table[data-source]').forEach((tableau) => {
    const filtres = tableau.dataset.filtres ? document.querySelector(tableau.dataset.filtres) : null;

    // data-lien="colonne_url" : la cellule devient un lien. Les valeurs sont
    // déjà échappées côté serveur par Yajra (pas de double échappement).
    const colonnes = [...tableau.querySelectorAll('thead th')].map((th) => ({
        data: th.dataset.colonne,
        name: th.dataset.colonne,
        orderable: th.dataset.triable !== 'false',
        searchable: false,
        render: th.dataset.lien
            ? (valeur, type, ligne) => (type === 'display' && ligne[th.dataset.lien] ? `<a href="${ligne[th.dataset.lien]}">${valeur}</a>` : valeur)
            : undefined,
    }));

    const table = new DataTable(tableau, {
        serverSide: true,
        processing: true,
        responsive: true,
        pageLength: 25,
        // Tri initial sur la première colonne triable (côté serveur).
        order: [[Math.max(0, colonnes.findIndex((colonne) => colonne.orderable)), 'desc']],
        columns: colonnes,
        language: francais,
        ajax: {
            url: tableau.dataset.source,
            data: (donnees) => {
                if (filtres) {
                    new FormData(filtres).forEach((valeur, cle) => {
                        donnees[cle] = valeur;
                    });
                }
            },
        },
    });

    // data-soumission="page" sur le formulaire : soumission normale (les
    // indicateurs rendus côté serveur suivent les filtres).
    filtres?.addEventListener('submit', (evenement) => {
        if (filtres.dataset.soumission === 'page') {
            return;
        }

        evenement.preventDefault();
        table.ajax.reload();
    });
});

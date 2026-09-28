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

    const colonnes = [...tableau.querySelectorAll('thead th')].map((th) => ({
        data: th.dataset.colonne,
        name: th.dataset.colonne,
        orderable: th.dataset.triable !== 'false',
        searchable: false,
    }));

    const table = new DataTable(tableau, {
        serverSide: true,
        processing: true,
        responsive: true,
        pageLength: 25,
        order: [[0, 'desc']],
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

    filtres?.addEventListener('submit', (evenement) => {
        evenement.preventDefault();
        table.ajax.reload();
    });
});

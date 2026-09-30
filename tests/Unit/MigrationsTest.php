<?php

use Illuminate\Support\Facades\File;

/*
 * Sur un MySQL en explicit_defaults_for_timestamp=OFF (fréquent en hébergement
 * mutualisé), un timestamp NOT NULL sans défaut explicite reçoit en silence
 * ON UPDATE CURRENT_TIMESTAMP (date réécrite à chaque mise à jour), ou fait
 * échouer la migration (« Invalid default value »).
 */
it('gives every timestamp column an explicit default or null', function () {
    $fautifs = [];

    foreach (File::files(database_path('migrations')) as $fichier) {
        preg_match_all('/->timestamp\(\'(\w+)\'\)(?!->(nullable|useCurrent|default)\()/', $fichier->getContents(), $trouves);

        foreach ($trouves[1] as $colonne) {
            $fautifs[] = $fichier->getFilename().' : '.$colonne;
        }
    }

    expect($fautifs)->toBe([]);
});

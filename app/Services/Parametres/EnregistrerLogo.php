<?php

namespace App\Services\Parametres;

use App\Models\User;
use App\Services\Parametres;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Logo de l'application : l'image envoyée est entièrement réencodée en PNG
 * (512 px maximum) avec GD, ce qui élimine tout contenu caché (script,
 * métadonnées, fichier polyglotte). Stockée dans public/uploads/identite,
 * sans lien symbolique (compatible hébergement mutualisé).
 */
class EnregistrerLogo
{
    public const DOSSIER = 'uploads/identite';

    public function enregistrer(UploadedFile $fichier, User $auteur): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($fichier->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('Image illisible.');
        }

        [$largeur, $hauteur] = [imagesx($source), imagesy($source)];
        $echelle = min(1, 512 / max($largeur, $hauteur));
        $image = imagecreatetruecolor(max(1, (int) round($largeur * $echelle)), max(1, (int) round($hauteur * $echelle)));
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagecopyresampled($image, $source, 0, 0, 0, 0, imagesx($image), imagesy($image), $largeur, $hauteur);

        if (! is_dir(public_path(self::DOSSIER))) {
            mkdir(public_path(self::DOSSIER), 0755, true);
        }

        $chemin = self::DOSSIER.'/logo-'.now()->format('YmdHis').'.png';
        imagepng($image, public_path($chemin), 6);
        imagedestroy($image);
        imagedestroy($source);

        $this->supprimerAncien();
        Parametres::definir('identite.logo', $chemin, $auteur);

        return $chemin;
    }

    /**
     * Revient au logo fourni avec l'application.
     */
    public function reinitialiser(User $auteur): void
    {
        $this->supprimerAncien();
        Parametres::definir('identite.logo', null, $auteur);
    }

    private function supprimerAncien(): void
    {
        $ancien = Parametres::get('identite.logo');

        if ($ancien !== null && str_starts_with($ancien, self::DOSSIER.'/') && is_file(public_path($ancien))) {
            unlink(public_path($ancien));
        }
    }
}

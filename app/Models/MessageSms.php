<?php

namespace App\Models;

use App\Enums\StatutLivraison;
use App\Enums\TypeSms;
use App\Services\Telephone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Trace d'un SMS émis par la plateforme. Le contenu est chiffré en base et
 * masqué (pour un OTP) dès qu'un envoi réel a eu lieu.
 */
#[Table('messages_sms')]
#[Fillable(['telephone', 'type', 'contenu', 'statut', 'fournisseur'])]
#[Hidden(['contenu'])]
class MessageSms extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeSms::class,
            'statut' => StatutLivraison::class,
            'contenu' => 'encrypted',
            'tentatives' => 'integer',
            'envoye_le' => 'datetime',
        ];
    }

    public function telephoneFormate(): string
    {
        return Telephone::formater($this->telephone);
    }
}

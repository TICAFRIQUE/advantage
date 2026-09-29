<?php

namespace App\Services\Sauvegardes;

use DomainException;

/**
 * Sauvegarde ou restauration impossible : le message est destiné à l'utilisateur.
 */
class SauvegardeException extends DomainException {}

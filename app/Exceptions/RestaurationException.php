<?php

namespace App\Exceptions;

use DomainException;

/**
 * Restauration impossible (élément déjà actif, partenaire encore supprimé…) :
 * le message est destiné à l'utilisateur.
 */
class RestaurationException extends DomainException {}

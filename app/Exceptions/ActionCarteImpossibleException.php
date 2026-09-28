<?php

namespace App\Exceptions;

use DomainException;

/**
 * Action refusée par l'état de la carte (message destiné à l'utilisateur).
 */
class ActionCarteImpossibleException extends DomainException {}

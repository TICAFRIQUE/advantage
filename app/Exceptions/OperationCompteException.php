<?php

namespace App\Exceptions;

use DomainException;

/**
 * Opération sur un compte refusée (droits insuffisants, règle métier).
 */
class OperationCompteException extends DomainException {}

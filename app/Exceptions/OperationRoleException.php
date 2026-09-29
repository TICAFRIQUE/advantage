<?php

namespace App\Exceptions;

use DomainException;

/**
 * Opération sur un rôle refusée (droits insuffisants, règle métier) : le
 * message est destiné à l'utilisateur.
 */
class OperationRoleException extends DomainException {}

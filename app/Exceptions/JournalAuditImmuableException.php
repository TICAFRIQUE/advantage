<?php

namespace App\Exceptions;

use LogicException;

class JournalAuditImmuableException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Le journal d\'audit est en ajout seul : modification et suppression interdites.');
    }
}

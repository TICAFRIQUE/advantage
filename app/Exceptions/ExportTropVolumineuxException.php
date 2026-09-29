<?php

namespace App\Exceptions;

use DomainException;

/**
 * Export refusé : trop de lignes pour le format demandé (le message propose
 * d'affiner les filtres ou de choisir un autre format).
 */
class ExportTropVolumineuxException extends DomainException {}

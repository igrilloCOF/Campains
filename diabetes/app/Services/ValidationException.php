<?php
declare(strict_types=1);
namespace App\Services;
use DomainException;
final class ValidationException extends DomainException
{
    public function __construct(
        public readonly array $errors,
        public readonly array $answers = [],
        public readonly array $warnings = [],
        public readonly array $observations = []
    ) { parent::__construct('Revisa los campos indicados.'); }
}

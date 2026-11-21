<?php
declare(strict_types=1);

namespace App\Model;

/** An expected rejection of user data, safe to expose as a validation response. */
final class ValidationError extends \RuntimeException
{
}

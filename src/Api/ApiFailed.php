<?php

declare(strict_types=1);

namespace EventsSite\Api;

use RuntimeException;

/** The API could not be asked, or did not give what was asked for: no answer, a time out, a 500, not JSON. */
final class ApiFailed extends RuntimeException
{
}

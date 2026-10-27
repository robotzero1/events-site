<?php

declare(strict_types=1);

namespace EventsSite\Api;

use RuntimeException;

/** The API failed and no earlier answer was kept: the page cannot be made just now (503). */
final class Unavailable extends RuntimeException
{
}

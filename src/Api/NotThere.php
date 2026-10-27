<?php

declare(strict_types=1);

namespace EventsSite\Api;

use RuntimeException;

/** The API says there is no such thing (404): a page about it is "not found". */
final class NotThere extends RuntimeException
{
}

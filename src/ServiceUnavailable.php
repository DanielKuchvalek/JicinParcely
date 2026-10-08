<?php

declare(strict_types=1);

namespace JicinParcely;

/** Služba ČÚZK neodpověděla nebo vrátila chybu (HTTP 502). */
final class ServiceUnavailable extends \RuntimeException
{
}

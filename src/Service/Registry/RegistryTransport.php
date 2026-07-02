<?php

declare(strict_types=1);

namespace App\Service\Registry;

/** Replaceable seam boundary for registry fetches. */
interface RegistryTransport
{
    public function fetch(string $url): RegistryFetchResult;
}

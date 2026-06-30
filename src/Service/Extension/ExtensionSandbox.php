<?php

declare(strict_types=1);

namespace App\Service\Extension;

interface ExtensionSandbox
{
    /** @return array{supported:bool,adapter:string,reason:?string} */
    public function probe(): array;

    /**
     * @param array<string,mixed> $handler
     * @param array<string,mixed> $job
     * @return array{status:string,exit_code:?int,duration_ms:int,output_bytes:int,stdout_json:mixed,error:?string}
     */
    public function run(array $handler, array $job): array;
}

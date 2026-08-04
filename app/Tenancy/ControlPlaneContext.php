<?php

namespace App\Tenancy;

final class ControlPlaneContext
{
    public function __construct(public readonly string $host)
    {
    }
}

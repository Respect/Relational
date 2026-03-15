<?php

declare(strict_types=1);

namespace Respect\Relational;

class TestFetchingClassArgs
{
    public int|null $testa = null;

    public string|null $testb = null;

    public int|null $testez = null;

    public function __construct(public string|null $testd = null)
    {
    }
}

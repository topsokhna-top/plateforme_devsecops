<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function testAddition(): void
    {
        $result = 2 + 4;

        $this->assertSame(6, $result);
    }
}
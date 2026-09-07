<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function testAddition(): void
    {
        $result = 2 + 3;

        $this->assertSame(5, $result);
    }
}
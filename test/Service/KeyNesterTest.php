<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\Service;

use Atoolo\TranslationApi\Service\KeyNester;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeyNester::class)]
class KeyNesterTest extends TestCase
{
    public function testNestsDottedKeys(): void
    {
        $nested = (new KeyNester())->nest([
            'greeting' => 'Hello',
            'cart.items' => 'Items',
            'cart.checkout.button' => 'Checkout',
        ]);

        $this->assertSame(
            [
                'greeting' => 'Hello',
                'cart' => [
                    'items' => 'Items',
                    'checkout' => ['button' => 'Checkout'],
                ],
            ],
            $nested,
        );
    }

    public function testKeepsRemainderFlatIfPrefixIsAMessage(): void
    {
        $nested = (new KeyNester())->nest([
            'a.b.c' => 'deep',
            'a.b' => 'message',
            'a.x' => 'sibling',
        ]);

        $this->assertSame(
            [
                'a' => [
                    'b' => 'message',
                    'x' => 'sibling',
                    'b.c' => 'deep',
                ],
            ],
            $nested,
        );
    }

    public function testKeepsKeysWithEmptySegmentsFlat(): void
    {
        $nested = (new KeyNester())->nest([
            'a..b' => 'double dot',
            'trailing.' => 'trailing dot',
        ]);

        $this->assertSame(
            ['trailing.' => 'trailing dot', 'a..b' => 'double dot'],
            $nested,
        );
    }

    public function testHandlesNumericSegments(): void
    {
        $nested = (new KeyNester())->nest([
            'error.404.headline' => 'Not found',
        ]);

        $this->assertSame(
            ['error' => [404 => ['headline' => 'Not found']]],
            $nested,
        );
    }
}

<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\DependencyInjection;

use Atoolo\TranslationApi\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
class ConfigurationTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(
            new Configuration(),
            [$config],
        );
    }

    public function testDefaults(): void
    {
        $this->assertSame(
            [
                'domains' => [],
                'locales' => [],
                'cache_max_age' => 300,
                'cors_allow_origins' => [],
            ],
            $this->process([]),
        );
    }

    public function testCustomValues(): void
    {
        $config = $this->process([
            'domains' => ['frontend'],
            'locales' => ['de', 'en_GB'],
            'cache_max_age' => 0,
            'cors_allow_origins' => ['*'],
        ]);

        $this->assertSame(['frontend'], $config['domains']);
        $this->assertSame(['de', 'en_GB'], $config['locales']);
        $this->assertSame(0, $config['cache_max_age']);
        $this->assertSame(['*'], $config['cors_allow_origins']);
    }

    public function testRejectsNegativeCacheMaxAge(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['cache_max_age' => -1]);
    }

    public function testRejectsEmptyDomain(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['domains' => ['']]);
    }
}

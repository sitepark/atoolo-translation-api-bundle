<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\DependencyInjection\Compiler;

use Atoolo\TranslationApi\DependencyInjection\Compiler\ValidateSourcesPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Translator;

#[CoversClass(ValidateSourcesPass::class)]
class ValidateSourcesPassTest extends TestCase
{
    /**
     * @param array<string, mixed> $attributes
     */
    private function process(string $class, array $attributes, ?ContainerBuilder $container = null): void
    {
        $container ??= new ContainerBuilder();
        $container->register('source.' . count($container->getDefinitions()), $class)
            ->addTag(ValidateSourcesPass::TAG, $attributes);
        (new ValidateSourcesPass())->process($container);
    }

    public function testValidSource(): void
    {
        $this->expectNotToPerformAssertions();
        $this->process(Translator::class, ['source' => 'overrides']);
    }

    public function testClassFromParameter(): void
    {
        $this->expectNotToPerformAssertions();
        $container = new ContainerBuilder();
        $container->setParameter('translator.class', Translator::class);
        $this->process('%translator.class%', ['source' => 'overrides'], $container);
    }

    public function testFactoryWithoutClass(): void
    {
        $this->expectNotToPerformAssertions();
        $container = new ContainerBuilder();
        $container->register('source.factory')
            ->setFactory([Translator::class, 'create'])
            ->addTag(ValidateSourcesPass::TAG, ['source' => 'overrides']);
        (new ValidateSourcesPass())->process($container);
    }

    public function testMissingSourceAttribute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs the attribute "source"');
        $this->process(Translator::class, []);
    }

    public function testDuplicateSource(): void
    {
        $container = new ContainerBuilder();
        $container->register('a', Translator::class)
            ->addTag(ValidateSourcesPass::TAG, ['source' => 'overrides']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('defined by service "a"');
        $this->process(Translator::class, ['source' => 'overrides'], $container);
    }

    public function testNoTranslatorBag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has to implement');
        $this->process(\stdClass::class, ['source' => 'overrides']);
    }

    public function testUnknownClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->process('App\\Unknown', ['source' => 'overrides']);
    }
}

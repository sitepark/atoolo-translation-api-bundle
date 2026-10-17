<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Test\Service;

use Atoolo\TranslationApi\Service\TranslationSources;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\Translator;

#[CoversClass(TranslationSources::class)]
class TranslationSourcesTest extends TestCase
{
    private Translator $translator;

    private Translator $overrides;

    private TranslationSources $sources;

    protected function setUp(): void
    {
        $this->translator = new Translator('en');
        $this->overrides = new Translator('en');
        $this->sources = new TranslationSources(
            $this->translator,
            new ServiceLocator([
                'overrides' => fn(): Translator => $this->overrides,
                'invalid' => static fn(): \stdClass => new \stdClass(),
            ]),
        );
    }

    public function testWithoutNameIsTheTranslator(): void
    {
        $this->assertSame($this->translator, $this->sources->get(null));
        $this->assertSame($this->translator, $this->sources->get(''));
        $this->assertSame($this->translator, $this->sources->fromRequest(new Request()));
    }

    public function testNamedSource(): void
    {
        $this->assertSame($this->overrides, $this->sources->get('overrides'));
        $this->assertSame(
            $this->overrides,
            $this->sources->fromRequest(new Request(['source' => 'overrides'])),
        );
    }

    public function testUnknownSource(): void
    {
        $this->assertNull($this->sources->get('unknown'));
        $this->assertNull($this->sources->get('invalid'));
    }

    public function testUnknownSourceOfRequest(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->sources->fromRequest(new Request(['source' => 'unknown']));
    }
}

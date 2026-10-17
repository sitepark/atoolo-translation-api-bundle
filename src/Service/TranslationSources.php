<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * The sources the messages are read from. Without a name it is the
 * translator of the application. An application can register further
 * sources by tagging a {@see TranslatorBagInterface} service with
 * `atoolo_translation_api.source` and its name as attribute `source`, e.g.
 * the messages of one kind of resources only. What a source holds is up to
 * the application.
 */
class TranslationSources
{
    public function __construct(
        private readonly TranslatorBagInterface $translator,
        private readonly ContainerInterface $sources,
    ) {}

    /**
     * Returns the source named by the query parameter `source`.
     *
     * @throws NotFoundHttpException if no source has the name
     */
    public function fromRequest(Request $request): TranslatorBagInterface
    {
        $name = $request->query->getString('source');
        return $this->get($name)
            ?? throw new NotFoundHttpException(sprintf('unknown translation source "%s"', $name));
    }

    /**
     * @return TranslatorBagInterface|null `null` if no source has the name
     */
    public function get(?string $name): ?TranslatorBagInterface
    {
        if ($name === null || $name === '') {
            return $this->translator;
        }
        if (!$this->sources->has($name)) {
            return null;
        }
        $source = $this->sources->get($name);
        return $source instanceof TranslatorBagInterface ? $source : null;
    }
}

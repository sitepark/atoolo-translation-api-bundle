<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * Checks the services tagged as translation source, so that a mistake fails
 * the build instead of answering with "unknown translation source": each
 * needs a unique attribute `source` and has to implement
 * {@see TranslatorBagInterface}.
 */
final class ValidateSourcesPass implements CompilerPassInterface
{
    public const string TAG = 'atoolo_translation_api.source';

    public function process(ContainerBuilder $container): void
    {
        $names = [];
        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            foreach ($tags as $attributes) {
                $name = $attributes['source'] ?? null;
                if (!is_string($name) || $name === '') {
                    throw new InvalidArgumentException(sprintf(
                        'The tag "%s" of service "%s" needs the attribute "source".',
                        self::TAG,
                        $id,
                    ));
                }
                if (isset($names[$name])) {
                    throw new InvalidArgumentException(sprintf(
                        'The translation source "%s" is defined by service "%s" and "%s".',
                        $name,
                        $names[$name],
                        $id,
                    ));
                }
                $names[$name] = $id;
            }
            $this->checkClass($container, $id);
        }
    }

    private function checkClass(ContainerBuilder $container, string $id): void
    {
        $definition = $container->getDefinition($id);
        // a factory without class: the type is only known at runtime
        if ($definition->getClass() === null && $definition->getFactory() !== null) {
            return;
        }
        $class = $container->getParameterBag()->resolveValue(
            $definition->getClass() ?? $id,
        );
        $reflection = is_string($class)
            ? $container->getReflectionClass($class, false)
            : null;
        if ($reflection === null || !$reflection->implementsInterface(TranslatorBagInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'The translation source service "%s" (%s) has to implement %s.',
                $id,
                is_string($class) ? $class : get_debug_type($class),
                TranslatorBagInterface::class,
            ));
        }
    }
}

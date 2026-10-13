<?php

declare(strict_types=1);

namespace Atoolo\TranslationApi\Service;

/**
 * Turns the flat, dot separated translation keys of a Symfony catalogue into
 * nested objects as used by i18next resource files.
 */
class KeyNester
{
    /**
     * Keys like `a.b.c` become `['a' => ['b' => ['c' => …]]]`. If a key is
     * both a message and the prefix of other keys (`a` and `a.b`), the
     * remainder of the longer key is kept flat next to the message
     * (`['a' => …, 'a.b' => …]`). i18next resolves such flat keys as well.
     *
     * @param array<string, string> $messages
     * @return array<string, mixed>
     */
    public function nest(array $messages): array
    {
        $keys = array_map('strval', array_keys($messages));
        // shorter keys first, so that messages always win over subtrees
        usort(
            $keys,
            static fn(string $a, string $b): int
                => substr_count($a, '.') <=> substr_count($b, '.'),
        );

        $nested = [];
        foreach ($keys as $key) {
            $this->insert($nested, $key, $messages[$key]);
        }
        return $nested;
    }

    /**
     * @param array<string, mixed> $tree
     */
    private function insert(array &$tree, string $key, string $message): void
    {
        $segments = explode('.', $key);
        if (in_array('', $segments, true)) {
            $tree[$key] = $message;
            return;
        }

        $node = &$tree;
        while (count($segments) > 1) {
            $segment = $segments[0];
            if (isset($node[$segment]) && !is_array($node[$segment])) {
                break;
            }
            $node[$segment] ??= [];
            $node = &$node[$segment];
            array_shift($segments);
        }
        $node[implode('.', $segments)] = $message;
    }
}

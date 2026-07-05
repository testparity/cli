<?php

declare(strict_types=1);

namespace App\Services\CoverageLinkers;

/**
 * Specs: S004
 *
 * Registry that auto-detects and applies the appropriate coverage linker for a test file.
 */
class CoverageLinkerRegistry
{
    /** @var list<CoverageLinkerInterface> */
    private array $linkers;

    /**
     * @param  list<CoverageLinkerInterface>|null  $linkers  Custom linkers (null = default set)
     */
    public function __construct(?array $linkers = null)
    {
        $this->linkers = $linkers ?? self::defaults();
    }

    /**
     * @return list<CoverageLinkerInterface>
     */
    public static function defaults(): array
    {
        return [
            new PestCoversLinker,
            new PhpAttributeLinker,
        ];
    }

    /**
     * Build a registry from config strings (e.g. ['pest-covers', 'php-attribute']).
     * Falls back to defaults if null/empty.
     *
     * @param  list<string>|null  $linkerNames
     */
    public static function fromConfig(?array $linkerNames = null, string $attributeFqcn = 'PHPUnit\Framework\Attributes\CoversClass'): self
    {
        if ($linkerNames === null || $linkerNames === [] || $linkerNames === ['auto']) {
            return new self([
                new PestCoversLinker,
                new PhpAttributeLinker($attributeFqcn),
            ]);
        }

        $linkers = [];
        foreach ($linkerNames as $name) {
            $linker = match ($name) {
                'pest-covers' => new PestCoversLinker,
                'php-attribute' => new PhpAttributeLinker($attributeFqcn),
                default => null,
            };
            if ($linker !== null) {
                $linkers[] = $linker;
            }
        }

        return new self($linkers);
    }

    /**
     * Find the first linker that supports this file content and extract covered classes.
     *
     * @return array{linker: string|null, classes: list<string>}
     */
    public function extractCoveredClasses(string $source, array $useMap, ?string $namespace): array
    {
        foreach ($this->linkers as $linker) {
            if ($linker->supports($source)) {
                $classes = $linker->extractCoveredClasses($source, $useMap, $namespace);

                return ['linker' => $linker->name(), 'classes' => $classes];
            }
        }

        return ['linker' => null, 'classes' => []];
    }

    /**
     * Check if any linker supports this file content.
     */
    public function hasSupport(string $source): bool
    {
        foreach ($this->linkers as $linker) {
            if ($linker->supports($source)) {
                return true;
            }
        }

        return false;
    }
}

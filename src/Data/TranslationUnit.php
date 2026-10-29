<?php

declare(strict_types=1);

namespace AbeTwoThree\LangExport\Data;

/**
 * One exportable block of translations: a locale's JSON lines, or one group of an app or package namespace.
 */
final readonly class TranslationUnit
{
    public const string WILDCARD = '*';

    /**
     * Creates a unit for a locale, namespace and group.
     */
    public function __construct(
        public string $locale,
        public string $namespace,
        public string $group,
    ) {}

    /**
     * Creates the unit holding a locale's top-level JSON lines.
     */
    public static function json(string $locale): self
    {
        return new self($locale, self::WILDCARD, self::WILDCARD);
    }

    /**
     * Tells whether this unit holds JSON lines rather than a PHP group.
     */
    public function isJson(): bool
    {
        return $this->namespace === self::WILDCARD && $this->group === self::WILDCARD;
    }

    /**
     * Returns the name used as the output key and matched by only/except patterns.
     */
    public function key(): string
    {
        return match (true) {
            $this->isJson() => self::WILDCARD,
            $this->namespace === self::WILDCARD => $this->group,
            default => "{$this->namespace}::{$this->group}",
        };
    }
}

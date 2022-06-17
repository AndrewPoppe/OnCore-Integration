<?php

namespace Psalm\Type\Atomic;

use function preg_quote;
use function preg_replace;
use function stripos;
use function strpos;
use function strtolower;

/**
 * Denotes a specific class string, generated by expressions like `A::class`.
 */
class TLiteralClassString extends TLiteralString
{
    /**
     * Whether or not this type can represent a child of the class named in $value
     * @var bool
     */
    public $definite_class = false;

    public function __construct(string $value, bool $definite_class = false)
    {
        parent::__construct($value);
        $this->definite_class = $definite_class;
    }

    public function __toString(): string
    {
        return 'class-string';
    }

    public function getKey(bool $include_extra = true): string
    {
        return 'class-string(' . $this->value . ')';
    }

    /**
     * @param array<lowercase-string, string> $aliased_classes
     */
    public function toPhpString(
        ?string $namespace,
        array   $aliased_classes,
        ?string $this_class,
        int     $php_major_version,
        int     $php_minor_version
    ): string
    {
        return 'string';
    }

    public function canBeFullyExpressedInPhp(int $php_major_version, int $php_minor_version): bool
    {
        return false;
    }

    public function getId(bool $nested = false): string
    {
        return $this->value . '::class';
    }

    public function getAssertionString(bool $exact = false): string
    {
        return $this->getKey();
    }

    /**
     * @param array<lowercase-string, string> $aliased_classes
     */
    public function toNamespacedString(
        ?string $namespace,
        array   $aliased_classes,
        ?string $this_class,
        bool    $use_phpdoc_format
    ): string
    {
        if ($use_phpdoc_format) {
            return 'string';
        }

        if ($this->value === 'static') {
            return 'static::class';
        }

        if ($this->value === $this_class) {
            return 'self::class';
        }

        if ($namespace && stripos($this->value, $namespace . '\\') === 0) {
            return preg_replace(
                    '/^' . preg_quote($namespace . '\\') . '/i',
                    '',
                    $this->value
                ) . '::class';
        }

        if (!$namespace && strpos($this->value, '\\') === false) {
            return $this->value . '::class';
        }

        if (isset($aliased_classes[strtolower($this->value)])) {
            return $aliased_classes[strtolower($this->value)] . '::class';
        }

        return '\\' . $this->value . '::class';
    }
}
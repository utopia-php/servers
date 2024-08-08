<?php

namespace Utopia\Servers;

abstract class Validator
{
    /**
     * Get Description
     *
     * Returns validator description
     *
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Is array
     *
     * Returns true if an array or false if not.
     *
     * @return bool
     */
    abstract public function isArray(): bool;

    /**
     * Is valid
     *
     * Returns true if valid or false if not.
     *
     * @param  mixed  $value
     * @return bool
     */
    abstract public function isValid($value): bool;

    /**
     * Get Type
     *
     * Returns validator type.
     *
     * @return string
     */
    abstract public function getType(): string;
}

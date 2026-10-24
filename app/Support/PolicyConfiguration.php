<?php

namespace App\Support;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;

/**
 * Reads only explicitly configured and validated policy values.
 */
final class PolicyConfiguration
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * An absent feature switch is disabled. Non-boolean values are rejected.
     */
    public function isEnabled(string $policy): bool
    {
        $value = $this->config->get("oyafind.policies.{$policy}");

        if ($value === null) {
            return false;
        }

        if (! is_bool($value)) {
            throw new InvalidArgumentException(
                "The [{$policy}] policy must be explicitly configured as a boolean."
            );
        }

        return $value;
    }

    /**
     * Return an explicitly configured value only if it is in the approved set.
     *
     * The caller supplies the approved set so this boundary cannot invent
     * product policy values. Missing values remain unavailable.
     *
     * @param  list<scalar>  $approvedValues
     */
    public function approvedValue(string $policy, array $approvedValues): mixed
    {
        $value = $this->config->get("oyafind.policies.{$policy}");

        if ($value === null || $value === '') {
            return null;
        }

        if (! in_array($value, $approvedValues, true)) {
            throw new InvalidArgumentException(
                "The [{$policy}] policy contains a value that has not been approved."
            );
        }

        return $value;
    }
}

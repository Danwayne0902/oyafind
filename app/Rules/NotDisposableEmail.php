<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects throw-away mailbox providers at registration.
 *
 * The bundled blocklist comes from the CC0 `disposable-email-domains`
 * project and is matched on the exact domain and on every parent domain, so
 * `inbox.mailinator.com` is rejected along with `mailinator.com`. Phone
 * numbers and already-invalid addresses pass through untouched: shape is the
 * job of the request's other rules, and the message should stay about one
 * problem at a time.
 */
final class NotDisposableEmail implements ValidationRule
{
    /** @var array<string, true>|null */
    private static ?array $blocked = null;

    private static ?string $signature = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $domain = $this->domainOf($value);
        if ($domain === null) {
            return;
        }

        if ($this->isBlocked($domain)) {
            $fail('Use a permanent email address. Disposable or temporary email domains are not accepted.');

            return;
        }

        $parts = explode('.', $domain);
        for ($index = 1; $index < count($parts) - 1; $index++) {
            $parent = implode('.', array_slice($parts, $index));

            if ($this->isBlocked($parent)) {
                $fail('Use a permanent email address. Disposable or temporary email domains are not accepted.');

                return;
            }
        }
    }

    private function domainOf(string $email): ?string
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return null;
        }

        $domain = strtolower(rtrim(substr($email, $at + 1), '.'));

        return $domain === '' ? null : $domain;
    }

    private function isBlocked(string $domain): bool
    {
        return isset($this->blocked()[$domain]);
    }

    /**
     * @return array<string, true>
     */
    private function blocked(): array
    {
        $path = resource_path('data/disposable-email-domains.txt');
        $extras = array_values(array_filter(
            array_map(
                static fn (mixed $domain): string => is_string($domain) ? strtolower(trim($domain)) : '',
                (array) config('oyafind.registration_blocked_email_domains', []),
            ),
            static fn (string $domain): bool => $domain !== '',
        ));

        $signature = (is_readable($path) ? (string) filemtime($path) : 'missing').'|'.implode(',', $extras);

        if (self::$blocked !== null && self::$signature === $signature) {
            return self::$blocked;
        }

        $domains = [];

        if (is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                $domains[strtolower($line)] = true;
            }
        }

        foreach ($extras as $extra) {
            $domains[$extra] = true;
        }

        self::$signature = $signature;

        return self::$blocked = $domains;
    }
}

<?php

declare(strict_types=1);

namespace CaravelPay\Laravel;

/**
 * The configured merchant credentials: the current pair plus replaced ones, as merchant id => secret.
 */
final class Credentials
{
    private const MERCHANT = '/^mch_[A-Za-z0-9]{4,64}$/D';
    private const SECRET = '/^[\x21-\x7e]{16,256}$/D';

    /** @var array<string, string> */
    private array $secrets = [];
    private string $current = '';

    /**
     * @param array<string, mixed> $config The "caravel" config array.
     */
    public static function fromConfig(array $config): self
    {
        $credentials = new self();
        $merchant = trim((string) ($config['merchant_id'] ?? ''));
        $secret = trim((string) ($config['secret'] ?? ''));
        if (preg_match(self::MERCHANT, $merchant) && preg_match(self::SECRET, $secret)) {
            $credentials->current = $merchant;
            $credentials->secrets[$merchant] = $secret;
        }
        $previous = $config['previous_credentials'] ?? '';
        $pairs = is_array($previous) ? $previous : explode(',', (string) $previous);
        foreach ($pairs as $key => $pair) {
            [$id, $value] = is_string($key) ? [$key, (string) $pair] : array_pad(explode(':', (string) $pair, 2), 2, '');
            $id = trim((string) $id);
            $value = trim((string) $value);
            if (preg_match(self::MERCHANT, $id) && preg_match(self::SECRET, $value) && !isset($credentials->secrets[$id])) {
                $credentials->secrets[$id] = $value;
            }
        }
        return $credentials;
    }

    public function current(): string
    {
        return $this->current;
    }

    public function isConfigured(): bool
    {
        return '' !== $this->current;
    }

    /** @return array<string, string> merchant id => secret (current first). */
    public function secrets(): array
    {
        return $this->secrets;
    }

    public function secretFor(string $merchantId): ?string
    {
        return $this->secrets[$merchantId] ?? null;
    }

    /** @return array<string, mixed> Debug output without secrets. */
    public function __debugInfo(): array
    {
        return ['current' => $this->current, 'merchants' => array_keys($this->secrets), 'secrets' => '[redacted]'];
    }
}

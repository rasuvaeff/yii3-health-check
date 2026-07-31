<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck;

/**
 * @api
 */
final readonly class HealthResult
{
    private const string NAME_PATTERN = '/^[a-z][a-z0-9_.-]*\z/';

    public string $name;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        string $name,
        public HealthStatus $status,
        public string $message = '',
        public array $data = [],
        public float $elapsedMs = 0.0,
    ) {
        $this->validateName($name);

        $this->name = $name;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function pass(string $name, string $message = '', array $data = []): self
    {
        return new self(name: $name, status: HealthStatus::Pass, message: $message, data: $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function warn(string $name, string $message = '', array $data = []): self
    {
        return new self(name: $name, status: HealthStatus::Warn, message: $message, data: $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fail(string $name, string $message = '', array $data = []): self
    {
        return new self(name: $name, status: HealthStatus::Fail, message: $message, data: $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function withData(array $data): self
    {
        return new self(
            name: $this->name,
            status: $this->status,
            message: $this->message,
            data: $data,
            elapsedMs: $this->elapsedMs,
        );
    }

    public function withElapsedMs(float $elapsedMs): self
    {
        return new self(
            name: $this->name,
            status: $this->status,
            message: $this->message,
            data: $this->data,
            elapsedMs: $elapsedMs,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'status' => $this->status->value,
            'message' => $this->message,
        ];

        if ($this->elapsedMs > 0.0) {
            $result['elapsedMs'] = $this->elapsedMs;
        }

        if ($this->data !== []) {
            $result['data'] = $this->data;
        }

        return $result;
    }

    private function validateName(string $name): void
    {
        if (!preg_match(self::NAME_PATTERN, $name)) {
            throw new Exception\InvalidCheckNameException(
                message: sprintf('Invalid check name "%s"', $name),
            );
        }
    }
}

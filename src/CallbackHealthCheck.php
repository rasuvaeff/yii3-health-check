<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck;

/**
 * @api
 */
final class CallbackHealthCheck implements HealthCheck
{
    private readonly string $checkName;

    /** @var callable(): HealthResult */
    private $callback;

    /**
     * @param callable(): HealthResult $check
     */
    public function __construct(
        string $name,
        callable $check,
    ) {
        $this->validateName($name);

        $this->checkName = $name;
        $this->callback = $check;
    }

    #[\Override]
    public function name(): string
    {
        return $this->checkName;
    }

    #[\Override]
    public function check(): HealthResult
    {
        return ($this->callback)();
    }

    private function validateName(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]*\z/', $name)) {
            throw new Exception\InvalidCheckNameException(
                message: sprintf('Invalid check name "%s"', $name),
            );
        }
    }
}

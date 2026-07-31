<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * @internal
 */
final readonly class FakeRequest implements ServerRequestInterface
{
    /**
     * @param array<string, mixed> $serverParams
     */
    public function __construct(
        private array $serverParams = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function getCookieParams(): array
    {
        return [];
    }

    /**
     * @param array<array-key, mixed> $cookies
     */
    #[\Override]
    public function withCookieParams(array $cookies): self
    {
        return clone $this;
    }

    /**
     * @return array<string, string|int|list<string>>
     */
    #[\Override]
    public function getQueryParams(): array
    {
        return [];
    }

    /**
     * @param array<array-key, mixed> $query
     */
    #[\Override]
    public function withQueryParams(array $query): self
    {
        return clone $this;
    }

    #[\Override]
    public function getUploadedFiles(): array
    {
        return [];
    }

    #[\Override]
    public function withUploadedFiles(array $uploadedFiles): self
    {
        return clone $this;
    }

    #[\Override]
    public function getParsedBody(): null
    {
        return null;
    }

    #[\Override]
    public function withParsedBody($data): self
    {
        return clone $this;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function getAttributes(): array
    {
        return [];
    }

    #[\Override]
    public function getAttribute(string $name, $default = null): null
    {
        return null;
    }

    #[\Override]
    public function withAttribute(string $name, $value): self
    {
        return clone $this;
    }

    #[\Override]
    public function withoutAttribute(string $name): self
    {
        return clone $this;
    }

    #[\Override]
    public function getRequestTarget(): string
    {
        return '/';
    }

    #[\Override]
    public function withRequestTarget(string $requestTarget): self
    {
        return clone $this;
    }

    #[\Override]
    public function getMethod(): string
    {
        return 'GET';
    }

    #[\Override]
    public function withMethod(string $method): self
    {
        return clone $this;
    }

    #[\Override]
    public function getUri(): UriInterface
    {
        throw new \LogicException('Not implemented');
    }

    #[\Override]
    public function withUri(UriInterface $uri, bool $preserveHost = false): self
    {
        return clone $this;
    }

    #[\Override]
    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    #[\Override]
    public function withProtocolVersion(string $version): self
    {
        return clone $this;
    }

    /**
     * @return array<string, list<string>>
     */
    #[\Override]
    public function getHeaders(): array
    {
        return [];
    }

    #[\Override]
    public function hasHeader(string $name): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function getHeader(string $name): array
    {
        return [];
    }

    #[\Override]
    public function getHeaderLine(string $name): string
    {
        return '';
    }

    #[\Override]
    public function withHeader(string $name, $value): self
    {
        return clone $this;
    }

    #[\Override]
    public function withAddedHeader(string $name, $value): self
    {
        return clone $this;
    }

    #[\Override]
    public function withoutHeader(string $name): self
    {
        return clone $this;
    }

    #[\Override]
    public function getBody(): StreamInterface
    {
        throw new \LogicException('Not implemented');
    }

    #[\Override]
    public function withBody(StreamInterface $body): self
    {
        return clone $this;
    }
}

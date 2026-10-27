<?php

declare(strict_types=1);

/**
 * A container that hands back the services a test gives it.
 */
class TestContainer
{
    /**
     * @param  array<string, object>  $services
     */
    public function __construct(private array $services = []) {}

    public function get(string $serviceClass): ?object
    {
        return $this->services[$serviceClass] ?? null;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function make(string $abstract, array $parameters = []): ?object
    {
        return $this->get($abstract);
    }

    public function resolve(string $serviceClass): ?object
    {
        return $this->get($serviceClass);
    }

    public function has(string $serviceClass): bool
    {
        return isset($this->services[$serviceClass]);
    }

    public function instance($abstract, $instance): void
    {
        $this->services[$abstract] = $instance;
    }
}

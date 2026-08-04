<?php

namespace Tests\Unit\Tenancy;

use App\Tenancy\Exceptions\InvalidHostException;
use App\Tenancy\HostNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HostNormalizerTest extends TestCase
{
    public function test_it_normalizes_case_and_a_trailing_dot(): void
    {
        $normalizer = new HostNormalizer;

        $this->assertSame('shop.counterpos.pk', $normalizer->normalize('Shop.CounterPOS.pk.'));
    }

    #[DataProvider('invalidHosts')]
    public function test_it_rejects_unsafe_hosts(string $host): void
    {
        $this->expectException(InvalidHostException::class);

        (new HostNormalizer)->normalize($host);
    }

    public static function invalidHosts(): array
    {
        return [
            'empty' => [''],
            'single label' => ['localhost'],
            'ip address' => ['127.0.0.1'],
            'port' => ['shop.counterpos.pk:8000'],
            'path' => ['shop.counterpos.pk/login'],
            'wildcard' => ['*.counterpos.pk'],
            'leading hyphen' => ['-shop.counterpos.pk'],
            'whitespace' => [' shop.counterpos.pk'],
        ];
    }
}

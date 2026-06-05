<?php
declare(strict_types=1);

namespace Etechflow\CanonicalHreflang\Test\Unit\Service;

use Etechflow\CanonicalHreflang\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private function configReturning(array $map): Config
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(
            static fn ($path) => $map[$path] ?? null
        );
        $scope->method('isSetFlag')->willReturnCallback(
            static fn ($path) => !empty($map[$path])
        );
        return new Config($scope);
    }

    public function testHreflangMappingParsesValidLines(): void
    {
        $c = $this->configReturning(['etechflow_canonical/hreflang/mapping' => "1:en-gb\n2:fr-fr"]);
        $this->assertSame([1 => 'en-gb', 2 => 'fr-fr'], $c->hreflangMapping());
    }

    public function testHreflangMappingSkipsJunkLines(): void
    {
        $c = $this->configReturning(['etechflow_canonical/hreflang/mapping' => "1:en-gb\nnonsense\n:missingid\n3:\nabc:de"]);
        $this->assertSame([1 => 'en-gb'], $c->hreflangMapping());
    }

    public function testHreflangMappingEmpty(): void
    {
        $this->assertSame([], $this->configReturning([])->hreflangMapping());
    }

    public function testXDefaultStoreNullWhenBlank(): void
    {
        $this->assertNull($this->configReturning(['etechflow_canonical/hreflang/x_default_store' => ''])->xDefaultStore());
        $this->assertSame(2, $this->configReturning(['etechflow_canonical/hreflang/x_default_store' => '2'])->xDefaultStore());
    }

    public function testCanonicalForTypeMapsFlags(): void
    {
        $c = $this->configReturning([
            'etechflow_canonical/canonical/product' => '1',
            'etechflow_canonical/canonical/category' => '0',
        ]);
        $this->assertTrue($c->canonicalForType('product'));
        $this->assertFalse($c->canonicalForType('category'));
        $this->assertFalse($c->canonicalForType('nonsense'));
    }
}

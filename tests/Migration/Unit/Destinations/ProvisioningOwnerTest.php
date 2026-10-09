<?php

namespace Utopia\Tests\Unit\Destinations;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Utopia\Migration\Destinations\Appwrite\ProvisioningOwner;

final class ProvisioningOwnerTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function emptyIdentifiers(): array
    {
        return [
            'migration identifier' => ['', 'attempt'],
            'attempt identifier' => ['migration', ''],
        ];
    }

    #[DataProvider('emptyIdentifiers')]
    public function testIdentifiersMustBeNonEmpty(string $migrationId, string $attemptId): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ProvisioningOwner($migrationId, $attemptId);
    }

    public function testEqualityRequiresTheExactPair(): void
    {
        $owner = new ProvisioningOwner('migration', 'attempt');

        $this->assertTrue($owner->equals(new ProvisioningOwner('migration', 'attempt')));
        $this->assertFalse($owner->equals(new ProvisioningOwner('migration-other', 'attempt')));
        $this->assertFalse($owner->equals(new ProvisioningOwner('migration', 'attempt-other')));
    }
}

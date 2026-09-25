<?php

namespace Utopia\Migration\Destinations\Appwrite;

final readonly class ProvisioningOwner
{
    public function __construct(
        public string $migrationId,
        public string $attemptId,
    ) {
        if ($migrationId === '') {
            throw new \InvalidArgumentException('Migration identifier must not be empty');
        }
        if ($attemptId === '') {
            throw new \InvalidArgumentException('Migration attempt identifier must not be empty');
        }
    }

    public function equals(self $owner): bool
    {
        return $this->migrationId === $owner->migrationId
            && $this->attemptId === $owner->attemptId;
    }
}

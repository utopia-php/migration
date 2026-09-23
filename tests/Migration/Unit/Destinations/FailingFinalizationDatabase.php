<?php

namespace Utopia\Tests\Unit\Destinations;

use Override;
use Utopia\Database\Database as UtopiaDatabase;
use Utopia\Database\Document as UtopiaDocument;
use Utopia\Database\Exception as DatabaseException;

/**
 * A project database whose finalization steps can be made to fail: the
 * `ready` write of chosen databases.
 */
final class FailingFinalizationDatabase extends UtopiaDatabase
{
    /** @var list<string> */
    public array $failReadyWrites = [];

    #[Override]
    public function updateDocument(string $collection, string $id, UtopiaDocument $document): UtopiaDocument
    {
        if (
            $collection === 'databases'
            && $document->getAttribute('status') === 'ready'
            && \in_array($id, $this->failReadyWrites, true)
        ) {
            throw new DatabaseException('ready status unavailable');
        }

        return parent::updateDocument($collection, $id, $document);
    }
}

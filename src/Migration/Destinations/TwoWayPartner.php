<?php

namespace Utopia\Migration\Destinations;

use Utopia\Database\Document as UtopiaDocument;

final readonly class TwoWayPartner
{
    public function __construct(
        public UtopiaDocument $relatedTable,
        public string $partnerKey,
        public string $partnerMetaId,
    ) {
    }
}

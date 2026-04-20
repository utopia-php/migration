<?php

namespace Utopia\Migration\Destinations;

/**
 * Behavior when a destination row with an existing ID is encountered.
 */
enum OnDuplicate: string
{
    case Fail = 'fail';
    case Skip = 'skip';
    case Upsert = 'upsert';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return \array_map(fn (self $case) => $case->value, self::cases());
    }
}

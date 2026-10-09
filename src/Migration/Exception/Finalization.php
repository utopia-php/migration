<?php

namespace Utopia\Migration\Exception;

use Utopia\Migration\Exception;

/**
 * Thrown by Target::success() once every finalization step has been attempted and at
 * least one of them failed. Each failure is also recorded on the target with addError(),
 * so a caller that persists getErrors() already holds the per-resource detail.
 */
final class Finalization extends Exception
{
    /**
     * @param list<Exception> $failures
     */
    public function __construct(public readonly array $failures)
    {
        if ($failures === []) {
            throw new \InvalidArgumentException('Finalization requires at least one failure');
        }

        $subjects = \array_map(
            static fn (Exception $failure): string => \trim($failure->getResourceName().' '.$failure->getResourceId()),
            $failures,
        );

        parent::__construct(
            resourceName: '',
            resourceGroup: '',
            message: 'Finalization failed for '.\implode(', ', $subjects),
            code: Exception::CODE_INTERNAL,
            previous: $failures[0],
        );
    }
}

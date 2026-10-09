<?php

namespace Utopia\Migration\Exception;

/**
 * Thrown from a transfer's progress callback to stop the transfer.
 *
 * Sources record any other failure as a resource error and move on to the next resource
 * type. They rethrow this one, so nothing further is exported or imported, and it leaves
 * Transfer::run() and Transfer::runWithResourceSelector() unchanged. Extend it to give
 * the reason its own type.
 */
class Aborted extends \RuntimeException
{
}

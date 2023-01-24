<?php

namespace Utopia\Transfer;

class Progress
{
    function __construct(
        private string $resourceType,
        private int $timestamp, 
        private int $total = 0, 
        private int $current = 0, 
        private int $failed = 0, 
        private int $skipped = 0
        ){}
}
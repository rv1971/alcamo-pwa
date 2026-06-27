<?php

namespace alcamo\pwa;

/// Database record having column `modified`
trait ModifiedTrait
{
    public $modified;

    private function initModified(): void
    {
        $this->modified = new \DateTimeImmutable($this->modified);
    }
}

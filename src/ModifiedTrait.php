<?php

namespace alcamo\pwa;

/// Database record having column `modified`
trait ModifiedTrait
{
    private $modified;

    public function getModified(): \DateTimeImmutable
    {
        return $this->modified;
    }

    private function initModified(): void
    {
        $this->modified = new \DateTimeImmutable($this->modified);
    }
}

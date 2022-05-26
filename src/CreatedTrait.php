<?php

namespace alcamo\pwa;

/// Database record having column `created`
trait CreatedTrait
{
    private $created;

    public function getCreated(): \DateTimeImmutable
    {
        return $this->created;
    }

    private function initCreated(): void
    {
        $this->created = new \DateTimeImmutable($this->created);
    }
}

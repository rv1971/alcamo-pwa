<?php

namespace alcamo\pwa;

use PHPUnit\Framework\TestCase;

class AccountAccessorTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

    public function testBasics()
    {
        $accessor = new AccountAccessor(static::DSN);

        $accessor->createTable();

        $accessor->add('alice');

        $accessor->add('bob');

        $accounts = [];

        foreach ($accessor as $record) {
            $accounts[$record->getUsername()] = [
                $record->getCreated(),
                $record->getModified(),
            ];
        }

        $this->assertSame(
            [ 'alice', 'bob' ],
            array_keys($accounts)
        );

        $this->assertGreaterThanOrEqual(
            (new \DateTime())->getTimestamp(),
            $accounts['alice'][0]->getTimestamp()
        );

        $this->assertLessThanOrEqual(
            (new \DateTime())->add(new \DateInterval('PT5S'))->getTimestamp(),
            $accounts['alice'][0]->getTimestamp()
        );

        $this->assertGreaterThanOrEqual(
            (new \DateTime())->getTimestamp(),
            $accounts['alice'][1]->getTimestamp()
        );

        $this->assertLessThanOrEqual(
            (new \DateTime())->add(new \DateInterval('PT5S'))->getTimestamp(),
            $accounts['alice'][1]->getTimestamp()
        );
    }
}

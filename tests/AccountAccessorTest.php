<?php

namespace alcamo\pwa;

use alcamo\exception\DataNotFound;
use PHPUnit\Framework\TestCase;

class AccountAccessorTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

    private $accessor_;

    public function setUp(): void
    {
        $this->accessor_ = AccountAccessor::newFromParams(
            [ 'connection' => static::DSN ]
        );

        $this->accessor_->createTable();
    }

    public function testAdd()
    {
        $this->accessor_->add('alice');

        $this->accessor_->add('bob');

        $this->assertSame(
            'alice',
            $this->accessor_->get('alice')->getUsername()
        );

        $this->assertNull($this->accessor_->get('ALICE'));

        $accounts = [];

        foreach ($this->accessor_ as $record) {
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

        $this->accessor_->remove('bob');

        $this->assertNull($this->accessor_->get('bob'));

        $this->assertSame(1, count($this->accessor_));
    }

    public function testRemoveException()
    {
        $this->expectException(DataNotFound::class);

        $this->expectExceptionMessage(
            'Data not found in table "account" for key "qux"'
        );

        $this->accessor_->remove('qux');
    }
}

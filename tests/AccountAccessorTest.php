<?php

namespace alcamo\pwa;

use alcamo\dao\DbAccessor;
use alcamo\exception\DataNotFound;
use PHPUnit\Framework\TestCase;

class AccountAccessorTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

    private $accessor_;

    public function setUp(): void
    {
        $dbAccessor = DbAccessor::newFromProps([ 'dsn' => static::DSN ]);

        (new Installer($dbAccessor))->install();

        $this->accessor_ = new AccountAccessor($dbAccessor);
    }

    public function testAdd()
    {
        $this->accessor_->add('alice');

        $this->accessor_->add('bob');

        $this->assertSame(
            'alice',
            $this->accessor_->get('alice')->username
        );

        $this->assertNull($this->accessor_->get('ALICE'));

        $accounts = [];

        foreach ($this->accessor_ as $record) {
            $accounts[$record->username] = [
                $record->created,
                $record->modified,
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

    public function testRemove()
    {
        $this->accessor_->add('alice');

        $this->accessor_->add('bob');

        $this->assertSame(2, count($this->accessor_));

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

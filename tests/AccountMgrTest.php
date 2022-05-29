<?php

namespace alcamo\pwa;

use alcamo\exception\DataNotFound;
use PHPUnit\Framework\TestCase;
use Symfony\Polyfill\Uuid\Uuid;

class AccountMgrTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

    private $mgr_;

    public function setUp(): void
    {
        $this->mgr_ = AccountMgr::newFromParams(
            [
                'connection' => static::DSN,
                'passwdKey' => random_bytes(8),
                'maxOpenInstAge' => 'PT4S'
            ]
        );

        $this->mgr_->createTables();
    }

    public function testBasics()
    {
        $obfuscated1 = $this->mgr_->addOpenInst('bob');

        $obfuscated2 = $this->mgr_->addOpenInst('alice');

        $obfuscated3 = $this->mgr_->addOpenInst('charles');

        $obfuscated4 = $this->mgr_->addOpenInst('alice');

        $accounts = [];

        foreach ($this->mgr_->getAccountAccessor() as $record) {
            $accounts[$record->getUsername()] = [
                $record->getCreated(),
                $record->getModified(),
            ];
        }

        $this->assertSame(
            [ 'alice', 'bob', 'charles' ],
            array_keys($accounts)
        );

        $this->assertSame(4, count($this->mgr_->getOpenInstAccessor()));

        $instId1 = Uuid::uuid_create();

        $userAgent1 = 'Mozilla/5.0 (BobDevice 1.0)';

        $appVersion1 = '1.0.0';

        // test successful add
        $this->mgr_->addOrModifyInst(
            $instId1,
            'bob',
            $obfuscated1,
            $userAgent1,
            $appVersion1
        );

        $this->assertSame(3, count($this->mgr_->getOpenInstAccessor()));

        $inst1 = $this->mgr_->getInstAccessor()
            ->get($instId1, 'bob', $obfuscated1);

        $this->assertSame($userAgent1, $inst1->getUserAgent());

        $this->assertSame($appVersion1, $inst1->getAppVersion());

        $this->assertSame(0, $inst1->getUpdateCount());

        // test successful modify
        $userAgent1 = 'Mozilla/5.0 (BobDevice 2.0)';

        $appVersion1 = '1.1.0';

        // test successful add
        $this->mgr_->addOrModifyInst(
            $instId1,
            'bob',
            $obfuscated1,
            $userAgent1,
            $appVersion1
        );

        $this->assertSame(3, count($this->mgr_->getOpenInstAccessor()));

        $this->assertSame(1, count($this->mgr_->getInstAccessor()));

        $inst1 = $this->mgr_->getInstAccessor()
            ->get($instId1, 'bob', $obfuscated1);

        $this->assertSame($userAgent1, $inst1->getUserAgent());

        $this->assertSame($appVersion1, $inst1->getAppVersion());

        $this->assertSame(1, $inst1->getUpdateCount());
    }


    public function testAddException()
    {
        $this->expectException(DataNotFound::class);

        $this->expectExceptionMessage(
            'Data not found for key ["uuid", "charles", "12345"]'
        );

        $this->mgr_->addOrModifyInst(
            'uuid',
            'charles',
            '12345',
            'No real device',
            '1.0.0'
        );
    }
}

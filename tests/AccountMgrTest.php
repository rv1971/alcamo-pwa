<?php

namespace alcamo\pwa;

use alcamo\exception\DataNotFound;
use PHPUnit\Framework\TestCase;
use Symfony\Polyfill\Uuid\Uuid;

class AccountMgrTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

    private $mgr_;

    public const TEST_DATA = [
        [
            'username' => 'bob',
            'userAgent' => 'BlackBerry9900/5.1.0.692',
            'appVersion' => '0.42.1',
            'launcher' => 'Homescreen'
        ],
        [
            'username' => 'alice',
            'userAgent' => 'Mozilla/5.0',
            'appVersion' => '1.2.3'
        ],
        [
            'username' => 'charles',
            'userAgent' => 'BlackBerry9800/5.0.0.690',
            'appVersion' => '0.43.0'
        ],
        [
            'username' => 'alice',
            'userAgent' => 'Mozilla/5.0',
            'appVersion' => '4.5.6'
        ]
    ];

    private $testData_;

    public function setUp(): void
    {
        $this->mgr_ = AccountMgr::newFromConf(
            [
                'db' => [ 'connection' => static::DSN ],
                'passwdKey' => random_bytes(8),
                'maxOpenInstAge' => 'PT4S',
                'maxPrevInstAge' => 'PT2S'
            ]
        );

        $this->mgr_->createTables();

        foreach (static::TEST_DATA as $i => $data) {
            $data = (object)$data;

            $data->obfuscated = $this->mgr_->addOpenInst($data->username);

            $data->instId = Uuid::uuid_create();

            $this->testData_[$i] = $data;
        }
    }

    /**
     * @dataProvider isSimilarUserAgentProvider
     */
    public function testIsSimilarUserAgent(
        $userAgent1,
        $userAgent2,
        $expectedResult
    ) {
        $this->assertSame(
            $expectedResult,
            AccountMgr::isSimilarUserAgent($userAgent1, $userAgent2)
        );
    }

    public function isSimilarUserAgentProvider()
    {
        return [
            'no-parenthesis-false' => [
                'BlackBerry9900/5.1.0.692',
                'BlackBerry9800/5.0.0.690',
                false
            ],
            'no-parenthesis-false' => [
                'BlackBerry9900/5.1.0.692',
                'BlackBerry9900/5.1.0.692',
                true
            ],
            'parentheses-equal' => [
                'Wget/1.20.3 (linux-gnu) foo/bar',
                'Wget/1.20.3 (linux-gnu) bar/baz',
                true
            ],
            'one-semicolon-true' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 14_8_1 like Mac OS X) baz',
                'Mozilla/5.0 (iPhone; CPU iPhone OS 14_8_1 like Mac OS X) qux',
                true
            ],
            'two-semicolons-true' => [
                'Mozilla/5.0 (Linux; Android 12; SAMSUNG SM-A415F) '
                . 'AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/18.0 '
                . 'Chrome/99.0.4844.88 Mobile Safari/537.36',
                'Mozilla/5.0 (Linux; Android 12; SM-A415F) '
                . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                . 'Chrome/104.0.0.0 Mobile Safari/537.36',
                true
            ],
            'two-semicolons-false' => [
                'Mozilla/5.0 (Linux; Android 12; SAMSUNG SM-A415F) AppleWebKit/537.36',
                'Mozilla/5.0 (Linux; Android 8.0.0; WAS-LX1A) AppleWebKit/537.36',
                false
            ]
        ];
    }

    public function testAddOpenInst()
    {
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
    }

    public function testAddOrModifyInst()
    {
        // test successful add
        foreach ($this->testData_ as $i => $data) {
            $this->mgr_->addOrModifyInst(
                $data->instId,
                $data->username,
                $data->obfuscated,
                $data->userAgent,
                $data->appVersion,
                $data->launcher ?? null
            );

            $this->assertSame(
                3 - $i,
                count($this->mgr_->getOpenInstAccessor())
            );

            $this->assertSame(
                $i + 1,
                count($this->mgr_->getInstAccessor())
            );

            $inst = $this->mgr_->getInstAccessor()->get(
                $data->instId,
                $data->username,
                $data->obfuscated
            );

            $this->assertSame($data->userAgent, $inst->getUserAgent());

            $this->assertSame($data->appVersion, $inst->getAppVersion());

            if (isset($data->launcher)) {
                $this->assertSame($data->launcher, $inst->getLauncher());
            }

            $this->assertSame(0, $inst->getUpdateCount());
        }

        // test successful modify
        $userAgent1 = 'Mozilla/5.0 (Linux; Android 12; SAMSUNG SM-A415F) AppleWebKit/537.36';

        $appVersion = '1.1.0';

        $launcher = 'SpecialLauncher';

        $this->mgr_->addOrModifyInst(
            $this->testData_[0]->instId,
            $this->testData_[0]->username,
            $this->testData_[0]->obfuscated,
            $userAgent1,
            $appVersion,
            $launcher
        );

        $this->assertSame(0, count($this->mgr_->getOpenInstAccessor()));

        $this->assertSame(4, count($this->mgr_->getInstAccessor()));

        $inst = $this->mgr_->getInstAccessor()
            ->get($this->testData_[0]->instId);

        $this->assertSame($userAgent1, $inst->getUserAgent());

        $this->assertSame($appVersion, $inst->getAppVersion());

        $this->assertSame($launcher, $inst->getLauncher());

        $this->assertSame(1, $inst->getUpdateCount());

        // test successful second instance on same device

        $secondInstId = Uuid::uuid_create();

        $userAgent2 = 'Mozilla/5.0 (Linux; Android 12; SM-A415F) AppleWebKit/537.36';

        $this->mgr_->addOrModifyInst(
            $secondInstId,
            $this->testData_[0]->username,
            $this->testData_[0]->obfuscated,
            $userAgent2,
            $appVersion
        );

        $this->assertSame(5, count($this->mgr_->getInstAccessor()));

        $secondInst = $this->mgr_->getInstAccessor()->get($secondInstId);

        $this->assertInstanceOf(InstRecord::class, $secondInst);

        // test that third instance cannot be added

        $thirdInstId = Uuid::uuid_create();

        $this->expectException(DataNotFound::class);
        $this->expectExceptionMessage($thirdInstId);

        $this->mgr_->addOrModifyInst(
            $thirdInstId,
            $this->testData_[0]->username,
            $this->testData_[0]->obfuscated,
            $userAgent1,
            $appVersion
        );
    }

    public function testSecondUserAgentCheck()
    {
        foreach ($this->testData_ as $i => $data) {
            $this->mgr_->addOrModifyInst(
                $data->instId,
                $data->username,
                $data->obfuscated,
                $data->userAgent,
                $data->appVersion
            );
        }

        $secondInstId = Uuid::uuid_create();

        $this->expectException(DataNotFound::class);
        $this->expectExceptionMessage($secondInstId);

        $this->mgr_->addOrModifyInst(
            $secondInstId,
            $this->testData_[0]->username,
            $this->testData_[0]->obfuscated,
            $this->testData_[2]->userAgent,
            $this->testData_[0]->appVersion
        );
    }

    public function testSecondInstPasswdCheck()
    {
        foreach ($this->testData_ as $i => $data) {
            $this->mgr_->addOrModifyInst(
                $data->instId,
                $data->username,
                $data->obfuscated,
                $data->userAgent,
                $data->appVersion
            );
        }

        $secondInstId = Uuid::uuid_create();

        $this->expectException(DataNotFound::class);
        $this->expectExceptionMessage($secondInstId);

        $this->mgr_->addOrModifyInst(
            $secondInstId,
            $this->testData_[0]->username,
            $this->testData_[1]->obfuscated,
            $this->testData_[0]->userAgent,
            $this->testData_[0]->appVersion
        );
    }

    public function testSecondInstExpiryCheck()
    {
        foreach ($this->testData_ as $i => $data) {
            $this->mgr_->addOrModifyInst(
                $data->instId,
                $data->username,
                $data->obfuscated,
                $data->userAgent,
                $data->appVersion
            );
        }

        $secondInstId = Uuid::uuid_create();

        sleep(3);

        $this->expectException(DataNotFound::class);
        $this->expectExceptionMessage($secondInstId);

        $this->mgr_->addOrModifyInst(
            $secondInstId,
            $this->testData_[0]->username,
            $this->testData_[0]->obfuscated,
            $this->testData_[0]->userAgent,
            $this->testData_[0]->appVersion
        );
    }

    public function testRemove()
    {
        foreach ($this->testData_ as $i => $data) {
            $this->mgr_->addOrModifyInst(
                $data->instId,
                $data->username,
                $data->obfuscated,
                $data->userAgent,
                $data->appVersion
            );
        }

        $this->mgr_->removeInst($this->testData_[1]->instId);

        $this->assertNull(
            $this->mgr_->getInstAccessor()->get($this->testData_[1]->instId)
        );

        $this->assertSame(3, count($this->mgr_->getInstAccessor()));

        $this->assertSame(3, count($this->mgr_->getAccountAccessor()));

        $this->assertSame(
            'alice',
            $this->mgr_->getAccountAccessor()->get('alice')->getUsername()
        );

        $this->mgr_->removeInst($this->testData_[3]->instId);

        $this->assertSame(2, count($this->mgr_->getInstAccessor()));

        $this->assertSame(2, count($this->mgr_->getAccountAccessor()));

        $this->assertNull($this->mgr_->getAccountAccessor()->get('alice'));
    }

    public function testRemove2()
    {
        foreach ($this->testData_ as $i => $data) {
            $this->mgr_->addOrModifyInst(
                $data->instId,
                $data->username,
                $data->obfuscated,
                $data->userAgent,
                $data->appVersion
            );
        }

        $this->mgr_->getOpenInstAccessor()->add('bob');

        $this->mgr_->removeInst($this->testData_[0]->instId);

        $this->assertSame(3, count($this->mgr_->getInstAccessor()));

        // bob is still there because he has an open ionstallation
        $this->assertSame(
            'bob',
            $this->mgr_->getAccountAccessor()->get('bob')->getUsername()
        );
    }

    public function testAddOrModifyInstException()
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

<?php

namespace alcamo\pwa;

use alcamo\exception\DataNotFound;
use PHPUnit\Framework\TestCase;
use Symfony\Polyfill\Uuid\Uuid;

class InstAccessorTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

    public const TEST_DATA = [
        [
            'username' => 'bob',
            'userAgent' => 'BlackBerry9900/5.1.0.692',
            'appVersion' => '0.42.1'
        ],
        [
            'username' => 'alice',
            'userAgent' => 'Mozilla/5.0',
            'appVersion' => '1.2.3'
        ],
        [
            'username' => 'bob',
            'userAgent' => 'BlackBerry9800/5.0.0.690',
            'appVersion' => '0.43.0'
        ]
    ];

    private $accessor_;

    private $testData_;

    public function setUp(): void
    {
        $pdo = new \PDO(static::DSN);

        $params = [
            'db' => [
                'connection' => $pdo,
                'tablePrefix' => 'bar_',
            ],
            'passwdKey' => random_bytes(8)
        ];

        $pdo->query('PRAGMA foreign_keys = ON');

        $this->accessor_ = InstAccessor::newFromParams($params);

        $accountAccessor_ = AccountAccessor::newFromParams($params);

        $accountAccessor_->createTable();

        $this->accessor_->createTable();

        foreach (static::TEST_DATA as $i => $data) {
            $data = (object)$data;

            if (!$accountAccessor_->get($data->username)) {
                $accountAccessor_->add($data->username);
            }

            $data->instId = Uuid::uuid_create();

            $data->passwd = $this->accessor_->getPasswdTransformer()
                ->createPasswd();

            $data->obfuscated = $this->accessor_->getPasswdTransformer()
                ->obfuscate($data->passwd);

            $data->passwdHash = $this->accessor_->getPasswdTransformer()
                ->createHash($data->passwd);

            $this->accessor_->add(
                $data->instId,
                $data->username,
                $data->passwdHash,
                $data->userAgent,
                $data->appVersion
            );

            $this->testData_[$i] = $data;
        }
    }

    public function testGet()
    {
        foreach ($this->testData_ as $data) {
            $record = $this->accessor_->get($data->instId);

            $this->assertSame($data->instId, $record->getInstId());

            $this->assertSame($data->username, $record->getUsername());

            $this->assertSame($data->passwdHash, $record->getPasswdHash());

            $this->assertSame($data->userAgent, $record->getUserAgent());

            $this->assertSame($data->appVersion, $record->getAppVersion());

            $this->assertSame(0, $record->getUpdateCount());
        }

        $this->assertNull($this->accessor_->get('foo'));
    }

    public function testGetWithPasswd()
    {
        $this->assertSame(
            $this->testData_[1]->instId,
            $this->accessor_
                ->get(
                    $this->testData_[1]->instId,
                    $this->testData_[1]->username,
                    $this->testData_[1]->obfuscated
                )
                ->getInstId()
        );
    }

    public function testGetException1()
    {
        $this->expectException(DataNotFound::class);

        $this->expectExceptionMessage(
            'Data not found in table "bar_inst" for key ["'
        );

        $this->accessor_->get($this->testData_[1]->instId, 'ALICE');
    }

    public function testGetException2()
    {
        $this->expectException(DataNotFound::class);

        $this->expectExceptionMessage(
            'Data not found in table "bar_inst" for key ["'
        );

        $this->accessor_->get($this->testData_[1]->instId, 'alice', 'qux');
    }

    public function testGetUserInsts()
    {
        $insts = [];

        foreach ($this->accessor_->getUserInsts('bob') as $record) {
            $insts[$record->getInstId()] = $record;
        }

        $expectedInsts = [
            $this->testData_[0]->instId,
            $this->testData_[2]->instId,
        ];

        sort($expectedInsts);

        $this->assertSame($expectedInsts, array_keys($insts));
    }

    public function testModify()
    {
        $userAgent = 'BlackBerry9800/5.0.0.691';

        $appVersion = '0.43.2';

        $this->accessor_->modify(
            $this->testData_[2]->instId,
            $userAgent,
            $appVersion
        );

        $inst = $this->accessor_->get($this->testData_[2]->instId);

        $this->assertSame(1, $inst->getUpdateCount());

        $this->assertSame($userAgent, $inst->getUserAgent());

        $this->assertSame($appVersion, $inst->getAppVersion());
    }

    public function testRemove()
    {
        $this->accessor_->remove($this->testData_[0]->instId);

        $this->assertNull(
            $this->accessor_->get($this->testData_[0]->instId)
        );

        $this->assertSame(2, count($this->accessor_));
    }

    public function testRemoveException()
    {
        $this->expectException(DataNotFound::class);

        $this->expectExceptionMessage(
            'Data not found in table "bar_inst" for key "baz"'
        );

        $this->accessor_->remove('baz');
    }
}

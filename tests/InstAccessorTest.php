<?php

namespace alcamo\pwa;

use alcamo\dao\DbAccessor;
use alcamo\exception\DataNotFound;
use alcamo\time\Duration;
use PHPUnit\Framework\TestCase;
use Symfony\Polyfill\Uuid\Uuid;

class InstAccessorTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

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
            'username' => 'bob',
            'userAgent' => 'BlackBerry9800/5.0.0.690',
            'appVersion' => '0.43.0'
        ]
    ];

    private $accessor_;

    private $testData_;

    public function setUp(): void
    {
        $dbAccessor = DbAccessor::newFromProps(
            [ 'dsn' => static::DSN, 'namePrefix' => 'bar_' ]
        );

        $dbAccessor->executeScript('PRAGMA foreign_keys = ON');

        (new Installer($dbAccessor))->install();

        $this->accessor_ = new InstAccessor(
            $dbAccessor,
            new PasswdTransformer(random_bytes(8)),
            new Duration('PT2S')
        );

        $accountAccessor_ = new AccountAccessor($dbAccessor);

        foreach (static::TEST_DATA as $i => $data) {
            if ($i) {
                // ensure defined chronological order
                sleep(1);
            }

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
                $data->appVersion,
                $data->launcher ?? null
            );

            $this->testData_[$i] = $data;
        }
    }

    public function testGet()
    {
        foreach ($this->testData_ as $data) {
            $record = $this->accessor_->get($data->instId);

            $this->assertSame($data->instId, $record->inst_id);

            $this->assertSame($data->username, $record->username);

            $this->assertSame($data->passwdHash, $record->passwd_hash);

            $this->assertSame($data->userAgent, $record->user_agent);

            $this->assertSame($data->appVersion, $record->app_version);

            if (isset($data->launcher)) {
                $this->assertSame($data->launcher, $record->launcher);
            }

            $this->assertSame(0, $record->update_count);
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
                ->inst_id
        );
    }

    public function testGetException1()
    {
        $this->expectException(DataNotFound::class);

        $this->expectExceptionMessage(
            'Data not found in table "inst" for key ["'
        );

        $this->accessor_->get($this->testData_[1]->instId, 'ALICE');
    }

    public function testGetException2()
    {
        $this->expectException(DataNotFound::class);

        $this->expectExceptionMessage(
            'Data not found in table "inst" for key ["'
        );

        $this->accessor_->get($this->testData_[1]->instId, 'alice', 'qux');
    }

    public function testGetUserInsts()
    {
        $insts = [];

        foreach ($this->accessor_->getUserInsts('bob') as $record) {
            $insts[$record->inst_id] = $record;
        }

        $expectedInsts = [
            $this->testData_[0]->instId,
            $this->testData_[2]->instId
        ];

        $this->assertSame($expectedInsts, array_keys($insts));
    }

    public function testGetUserUserAgentInsts()
    {
        $insts = [];

        foreach (
            $this->accessor_->getUserUserAgentInsts(
                'bob',
                'BlackBerry9800/5.0.0.690'
            ) as $record
        ) {
            $insts[$record->inst_id] = $record;
        }

        $expectedInsts = [
            $this->testData_[2]->instId
        ];

        $this->assertSame($expectedInsts, array_keys($insts));
    }

    public function testModify()
    {
        $userAgent = 'BlackBerry9800/5.0.0.693';

        $launcher = 'TestLauncher';

        $this->accessor_
            ->modify($this->testData_[2]->instId, $userAgent, $launcher);

        $inst = $this->accessor_->get($this->testData_[2]->instId);

        $this->assertSame($userAgent, $inst->user_agent);

        $this->assertSame($launcher, $inst->launcher);
    }

    public function testUpdateInst()
    {
        $userAgent = 'BlackBerry9800/5.0.0.691';

        $appVersion = '0.43.2';

        $launcher = 'TestLauncher2';

        $this->accessor_->updateInst(
            $this->testData_[2]->instId,
            $userAgent,
            $appVersion,
            $launcher
        );

        $inst = $this->accessor_->get($this->testData_[2]->instId);

        $this->assertSame(1, $inst->update_count);

        $this->assertSame($userAgent, $inst->user_agent);

        $this->assertSame($appVersion, $inst->app_version);

        $this->assertSame($launcher, $inst->launcher);
    }

    public function testReplace()
    {
        $newInstId = 'feebaf';

        $testData = $this->testData_[2];

        $this->assertSame(
            $testData->instId,
            $this->accessor_->get(
                $testData->instId,
                'bob',
                $testData->obfuscated
            )->inst_id
        );

        $this->assertNull(
            $this->accessor_->get($newInstId, 'bob', $testData->obfuscated)
        );

        sleep(2);

        $this->assertSame(
            $newInstId,
            $this->accessor_->get(
                $newInstId,
                'bob',
                $testData->obfuscated
            )->inst_id
        );
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
            'Data not found in table "inst" for key "baz"'
        );

        $this->accessor_->remove('baz');
    }
}

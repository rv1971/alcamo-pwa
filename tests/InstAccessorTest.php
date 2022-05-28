<?php

namespace alcamo\pwa;

use alcamo\exception\DataNotFound;
use PHPUnit\Framework\TestCase;
use Symfony\Polyfill\Uuid\Uuid;

class InstAccessorTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

    private $accessor_;

    public function setUp(): void
    {
        $this->accessor_ = InstAccessor::newFromParams(
            [
                'connection' => static::DSN,
                'tablePrefix' => 'foo_',
                'passwdKey' => random_bytes(8)
            ]
        );

        $this->accessor_->createTable();
    }

    public function testBasics()
    {
        $instId1 = Uuid::uuid_create();

        $username1 = 'bob';

        $passwd1 = $this->accessor_->getPasswdTransformer()->createPasswd();

        $obfuscated1 =
            $this->accessor_->getPasswdTransformer()->obfuscate($passwd1);

        $passwdHash1 =
            $this->accessor_->getPasswdTransformer()->createHash($passwd1);

        $userAgent1 = 'BlackBerry9900/5.1.0.692';

        $appVersion1 = '0.42.1';

        $this->accessor_->add(
            $instId1,
            $username1,
            $passwdHash1,
            $userAgent1,
            $appVersion1
        );

        $instId2 = Uuid::uuid_create();

        $username2 = 'charles';

        $passwd2 = $this->accessor_->getPasswdTransformer()->createPasswd();

        $obfuscated2 =
            $this->accessor_->getPasswdTransformer()->obfuscate($passwd2);

        $passwdHash2 =
            $this->accessor_->getPasswdTransformer()->createHash($passwd2);

        $userAgent2 = 'Mozilla/5.0';

        $appVersion2 = '0.1.2';

        $this->accessor_->add(
            $instId2,
            $username2,
            $passwdHash2,
            $userAgent2,
            $appVersion2
        );

        $this->assertNull(
            $this->accessor_->get(
                Uuid::uuid_create(),
                $username1,
                $obfuscated1
            )
        );

        $this->assertNull(
            $this->accessor_->get($instId1, $username2, $obfuscated1)
        );

        $this->assertNull(
            $this->accessor_->get($instId1, $username1, $obfuscated2)
        );

        $inst1 = $this->accessor_->get($instId1, $username1, $obfuscated1);

        $this->assertSame($instId1, $inst1->getInstId());

        $this->assertSame(substr($instId1, 0, 6), $inst1->getShortInstId());

        $this->assertSame($username1, $inst1->getUsername());

        $this->assertSame($passwdHash1, $inst1->getPasswdHash());

        $this->assertSame($userAgent1, $inst1->getUserAgent());

        $this->assertSame($appVersion1, $inst1->getAppVersion());

        $this->assertSame(0, $inst1->getUpdateCount());

        $inst2 = $this->accessor_->get($instId2, $username2, $obfuscated2);

        $this->assertSame($instId2, $inst2->getInstId());

        $this->assertSame(substr($instId2, 0, 6), $inst2->getShortInstId());

        $this->assertSame($username2, $inst2->getUsername());

        $this->assertSame($passwdHash2, $inst2->getPasswdHash());

        $this->assertSame($userAgent2, $inst2->getUserAgent());

        $this->assertSame($appVersion2, $inst2->getAppVersion());

        $userAgent3 = 'BlackBerry9800/5.0.0.690';

        $appVersion3 = '0.43.0';

        $this->accessor_->modify($instId1, $userAgent3, $appVersion3);

        $inst1 = $this->accessor_->get($instId1, $username1, $obfuscated1);

        $this->assertSame(1, $inst1->getUpdateCount());

        $this->assertSame($userAgent3, $inst1->getUserAgent());

        $this->assertSame($appVersion3, $inst1->getAppVersion());
    }
}

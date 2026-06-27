<?php

namespace alcamo\pwa;

use alcamo\dao\DbAccessor;
use alcamo\exception\DataNotFound;
use alcamo\time\Duration;
use PHPUnit\Framework\TestCase;

class OpenInstAccessorTest extends TestCase
{
    public const DSN = 'sqlite::memory:';

    private $accessor_;

    public function setUp(): void
    {
        $dbAccessor = DbAccessor::newFromProps(
            [ 'dsn' => static::DSN, 'namePrefix' => 'bar_' ]
        );

        $dbAccessor->executeScript('PRAGMA foreign_keys = ON');

        (new Installer($dbAccessor))->install();

        $this->accessor_ = new OpenInstAccessor(
            $dbAccessor,
            new PasswdTransformer(random_bytes(8)),
            new Duration('PT5S')
        );

        $accountAccessor_ = new AccountAccessor($dbAccessor);

        $accountAccessor_->add('alice');

        $accountAccessor_->add('bob');
    }

    public function testGetUserInsts()
    {
        $alice1Obfuscated = $this->accessor_->add('alice');
        $bob1Obfuscated = $this->accessor_->add('bob');
        $alice2Obfuscated = $this->accessor_->add('alice');
        $bob2Obfuscated = $this->accessor_->add('bob');
        $alice3Obfuscated = $this->accessor_->add('alice');

        $i = 0;

        foreach ($this->accessor_->getUserInsts('alice') as $record) {
            $this->assertSame(
                'alice',
                $record->username
            );

            $i++;
        }

        $this->assertSame(3, $i);
    }

    public function testAdd()
    {
#        echo "*** A " . (new \DateTime())->format('H:i:s') . "\n";

        $alice1Obfuscated = $this->accessor_->add('alice');

        sleep(3);

#        echo "*** B " . (new \DateTime())->format('H:i:s') . "\n";

        $alice2Obfuscated = $this->accessor_->add('alice');

        $alice2 = $this->accessor_->get('alice', $alice2Obfuscated);

        $alice1 = $this->accessor_->get('alice', $alice1Obfuscated);

        $this->assertTrue(
            $this->accessor_->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice1Obfuscated,
                $alice1->passwd_hash
            )
        );

        $this->assertTrue(
            $this->accessor_->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice2Obfuscated,
                $alice2->passwd_hash
            )
        );

        $this->assertFalse(
            $this->accessor_->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice2Obfuscated,
                $alice1->passwd_hash
            )
        );

        $this->assertFalse(
            $this->accessor_->getPasswdTransformer()->verifyObfuscatedPasswd(
                $alice1Obfuscated,
                $alice2->passwd_hash
            )
        );

        $this->assertInstanceOf(
            OpenInstRecord::class,
            $this->accessor_->get('alice', $alice2Obfuscated)
        );

#        echo "*** C " . (new \DateTime())->format('H:i:s') . "\n";

        sleep(2);

#        echo "*** D " . (new \DateTime())->format('H:i:s') . "\n";

        // alice 2 is still there
        $this->assertInstanceOf(
            OpenInstRecord::class,
            $this->accessor_->get('alice', $alice2Obfuscated)
        );

#        echo "*** E " . (new \DateTime())->format('H:i:s') . "\n";

        // alice 1 is expired now
        $this->assertNull($this->accessor_->get('alice', $alice1Obfuscated));

        // unlike the previous invocation, this tests the case that no
        // record is found in the table
        $this->assertNull($this->accessor_->get('alice', $alice1Obfuscated));

        sleep(2);

        // now alice 2 is expired as well
        $this->assertNull($this->accessor_->get('alice', $alice2Obfuscated));
    }

    public function testRemoveException()
    {
        $this->expectException(DataNotFound::class);

        $this->expectExceptionMessage(
            'Data not found in table "open_inst" for key "bar"'
        );

        $this->accessor_->remove('bar');
    }
}

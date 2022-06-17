<?php

namespace alcamo\pwa;

use alcamo\cli\AbstractCli;
use GetOpt\{GetOpt, Operand};

class Cli extends AbstractCli
{
    public const OPTIONS =
        [
            'json-config-file' => [
                'j',
                GetOpt::REQUIRED_ARGUMENT,
                'Read conf from this JSON file'
            ]
        ]
        + parent::OPTIONS;

    public const COMMANDS = [
        'add' => [
            'addOpenInst',
            [
                'no-mail' => [
                    null,
                    self::NO_ARGUMENT,
                    'Do not send a mail to the user'
                ]
            ],
            [ 'username' => Operand::REQUIRED ],
            'Add an open installation for a user'
        ],
        'list-accounts' => [
            'listAccounts',
            [],
            [],
            'List accounts'
        ],
        'list-open-insts' => [
            'listOpenInsts',
            [],
            [],
            'List open installations'
        ],
        'setup-database' => [
            'setupDatabase',
            [],
            [],
            'Setup the database'
        ],
        'test-database' => [
            'testDatabase',
            [],
            [],
            'Test whether database is accessible'
        ],
        'test-smtp' => [
            'testSmtp',
            [],
            [ 'to' => Operand::REQUIRED ],
            'Test the SMTP server'
        ]
    ];

    public const ACCOUNT_LIST_FMT = "%-37s  %-19s  %-19s\n";

    public const OPEN_INST_LIST_FMT = "%-58s  %-19s\n";

    public const TIMESTAMP_FMT = 'Y-m-d H:i:s';

    protected $params_;
    protected $accountMgr_;
    protected $mailer_;

    /**
     * @param $params array or ArrayAccess object containing
     * - `db`
     * - `smtp`
     */
    public function __construct(iterable $params)
    {
        parent::__construct();

        $this->params_ = $params;
    }

    public function getParams(): array
    {
        return $this->params_;
    }

    public function getAccountMgr(): AccountMgr
    {
        return $this->accountMgr_;
    }

    public function getMailer(): Mailer
    {
        return $this->mailer_;
    }

    public function process($arguments = null): int
    {
        parent::process($arguments);

        if (!$this->getCommand()) {
            $this->showHelp();
            return 0;
        }

        if ($this->getOption('json-config-file')) {
            $this->params_ = json_decode(
                file_get_contents($this->getOption('json-config-file')),
                true
            );
        }

        $this->accountMgr_ = AccountMgr::newFromParams($this->params_);

        if ($this->getOption('verbose') > 0) {
            $this->params_['smtp']['debug'] = true;
        }

        $this->mailer_ = Mailer::newFromParams($this->params_['smtp']);

        return $this->{$this->getCommand()->getHandler()}();
    }

    public function addOpenInst(): int
    {
        $obfuscated = $this->accountMgr_->addOpenInst(
            $this->getOperand('username')
        );

        if (!$this->getOption('no-mail')) {
            $this->mailOpenInst($this->getOperand('username'), $obfuscated);
        }

        return 0;
    }

    public function listAccounts(): int
    {
        echo "\n";

        printf(static::ACCOUNT_LIST_FMT, 'username', 'created', 'modified');

        echo "\n";

        printf(
            static::ACCOUNT_LIST_FMT,
            '-------------------------------------',
            '-------------------',
            '-------------------'
        );

        echo "\n";

        foreach ($this->getAccountMgr()->getAccountAccessor() as $record) {
            printf(
                static::ACCOUNT_LIST_FMT,
                substr($record->getUsername(), 0, 39),
                $record->getCreated()->format(static::TIMESTAMP_FMT),
                $record->getModified()->format(static::TIMESTAMP_FMT)
            );
        }

        echo "\n";

        return 0;
    }

    public function listOpenInsts(): int
    {
        echo "\n";

        printf(static::OPEN_INST_LIST_FMT, 'username', 'created');

        echo "\n";

        printf(
            static::OPEN_INST_LIST_FMT,
            '----------------------------------------------------------',
            '-------------------'
        );

        echo "\n";

        foreach ($this->getAccountMgr()->getOpenInstAccessor() as $record) {
            printf(
                static::OPEN_INST_LIST_FMT,
                substr($record->getUsername(), 0, 58),
                $record->getCreated()->format(static::TIMESTAMP_FMT)
            );
        }

        echo "\n";

        return 0;
    }

    public function mailOpenInst(string $username, string $obfuscated): void
    {
        /** To be implemented in derived class. */
    }

    public function setupDatabase(): int
    {
        $this->getAccountMgr()->createTables();

        return 0;
    }

    public function testDatabase(): int
    {
        /* Nothing extra to do since the constructor of AccountMgr called in
         * process() fails if the database is not accessible. */
        return 0;
    }

    public function testSmtp(): int
    {
        $this->getMailer()->sendTestMail($this->getOperand('to'));

        return 0;
    }
}

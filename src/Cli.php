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
        'list-insts' => [
            'listInsts',
            [
                'app-version-detail' => [
                    null,
                    GetOpt::NO_ARGUMENT,
                    'Detailed app version'
                ],
                'user-agent-detail' => [
                    null,
                    GetOpt::NO_ARGUMENT,
                    'Detailed user agent'
                ],
                'username' => [
                    'u',
                    GetOpt::REQUIRED_ARGUMENT,
                    'Filter for this user'
                ]
            ],
            [],
            'List installations'
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
        ],
        'verify-passwd' => [
            'verifyPasswd',
            [],
            [
                'obfuscated' => Operand::REQUIRED,
                'passwdHash' => Operand::REQUIRED
            ],
            'Verify obfuscated password against hash'
        ]
    ];

    public const ACCOUNT_LIST_FMT = "%-37s  %-19s  %-19s\n";

    public const INST_LIST_FMT = "%-24s  %-6s %-23s  %-8s  %-10s\n";

    public const INST_LIST_USER_AGENT_DETAIL_FMT = "%-24s  %-6s  %-45s\n";

    public const INST_LIST_APP_VERSION_DETAIL_FMT =
        "%-24s  %-6s  %-32s  %-10s\n";

    public const OPEN_INST_LIST_FMT = "%-58s  %-19s\n";

    public const TIMESTAMP_FMT = 'Y-m-d H:i:s';

    public const DATE_FMT = 'Y-m-d';

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

    public function getParams(): iterable
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

        $this->reportOpenInst($this->getOperand('username'), $obfuscated);

        return 0;
    }

    public function listAccounts(): int
    {
        echo "\n";

        printf(static::ACCOUNT_LIST_FMT, 'username', 'created', 'modified');

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

    public function listInsts(): int
    {
        if ($this->getOption('username')) {
            $iterator = $this->getAccountMgr()->getInstAccessor()
                ->getUserInsts($this->getOption('username'));
        } else {
            $iterator = $this->getAccountMgr()->getInstAccessor();
        }

        echo "\n";

        if ($this->getOption('user-agent-detail')) {
            printf(
                static::INST_LIST_USER_AGENT_DETAIL_FMT,
                'username',
                'id',
                'user-agent'
            );

            printf(
                static::INST_LIST_USER_AGENT_DETAIL_FMT,
                '------------------------',
                '------',
                '---------------------------------------------',
            );

            echo "\n";

            foreach ($iterator as $record) {
                printf(
                    static::INST_LIST_USER_AGENT_DETAIL_FMT,
                    substr($record->getUsername(), 0, 24),
                    $record->getShortInstId(),
                    substr($record->getUserAgent(), 0, 45)
                );
            }
        } elseif ($this->getOption('app-version-detail')) {
            printf(
                static::INST_LIST_APP_VERSION_DETAIL_FMT,
                'username',
                'id',
                'version',
                'modified'
            );

            printf(
                static::INST_LIST_APP_VERSION_DETAIL_FMT,
                '------------------------',
                '------',
                '--------------------------------',
                '----------'
            );

            echo "\n";

            foreach ($iterator as $record) {
                printf(
                    static::INST_LIST_APP_VERSION_DETAIL_FMT,
                    substr($record->getUsername(), 0, 24),
                    $record->getShortInstId(),
                    $record->getAppVersion(),
                    $record->getModified()->format(static::DATE_FMT)
                );
            }
        } else {
            printf(
                static::INST_LIST_FMT,
                'username',
                'id',
                'user-agent',
                'version',
                'modified'
            );

            printf(
                static::INST_LIST_FMT,
                '------------------------',
                '------',
                '-----------------------',
                '--------',
                '----------',
            );

            echo "\n";

            foreach ($iterator as $record) {
                printf(
                    static::INST_LIST_FMT,
                    substr($record->getUsername(), 0, 24),
                    $record->getShortInstId(),
                    substr($record->getUserAgent(), 0, 23),
                    substr($record->getAppVersion(), 0, 8),
                    $record->getModified()->format(static::DATE_FMT)
                );
            }
        }

        echo "\n";

        return 0;
    }

    public function listOpenInsts(): int
    {
        echo "\n";

        printf(static::OPEN_INST_LIST_FMT, 'username', 'created');

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

    public function reportOpenInst(string $username, string $obfuscated): void
    {
        echo "Created new open instance at {$this->createUrl($username, $obfuscated)}\n";
    }

    public function mailOpenInst(string $username, string $obfuscated): void
    {
        /** To be implemented in derived class. */
    }

    public function createUrl(string $username, string $obfuscated): string
    {
        return "{$this->params_['url']}?u=$username&p=" . bin2hex($obfuscated);
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

    public function verifyPasswd(): int
    {
        if (
            $this->getAccountMgr()->getInstAccessor()->getPasswdTransformer()
                ->verifyObfuscatedPasswd(
                    hex2bin($this->getOperand('obfuscated')),
                    $this->getOperand('passwdHash')
                )
        ) {
            echo "Password OK\n";
            return 0;
        } else {
            echo "Password does not match\n";
            return 1;
        }
    }
}

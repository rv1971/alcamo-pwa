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
                'Read conf from this JSON file',
                'filename'
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
                'detail' => [
                    null,
                    GetOpt::NO_ARGUMENT,
                    'Details with one property per line'
                ],
                'app-version-detail' => [
                    null,
                    GetOpt::NO_ARGUMENT,
                    'Detailed app version'
                ],
                'timestamp-detail' => [
                    null,
                    GetOpt::NO_ARGUMENT,
                    'Detailed timestamp'
                ],
                'user-agent-detail' => [
                    null,
                    GetOpt::NO_ARGUMENT,
                    'Detailed user agent'
                ],
                'username' => [
                    'u',
                    GetOpt::REQUIRED_ARGUMENT,
                    'Filter for this user',
                    'username'
                ],
                'with-launcher' => [
                    'w',
                    GetOpt::NO_ARGUMENT,
                    'Instances with nonemtpy launcher only'
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

    public const INST_LIST_DETAIL_FMT =
          "username:    %s\n"
        . "instance id: %s\n"
        . "user agent:  %s\n"
        . "app version: %s\n"
        . "launcher:    %s\n"
        . "passwh hash: %s\n"
        . "created:     %s\n"
        . "modified:    %s\n\n";

    public const INST_LIST_USER_AGENT_DETAIL_FMT = "%-24s  %-6s  %-45s\n";

    public const INST_LIST_APP_VERSION_DETAIL_FMT =
        "%-24s  %-6s  %-32s  %-10s\n";

    public const INST_LIST_TIMESTAMP_DETAIL_FMT =
        "%-24s  %-6s  %-14s  %-8s  %-19s\n";

    public const OPEN_INST_LIST_FMT = "%-58s  %-19s\n";

    public const TIMESTAMP_FMT = 'Y-m-d H:i:s';

    public const DATE_FMT = 'Y-m-d';

    protected $conf_;
    protected $accountMgr_;
    protected $mailer_;

    /**
     * @param $conf array or ArrayAccess object containing
     * - `db`
     * - `smtp`
     */
    public function __construct(iterable $conf)
    {
        parent::__construct();

        $this->conf_ = $conf;
    }

    public function getConf(): iterable
    {
        return $this->conf_;
    }

    public function getAccountMgr(): AccountMgr
    {
        return $this->accountMgr_;
    }

    public function getMailer(): Mailer
    {
        return $this->mailer_;
    }

    public function innerRun(): int
    {
        if (!$this->getCommand()) {
            $this->showHelp();
            return 0;
        }

        if ($this->getOption('json-config-file')) {
            $this->conf_ = json_decode(
                file_get_contents($this->getOption('json-config-file')),
                true
            );
        }

        $this->accountMgr_ = AccountMgr::newFromConf($this->conf_);

        if ($this->getOption('verbose') > 0) {
            $this->conf_['smtp']['debug'] = true;
        }

        $this->mailer_ = Mailer::newFromConf($this->conf_['smtp']);

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

        if ($this->getOption('with-launcher')) {
            $records = [];

            foreach ($iterator as $record) {
                if ($record->getLauncher() !== null) {
                    $records[] = $record;
                }
            }

            $iterator = new \ArrayIterator($records);
        }

        echo "\n";

        if ($this->getOption('detail')) {
            foreach ($iterator as $record) {
                printf(
                    static::INST_LIST_DETAIL_FMT,
                    $record->getUsername(),
                    $record->getInstId(),
                    $record->getUserAgent(),
                    $record->getAppVersion(),
                    $record->getLauncher(),
                    $record->getPasswdHash(),
                    $record->getCreated()->format(static::TIMESTAMP_FMT),
                    $record->getModified()->format(static::TIMESTAMP_FMT)
                );
            }
        } elseif ($this->getOption('user-agent-detail')) {
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
                    substr(str_replace('Mozilla/5.0 ', '', $record->getUserAgent()), 0, 45)
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
        } elseif ($this->getOption('timestamp-detail')) {
            printf(
                static::INST_LIST_TIMESTAMP_DETAIL_FMT,
                'username',
                'id',
                'user-agent',
                'version',
                'modified'
            );

            printf(
                static::INST_LIST_TIMESTAMP_DETAIL_FMT,
                '------------------------',
                '------',
                '--------------',
                '--------',
                '-------------------'
            );

            echo "\n";

            foreach ($iterator as $record) {
                printf(
                    static::INST_LIST_TIMESTAMP_DETAIL_FMT,
                    substr($record->getUsername(), 0, 24),
                    $record->getShortInstId(),
                    substr(str_replace('Mozilla/5.0 ', '', $record->getUserAgent()), 0, 14),
                    substr($record->getAppVersion(), 0, 8),
                    $record->getModified()->format(static::TIMESTAMP_FMT)
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
                    substr(str_replace('Mozilla/5.0 ', '', $record->getUserAgent()), 0, 23),
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
        $this->reportProgress(
            "Created new open instance at {$this->createUrl($username, $obfuscated)}"
        );
    }

    public function mailOpenInst(string $username, string $obfuscated): void
    {
        /** To be implemented in derived class. */
    }

    public function createUrl(string $username, string $obfuscated): string
    {
        return "{$this->conf_['url']}?u=$username&p=" . bin2hex($obfuscated);
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
            $this->reportProgress("Password OK");
            return 0;
        } else {
            $this->reportProgress("Password does not match");
            return 1;
        }
    }
}

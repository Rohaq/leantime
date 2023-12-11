<?php

declare(strict_types=1);

use PDO;
use Leantime\Core\Db as DbCore;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

// Don't run script unless using the 'run' command
if (! isset($_SERVER['argv'][1]) || $_SERVER['argv'][1] !== 'run') {
    return;
}

if (! file_exists($composer = __DIR__ . '/../vendor/autoload.php')) {
    throw new RuntimeException('Please run "make build-dev" to run tests.');
}

require $composer;

define('PROJECT_ROOT', realpath(__DIR__ . '/..') . '/');
define('APP_ROOT', PROJECT_ROOT . 'app/');
define('DEV_ROOT', PROJECT_ROOT . '.dev/');

$bootstrapper = get_class(new class {
    /**
     * @var self
     */
    protected static $instance;

    /**
     * @var DbCore
     * Instance of the database core class.
     */
    private DbCore $db;

    /**
     * @var Process
     */
    protected Process $seleniumProcess;

    /**
     * @var Process
     */
    protected Process $dockerProcess;

    /**
     * Get the singleton instance of this class
     *
     * @access public
     * @return self
     */
    public static function getInstance(): self
    {
        if (! isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Start the testing environment
     *
     * @access public
     * @return void
     */
    public function start(): void
    {
        $this->startDevEnvironment();
        $this->setFolderPermissions();
        $this->createDatabase();
        $this->startSelenium();
        $this->createStep('Starting Codeception Testing Framework');
    }

    /**
     * Destroy the testing environment
     *
     * @access public
     * @return void
     */
    public function destroy(): void
    {
        $this->createStep('Stopping Codeception Testing Framework');
        $this->stopSelenium();
        $this->stopDevEnvironment();
    }

    /**
     * Stop Selenium
     *
     * @access protected
     * @return void
     */
    protected function stopSelenium(): void
    {
        $this->createStep('Stopping Selenium');

        try {
            $this->seleniumProcess->stop();
            // we want the script to continue even if failure
        } catch (Throwable $e) {
            return;
        }
    }

    /**
     * Stop the dev environment
     *
     * @deprecated We do this in the makefile now
     * @access protected
     * @return void
     */
    protected function stopDevEnvironment(): void
    {
        $this->createStep('Stopping Leantime Dev Environment');

        // We do this in the makefile now
        return;
    }

    /**
     * Start the dev environment
     *
     * @access protected
     * @return void
     */
    protected function startDevEnvironment(): void
    {
        $this->createStep('Build & Start Leantime Dev Environment');

        /** @var DbCore */
        $this->db = app()->make(DbCore::class, [
            'user' => getenv('LEAN_DB_USER'),
            'password' => getenv('LEAN_DB_PASSWORD'),
            'host' => getenv('LEAN_DB_HOST'),
            'port' => 3307,
        ]);

        return;
    }

    /**
     * Create the test database
     *
     * @access protected
     * @return void
     */
    protected function createDatabase(): void
    {
        $this->createStep('Creating Test Database');

        $envDbName = getenv('LEAN_DB_DATABASE');
        $envDbUser = getenv('LEAN_DB_USER');

        $sqlParams = [
            'dbName' => $envDbName . '_test',
            'dbUser' => $envDbUser . '\'@\'%',
        ];

        $sqlDropDb = 'DROP DATABASE IF EXISTS :dbName;';
        $sqlCreateDb = 'CREATE DATABASE IF NOT EXISTS :dbName;';
        $sqlGrantPrivileges = 'GRANT ALL PRIVILEGES ON :dbName.* TO :dbUser;';
        $sqlFlushPrivileges = 'FLUSH PRIVILEGES;';
        $sql = $sqlDropDb . $sqlCreateDb . $sqlGrantPrivileges . $sqlFlushPrivileges;

        $sth = $this->db->prepare($sql, $sqlParams);
        $sth->execute();
    }

    /**
     * Set folder permissions
     * @todo Currently moving this to the makefile
     *
     * @access protected
     * @return void
     */
    protected function setFolderPermissions(): void
    {
        $this->createStep('Setting folder permissions on cache folder');

        // Set file permissions
        chown('/var/www/html/cache', 'www-data');
        chgrp('/var/www/html/cache', 'www-data');
    }

    /**
     * Start Selenium
     *
     * @access protected
     * @return void
     */
    protected function startSelenium(): void
    {
        $this->createStep('Starting Selenium');
        $this->executeCommand(
            [
                'npx',
                'selenium-standalone',
                'install',
            ]
        );
        $this->seleniumProcess = $this->executeCommand([
            'npx',
            'selenium-standalone',
            'start',
        ], ['background' => true]);
        $this->seleniumProcess->waitUntil(function ($type, $buffer) {
            $this->commandOutputHandler($type, $buffer);
            return strpos($buffer, 'Selenium started') !== false;
        });
    }

    /**
     * Create a step in the output
     *
     * @access protected
     * @param  string $message
     * @return void
     */
    protected function createStep(string $message): void
    {
        $chars = strlen($message);
        $line = str_repeat('=', $chars);

        echo "\n$line\n$message\n$line\n";
    }

    /**
     * Execute a command
     *
     * @access protected
     * @param  string|array $command
     * @param  array        $args
     * @param  boolean      $required
     * @return Process|string
     */
    protected function executeCommand(
        string|array $command,
        array $args = [],
        bool $required = true,
    ): Process|string {
        $process = is_array($command)
            ? new Process($command)
            : Process::fromShellCommandline($command);

        if (isset($args['cwd'])) {
            $process->setWorkingDirectory($args['cwd']);
        }

        if (isset($args['timeout'])) {
            $process->setTimeout($args['timeout']);
        }

        if (isset($args['options'])) {
            $process->setOptions($args['options']);
        }

        if (isset($args['background']) && $args['background']) {
            $process->start();
        } else {
            $process->run(fn ($type, $buffer) => $this->commandOutputHandler($type, $buffer));
        }

        if (
            $required
            && (! isset($args['background']) || ! $args['background'])
            && ! $process->isSuccessful()
        ) {
            throw new ProcessFailedException($process);
        }

        if (
            isset($args['getOutput'])
            && $args['getOutput']
        ) {
            if (isset($args['background']) && $args['background']) {
                throw new RuntimeException('Cannot get output from background process');
            }

            return $process->getOutput();
        }

        return $process;
    }

    /**
     * Handle command output
     *
     * @access private
     * @param  string $type
     * @param  string $buffer
     * @return void
     */
    private function commandOutputHandler(string $type, string $buffer): void
    {
        echo Process::ERR === $type
            ? "\nSTDERR: $buffer"
            : "\nSTDOUT: $buffer";
    }
});

register_shutdown_function(fn () => $bootstrapper::getInstance()->destroy());
$bootstrapper::getInstance()->start();

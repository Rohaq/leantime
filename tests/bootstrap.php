<?php

declare(strict_types=1);

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
     * @deprecated We do this in the makefile now
     * @access public
     * @return void
     */
    public function start(): void
    {
        return;
    }

    /**
     * Destroy the testing environment
     *
     * @deprecated We do this in the makefile now
     * @access public
     * @return void
     */
    public function destroy(): void
    {
        return;
    }

    /**
     * Stop Selenium
     *
     * @deprecated We do this in the makefile now
     * @access protected
     * @return void
     */
    protected function stopSelenium(): void
    {
        return;
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
        return;
    }

    /**
     * Start the dev environment and set up DB connection
     *
     * @deprecated We do this in the makefile now
     * @access protected
     * @return void
     */
    protected function startDevEnvironment(): void
    {
        return;
    }

    /**
     * Create the test database
     *
     * @deprecated We do this in the makefile now
     * @access protected
     * @return void
     */
    protected function createDatabase(): void
    {
        return;
    }

    /**
     * Set folder permissions
     *
     * @deprecated We do this in the makefile now
     * @access protected
     * @return void
     */
    protected function setFolderPermissions(): void
    {
        return;
    }

    /**
     * Start Selenium
     *
     * @deprecated We do this in the makefile now
     * @access protected
     * @return void
     */
    protected function startSelenium(): void
    {
        return;
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
});

register_shutdown_function(fn () => $bootstrapper::getInstance()->destroy());
$bootstrapper::getInstance()->start();

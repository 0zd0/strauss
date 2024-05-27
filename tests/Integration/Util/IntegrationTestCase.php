<?php
/**
 * Creates a deletes a temp directory for tests.
 *
 * Could just system temp directory, but this is useful for setting breakpoints and seeing what has happened.
 */

namespace BrianHenryIE\Strauss\Tests\Integration\Util;

use BrianHenryIE\Strauss\Console\Commands\Compose;
use BrianHenryIE\Strauss\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class IntegrationTestCase
 * @package BrianHenryIE\Strauss\Tests\Integration\Util
 * @coversNothing
 */
class IntegrationTestCase extends TestCase
{
    protected string $projectDir;

    protected $testsWorkingDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->projectDir = getcwd();

        $this->testsWorkingDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'strausstestdir' . DIRECTORY_SEPARATOR;

        if ('Darwin' === PHP_OS) {
            $this->testsWorkingDir = DIRECTORY_SEPARATOR . 'private' . $this->testsWorkingDir;
        }

        if (file_exists($this->testsWorkingDir)) {
            $this->deleteDir($this->testsWorkingDir);
        }

        @mkdir($this->testsWorkingDir);

        if (file_exists($this->projectDir . '/strauss.phar')) {
            echo "strauss.phar found\n";
            ob_flush();
        }
    }

    protected function runStrauss(): int
    {
        if (file_exists($this->projectDir . '/strauss.phar')) {
            exec('php ' . $this->projectDir . '/strauss.phar', $output, $return_var);
            return $return_var;
        }

        $inputInterfaceMock = $this->createMock(InputInterface::class);
        $outputInterfaceMock = $this->createMock(OutputInterface::class);

        $strauss = new Compose();

        return $strauss->run($inputInterfaceMock, $outputInterfaceMock);
    }

    /**
     * Delete $this->testsWorkingDir after each test.
     *
     * @see https://stackoverflow.com/questions/3349753/delete-directory-with-files-in-it
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $dir = $this->testsWorkingDir;

        $this->deleteDir($dir);
    }

    protected function deleteDir($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

        $filesystem = new \Symfony\Component\Filesystem\Filesystem();
        $isSymlink = function ($path) use ($filesystem): bool {
            return ! is_null($filesystem->readlink($path));
        };

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ($isSymlink($file->getPath())) {
                if (false !== strpos('WIN', PHP_OS)) {
                    /**
                     * `unlink()` will not work on Windows. `rmdir()` will not work if there are files in the directory.
                     * "On windows, take care that `is_link()` returns false for Junctions."
                     *
                     * @see https://www.php.net/manual/en/function.is-link.php#113263
                     * @see https://stackoverflow.com/a/18262809/336146
                     */
                    rmdir($file);
                } else {
                    unlink($file);
                }
            } elseif ($file->isDir()) {
                rmdir($file->getRealPath());
            } elseif (is_readable($file->getRealPath())) {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }
}

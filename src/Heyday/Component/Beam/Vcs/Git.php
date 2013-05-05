<?php

namespace Heyday\Component\Beam\Vcs;

use Symfony\Component\Process\Process;

class Git implements VcsProvider
{
    /**
     * @var
     */
    protected $srcdir;
    /**
     * @param $srcdir
     */
    public function __construct($srcdir)
    {
        $this->srcdir = $srcdir;
    }
    /**
     * @{inheritDoc}
     */
    public function getCurrentBranch()
    {
        $process = $this->process('git rev-parse --abbrev-ref HEAD');
        return trim($process->getOutput());
    }
    /**
     * @{inheritDoc}
     */
    public function getAvailableBranches()
    {
        $process = $this->process('git branch -a');
        $matches = array();
        preg_match_all('/[^\n](?:[\s\*]*)([^\s]*)(?:.*)/', $process->getOutput(), $matches);
        return $matches[1];
    }
    /**
     * @{inheritDoc}
     */
    public function exists()
    {
        return file_exists($this->srcdir . DIRECTORY_SEPARATOR . '.git');
    }
    /**
     * @{inheritDoc}
     */
    public function exportBranch($branch, $location)
    {
        if (file_exists($location)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($location),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $file) {
                if (in_array($file->getBasename(), array('.', '..'))) {
                    continue;
                } elseif ($file->isDir()) {
                    rmdir($file->getPathname());
                } elseif ($file->isFile() || $file->isLink()) {
                    unlink($file->getPathname());
                }
            }
            rmdir($location);
        }

        mkdir($location, 0755);

        $this->process(
            sprintf(
                '(git archive %s) | (cd %s && tar -xf -)',
                $branch,
                $location
            )
        );
    }
    /**
     * @{inheritDoc}
     */
    public function updateBranch($branch)
    {
        $parts = explode('/', $branch);
        if (!isset($parts[1])) {
            throw new \InvalidArgumentException('The git vcs provider can only update remotes');
        }
        $this->process(sprintf('(git remote update --prune %s)', $parts[1]));
    }
    /**
     * A helper method that returns a process with some defaults
     * @param $command
     * @throws \RuntimeException
     * @return Process
     */
    protected function process($command)
    {
        $process = new Process(
            $command,
            $this->srcdir
        );
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
        return $process;
    }
}
<?php

declare(strict_types=1);

namespace Infection\Config\Guesser;

class SourceDirGuesser implements Guesser
{
    public function __construct(\stdClass $composerJsonContent)
    {
        $this->composerJsonContent = $composerJsonContent;
    }

    public function guess()
    {
        if (!isset($this->composerJsonContent->autoload)) {
            return null;
        }

        $autoload = $this->composerJsonContent->autoload;

        if (isset($autoload->{'psr-4'})) {
            return $this->getValues('psr-4');
        }

        if (isset($autoload->{'psr-0'})) {
            return $this->getValues('psr-0');
        }

        return null;
    }

    private function getValues(string $psr)
    {
        $dirs = array_map(
            function(string $dir) {
                return trim($dir, DIRECTORY_SEPARATOR);
            },
            array_values((array) $this->composerJsonContent->autoload->{$psr})
        );

        // we don't want to mix different framework's folders like "app" for Symfony
        if (in_array('src', $dirs, true)) {
            return ['src'];
        }

        return $dirs;
    }
}
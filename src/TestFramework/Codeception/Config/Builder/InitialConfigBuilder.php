<?php
/**
 * Copyright © 2017-2018 Maks Rafalko
 *
 * License: https://opensource.org/licenses/BSD-3-Clause New BSD License
 */

declare(strict_types=1);

namespace Infection\TestFramework\Codeception\Config\Builder;

use Infection\TestFramework\Codeception\Config\YamlConfigurationHelper;
use Infection\TestFramework\Config\InitialConfigBuilder as ConfigBuilder;
use Symfony\Component\Console\Output\OutputInterface;

class InitialConfigBuilder implements ConfigBuilder
{
    /**
     * @var YamlConfigurationHelper
     */
    private $configurationHelper;

    public function __construct(string $tempDir, string $projectDir, string $originalConfig, array $srcDirs, OutputInterface $output)
    {
        $this->configurationHelper = new YamlConfigurationHelper($tempDir, $projectDir, $originalConfig, $srcDirs, $output);
    }

    public function build(): string
    {
        $pathToInitialConfigFile = $this->configurationHelper->getTempDir() . DIRECTORY_SEPARATOR . 'codeception.initial.infection.yml';

        file_put_contents($pathToInitialConfigFile, $this->configurationHelper->getTransformedConfig());

        return $pathToInitialConfigFile;
    }
}

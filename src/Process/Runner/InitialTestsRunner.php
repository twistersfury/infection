<?php
/**
 * This code is licensed under the BSD 3-Clause License.
 *
 * Copyright (c) 2017, Maks Rafalko
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * * Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

declare(strict_types=1);

namespace Infection\Process\Runner;

use Infection\Event\EventDispatcher\EventDispatcher;
use Infection\Event\InitialTestCaseWasCompleted;
use Infection\Event\InitialTestSuiteWasFinished;
use Infection\Event\InitialTestSuiteWasStarted;
use Infection\Process\Factory\InitialTestsRunProcessFactory;
use Symfony\Component\Process\Process;
use function time;

/**
 * @internal
 */
final class InitialTestsRunner
{
    private const ERROR_TIMEOUT = 10; // Time To Allow Errors To Exit (Seconds)

    private $processBuilder;
    private $eventDispatcher;

    public function __construct(
        InitialTestsRunProcessFactory $processFactory,
        EventDispatcher $eventDispatcher
    ) {
        $this->processBuilder = $processFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param string[] $phpExtraOptions
     */
    public function run(
        string $testFrameworkExtraOptions,
        array $phpExtraOptions,
        bool $skipCoverage
    ): Process {
        $process = $this->processBuilder->createProcess(
            $testFrameworkExtraOptions,
            $phpExtraOptions,
            $skipCoverage
        );

        // Tracking Process Error Exit
        $expiration = null;

        $this->eventDispatcher->dispatch(new InitialTestSuiteWasStarted());

        $process->run(function (string $type) use ($process, &$expiration): void {
            if ($process::ERR === $type) {
                //If already started, do not start again and let parent one run - prevent infinite loop.
                //->isRunning calls the callback again every time there is any updated output.
                if ($expiration !== null) {
                    return;
                }

                // Give The Error Processing Time To Fully Output
                $expiration->time = time() + self::ERROR_TIMEOUT;

                while ($process->isRunning() && time() < $expiration->time) {
                    usleep(100);
                }

                // Stop on the first error encountered
                $process->stop();
            }

            $this->eventDispatcher->dispatch(new InitialTestCaseWasCompleted());
        });

        $this->eventDispatcher->dispatch(new InitialTestSuiteWasFinished($process->getOutput()));

        return $process;
    }
}

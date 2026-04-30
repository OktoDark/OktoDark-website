<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service;

final class CommandRunner
{
    /**
     * Streams command output in real time.
     *
     * @param callable $callback function(string $chunk): void
     */
    public function stream(string $command, string $cwd, callable $callback): int
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $cwd);

        if (!\is_resource($process)) {
            $callback("[error] Failed to start process.\n");

            return 1;
        }

        fclose($pipes[0]); // no stdin

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            $stdout = fgets($pipes[1]);
            $stderr = fgets($pipes[2]);

            if (false !== $stdout) {
                $callback($stdout);
            }

            if (false !== $stderr) {
                if (!$this->isNoise($stderr)) {
                    $callback('[stderr] '.$stderr);
                }
            }

            $status = proc_get_status($process);

            if (!$status['running']) {
                break;
            }

            usleep(200000); // 200ms
        }

        // Read remaining output
        while ($stdout = fgets($pipes[1])) {
            $callback($stdout);
        }
        while ($stderr = fgets($pipes[2])) {
            if (!$this->isNoise($stderr)) {
                $callback('[stderr] '.$stderr);
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }

    /**
     * Filters out PHP environment noise (Plesk startup warnings, etc.).
     */
    private function isNoise(string $line): bool
    {
        $noisePatterns = [
            'PHP Startup: Unable to load dynamic library',
            'is already loaded in Unknown on line 0',
            'PHP Warning:  Module',
        ];

        foreach ($noisePatterns as $pattern) {
            if (str_contains($line, $pattern)) {
                return true;
            }
        }

        return false;
    }
}

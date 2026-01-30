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
     * @param string $command Full command line (already validated/whitelisted)
     * @param string $cwd     Working directory (project root)
     */
    public function run(string $command, string $cwd): string
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $cwd);

        if (!\is_resource($process)) {
            return 'Failed to start process.';
        }

        fclose($pipes[0]); // we don't send input

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $output = '';
        if ($stdout) {
            $output .= $stdout.\PHP_EOL;
        }
        if ($stderr) {
            $output .= '[stderr]'.\PHP_EOL.$stderr.PHP_EOL;
        }
        $output .= sprintf('[exit code] %d', $exitCode).\PHP_EOL;

        return $output;
    }
}

<?php

use DigiLive\PhpStorm\PhpStorm;

// Define variables.
$options   = getopt('v:', ['revert']);
$version   = $options['v'] ?? null;
$reverting = isset($options['revert']);

// Validate CLI arguments.
if ($version === null) {
    echo
        'Please specify a Major.Minor version of phpStorm (E.g. ' .
        pathinfo(__FILE__, PATHINFO_BASENAME) .
        ' -v 2020.1)!';
    exit(1);
}

// Initialize the autoloader.
require __DIR__ . '/vendor/autoload.php';

$phpStorm = new PhpStorm($version, $reverting);

try {
    echo 'Validating the version and file system.';
    $phpStorm->validateProperties();
    echoFormatted('fGreen', ' Valid!' . PHP_EOL);
} catch (Exception $e) {
    echoFormatted('fRed', PHP_EOL . '[ERROR] ');
    echoFormatted('fDefault', $e->getMessage());
    exit(1);
}

// Check if phpStorm is running.
$taskList = [];
exec("tasklist 2>NUL", $taskList);

while ($repeat ?? true) {
    foreach ($taskList as $entry) {
        if (preg_match('/(phpstorm.*)\.exe/i', $entry, $matches)) {
            echo 'PhpStorm is currently running. Do you want to close it [Y/n]? ';
            $response = strtoupper(stream_get_line(STDIN, 1024, PHP_EOL));
            if ($response == 'Y') {
                exec("taskkill /F /IM " . $matches[1] . ".exe 2>NUL");
            } else {
                // Continue script.
                echo PHP_EOL;
                $repeat = false;
            }
        } else {
            // PhpStorm not running. Continue script.
            $repeat = false;
        }
    }
}

// Indicate when reverting last changes.
if ($reverting) {
    echoFormatted('bold', 'Reverting changes...');
    echo PHP_EOL;
}
$process = $reverting ? 'Restoring' : 'Backing up';

try {
    $phpStorm->resetEvaluation();
    foreach ($phpStorm->getLog() as $logEntry) {
        switch ($logEntry[0]) {
            case PhpStorm::INFO:
                $fontFormat = 'fDefault';
                break;
            case PhpStorm::SUCCESS:
                $fontFormat = 'fGreen';
                break;
            case PhpStorm::WARNING:
                $fontFormat = 'fYellow';
                break;
            case PhpStorm::ERROR:
                $fontFormat = 'fRed';
                break;
            default:
                $fontFormat = 'default';
        }

        echoFormatted($fontFormat, "[{$logEntry[0]}] ");
        echoFormatted('default', $logEntry[1]);
        echo PHP_EOL;
    }
} catch (Exception $e) {
    echoFormatted('fRed', '[ERROR] ', $e->getMessage());
    echo PHP_EOL;
}

// ---------------------------------------------------------------------------------------------------------------------
echoFormatted('bold', 'Finished!');
echo PHP_EOL;

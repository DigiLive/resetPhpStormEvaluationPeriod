<?php

use Windows\Registry\Registry;
use Windows\Registry\RegistryKey;

if (!isset($argv[1])) {
    echo 'Please specify a version of phpStorm as the first argument (E.g. 2020.1)!';
    exit(1);
}
$version = $argv[1];

// Check if phpStorm is running.
$taskList = [];
exec("tasklist 2>NUL", $taskList);
while ($continue ?? true) {
    foreach ($taskList as $entry) {
        if (preg_match('/(phpstorm.*)\.exe/i', $entry, $matches)) {
            echo 'PhpStorm is currently running. Do you want to close it [Y/n]? ';
            $response = strtoupper(stream_get_line(STDIN, 1024, PHP_EOL));
            if ($response == 'Y') {
                exec("taskkill /F /IM " . $matches[1] . ".exe 2>NUL");
            } else {
                // Continue script.
                $continue = false;
            }
        } else {
            // PhpStorm not running. Continue script.
            $continue = false;
        }
    }
}

require __DIR__ . '/vendor/autoload.php';

define('VERSIONS', ['2020.1']);
define('APPDATA_FOLDER', "{$_SERVER['APPDATA']}/JetBrains/PhpStorm$version");
define('EVAL_FOLDER', APPDATA_FOLDER . '/' . 'eval');
define('OTHER_XML', APPDATA_FOLDER . '/' . 'options/other.xml');
define('HKCU_REG_PATH', 'Software\JavaSoft\Prefs\jetbrains\phpStorm');

echo 'Validating Version...';
if (!in_array($version, VERSIONS)) {
    echoFormatted('fRed', PHP_EOL . '[ERROR] ');
    echoFormatted('fDefault', "This script does not support given version $version!");
    exit(1);
}
echoFormatted('fGreen', ' Valid!' . PHP_EOL);

echo 'Validating Folder Structure...';
if (!@is_dir(APPDATA_FOLDER) || !@is_dir(EVAL_FOLDER) || !@is_file(OTHER_XML)) {
    echoFormatted('fRed', PHP_EOL . '[ERROR] ');
    echoFormatted('fDefault', 'Unexpected folder structure!');
    exit(1);
}
echoFormatted('fGreen', ' Valid!' . PHP_EOL);

echo 'Validating Access...';
if (!@is_writable(EVAL_FOLDER) || !@is_writable(OTHER_XML)) {
    echoFormatted('fRed', PHP_EOL . '[ERROR] ');
    echoFormatted('fDefault', 'Writing to targets is not allowed!');
    exit(1);
}
echoFormatted('fGreen', ' Valid!' . PHP_EOL);

echo 'Backing Up Existing Evaluation Keys...' . PHP_EOL;
$errorQueue = [];
$keyFiles   = new FilesystemIterator(EVAL_FOLDER);
$workingDirectory = getcwd();
if ($workingDirectory === false) {
    echoFormatted('fRed', PHP_EOL . '[ERROR] ');
    echoFormatted('fDefault', 'Current working directory cannot be determined!');
    exit(1);
}
if (!chdir(EVAL_FOLDER)) {
    echoFormatted('fRed', PHP_EOL . '[ERROR] ');
    echoFormatted('fDefault', 'Cannot change the current working directory!');
    exit(1);
}
foreach ($keyFiles as $fileInfo) {
    if ($fileInfo->getExtension() == 'key') {
        echo "Backing up {$fileInfo->getFilename()}... ";
        if (!@rename($fileInfo->getFilename(), $fileInfo->getBasename('.key') . '.bak')) {
            $errorQueue[] = "Error backing up {$fileInfo->getFilename()}";
            echoFormatted('fRed', '[ERROR] ', 'fDefault', 'See details below!');
        } else {
            echoFormatted('fGreen', 'Done!');
        }
        echo PHP_EOL;
    }
}

if ($errorQueue) {
    echoFormatted('bold', 'The following errors occurred:' . PHP_EOL);
    foreach ($errorQueue as $error) {
        echoFormatted('bold', $error . PHP_EOL);
    }
    exit(1);
}

echo 'Backing Up other.xml...' . PHP_EOL;
$fileInfo = new SplFileInfo(OTHER_XML);
chdir($fileInfo->getPath());
if (!@copy($fileInfo->getFilename(), $fileInfo->getBasename('.xml') . '.bak')) {
    echoFormatted('fRed', PHP_EOL . '[ERROR] ');
    echoFormatted('fDefault', "Error backing up {$fileInfo->getFilename()}");
} else {
    echoFormatted('fGreen', 'Done!');
}
chdir($workingDirectory);
echo PHP_EOL;

echo 'Modifying files...';
$dom = new DOMDocument();
if (!@$dom->load(OTHER_XML)) {
    echoFormatted('fRed', PHP_EOL . '[ERROR] ');
    echoFormatted('fDefault', 'File loading failed!');
    exit(1);
}
$xpath         = new DOMXPath($dom);
$propertyNodes = $xpath->query(
    '/application/component[@name="PropertiesComponent"]/property[starts-with(@name, "evlsprt")]'
);
if (!$propertyNodes) {
    echoFormatted('fRed', PHP_EOL . '[ERROR] ');
    echoFormatted('fDefault', PHP_EOL . 'Unexpected File Format!');
    exit(1);
}
try {
    foreach ($propertyNodes as $property) {
        /** @var DOMNode $property */
        $property->parentNode->removeChild($property);
    }
    $newXML = $dom->saveXML($dom->documentElement);
    if ($newXML && @file_put_contents(OTHER_XML, $newXML)) {
        echoFormatted('fGreen', 'Done!');
    }
} catch (Exception $e) {
    echoFormatted('fRed', PHP_EOL . '[ERROR] ');
    echoFormatted('fDefault', PHP_EOL . 'Unable to modify file!');
    exit(1);
}
echo PHP_EOL;

echo 'Updating Registry...';
$HKCU = Registry::connect()->getCurrentUser();

try {
    deleteSubKeys($HKCU, HKCU_REG_PATH, true);
} catch (Exception $e) {
    echoFormatted('fRed', PHP_EOL . '[ERROR] ');
    echoFormatted('fDefault', $e->getMessage());
    exit(1);
}
echo ' Done!' . PHP_EOL;
echoFormatted('fWhite', PHP_EOL . 'Finished! ');
echo 'The evaluation period should now be reset.' . PHP_EOL;

/**
 * Delete key content from the registry.
 *
 * @param RegistryKey $sourceKey Key containing the key to delete.
 * @param string      $keyName   Qualified Name of the key to delete
 * @param bool        $include   True to also delete the key itself, False to delete the key content only.
 */
function deleteSubKeys(RegistryKey $sourceKey, string $keyName, bool $include = false)
{
    $rootKey  = $sourceKey->getSubKey($keyName);
    $iterator = $rootKey->getSubKeyIterator();
    foreach ($iterator as $subKeyName => $subKey) {
        // Delete each value of the sub key.
        foreach ($subKey->getValueIterator() as $valueName => $valueValue) {
            $subKey->deleteValue($valueName);
        }

        // Delete nested sub keys.
        deleteSubKeys($rootKey, $subKeyName, false);
        // Delete the current sub key.
        $rootKey->deleteSubKey($subKeyName);
    }

    if ($include) {
        // Delete defined sub key.
        $sourceKey->deleteSubKey($keyName);
    }
}

function echoFormatted(...$arguments)
{
    // Extract colors.
    $formats = $texts = [];
    foreach ($arguments as $key => $result) {
        $result = @(string)$result;
        if ($key % 2) {
            $texts[] = $result;
        } else {
            $formats[] = $result;
        }
    }

    if (count($formats) != count($texts)) {
        throw new InvalidArgumentException('Given formats and texts are out of balance!');
    }

    // Echo colorized texts.
    $availableFormats = [
        'default'      => 0,
        'bold'         => 1,
        'underlineYes' => 4,
        'underlineNo'  => 24,
        'negative'     => 7,
        'positive'     => 27,

        'fBlack'    => 30,
        'fRed'      => 31,
        'fGreen'    => 32,
        'fYellow'   => 33,
        'fBlue'     => 34,
        'fMagenta'  => 35,
        'fCyan'     => 36,
        'fWhite'    => 37,
        'fExtended' => 38,
        'fDefault'  => 39,

        'bBlack'    => 40,
        'bRed'      => 41,
        'bGreen'    => 42,
        'bYellow'   => 43,
        'bBlue'     => 44,
        'bMagenta'  => 45,
        'bCyan'     => 46,
        'bWhite'    => 47,
        'bExtended' => 48,
        'bDefault'  => 49,

        'fbBlack'   => 90,
        'fbRed'     => 91,
        'fbGreen'   => 92,
        'fbYellow'  => 93,
        'fbBlue'    => 94,
        'fbMagenta' => 95,
        'fbCyan'    => 96,
        'fbWhite'   => 97,

        'bbBlack'   => 100,
        'bbRed'     => 101,
        'bbGreen'   => 102,
        'bbYellow'  => 103,
        'bbBlue'    => 104,
        'bbMagenta' => 105,
        'bbCyan'    => 106,
        'bbWhite'   => 107,
    ];
    for ($i = 0; $i < count($formats); $i++) {
        echo "\033[{$availableFormats[$formats[$i]]}m", $texts[$i], "\033[37m";
    }
}

<?php
/*
 * Copyright (c) 2020. DigiLive
 *
 * This file is part of resetPhpStormEvaluationPeriod.
 *
 * resetPhpStormEvaluationPeriod is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * resetPhpStormEvaluationPeriod is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with resetPhpStormEvaluationPeriod.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace DigiLive\PhpStorm;


use DOMDocument;
use DOMNode;
use DOMXPath;
use Exception;
use FilesystemIterator;
use SplFileInfo;
use Windows\Registry\OperationFailedException;
use Windows\Registry\Registry;
use Windows\Registry\RegistryKey;

/**
 * Class PhpStorm
 *
 * Script to reset the 30-day evaluation period of PhpStorm.
 *
 * @package DigiLive\PhpStorm
 */
class PhpStorm
{
    /**
     * Contains the versions of PhpStorm which aren't supported by this class.
     */
    public const    UNSUPPORTED_VERSIONS = [];
    /**
     * Path to registry entries.
     */
    protected const REGISTRY_PATH = 'Software\JavaSoft\Prefs\jetbrains\phpStorm';
    /**
     * Log Entry type.
     */
    const           INFO = 'INFO';
    /**
     * Log Entry type.
     */
    const           SUCCESS = 'SUCCESS';
    /**
     * Log Entry type.
     */
    const           WARNING = 'WARNING';
    /**
     * Log Entry type.
     */
    const           ERROR = 'ERROR';
    /**
     * @var string Name of the file where the registry backup data is gonna stored.
     */
    public $regBackupFileName = 'registry.bak';
    /**
     * @var string Path to PhpStorm data in your Windows AppData folder.
     */
    protected $appDataFolder;
    /**
     * @var string Path to the eval folder of PhpStorm.
     */
    protected $evalFolder;
    /**
     * @var string Path to file other.xml of PhpStorm.
     */
    protected $otherXML;
    /**
     * @var string The working directory when the class was instantiated.
     */
    protected $cwd;
    /**
     * @var false|string Version of PhpStorm which is being processed.
     *                   Format: Major.Minor (E.g. 2020.1)
     */
    protected $version;
    /**
     * @var array Contains data about the reset or revert operations.
     */
    private $log = [];
    /**
     * @var bool Defines if the instance will reset the evaluation period or try to revert the changes previously made.
     */
    private $revert;

    /**
     * PhpStorm constructor.
     *
     * @param string     $version Version of PhpStorm to patch.
     * @param bool|false $revert  Set to true to revert the changes previously made.
     */
    public function __construct(string $version, $revert = false)
    {
        $this->version       = $version;
        $this->revert        = $revert;
        $this->appDataFolder = "{$_SERVER['APPDATA']}/JetBrains/PhpStorm$version";
        $this->evalFolder    = $this->appDataFolder . '/eval';
        $this->otherXML      = $this->appDataFolder . '/options/other.xml';
        $this->cwd           = getcwd();
    }

    /**
     * Get the processing log of the last execution to reset the evaluation period.
     *
     * @return array Log entries of the resetting process.
     */
    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * Validate the property values.
     *
     * The values of properties which are defining paths or files are validating against existence and accessibility.
     *
     * @throws Exception When any of the property values are invalid.
     */
    public function validateProperties()
    {
        if (in_array($this->version, self::UNSUPPORTED_VERSIONS)) {
            throw new Exception("Version {$this->version} isn't supported by this class.");
        }
        if ($this->cwd === false) {
            throw new Exception('The current working directory is invalid to operate on.');
        }
        if (!@is_dir($this->appDataFolder) || !@is_dir($this->evalFolder) || !@is_file($this->otherXML)) {
            throw new Exception('Folder structure is not as expected.');
        }
        if (!@is_writable($this->cwd) || !@is_writable($this->evalFolder) || !@is_writable($this->otherXML)) {
            throw new Exception('Writing to targets is not allowed.');
        }
    }

    /**
     * Start the resetting process.
     *
     * It will process the evaluation key files, settings and the registry where possible.
     *
     * @throws Exception When changing the current working directory fails.
     */
    public function resetEvaluation()
    {
        $this->log = [];
        $this->processKeyFiles();
        $this->processXML();
        $this->processRegistry();
    }

    /**
     * Start processing the registry.
     *
     * Depending on the PhpStorm::revert property, entries are backed up to a file or restored or restored from that
     * file.
     */
    private function processRegistry()
    {
        $process = $this->revert ? 'Restoring' : 'Backing Up';

        $this->writeLogEntry("$process Registry...");

        try {
            $HKCU = Registry::connect()->getCurrentUser();

            if (!$this->revert) {
                // Backup the registry.
                $registryKeys = json_encode($HKCU->getSubKeyRecursive(self::REGISTRY_PATH, true));
                if ($registryKeys === false) {
                    throw new OperationFailedException('An Error occurred while fetching registry keys and values!');
                }
                if (@file_put_contents($this->regBackupFileName, $registryKeys) === false) {
                    throw new Exception('An error occurred while writing registry keys and values to the backup file!');
                }

                $this->writeLogEntry("Done.", self::SUCCESS);
                $this->writeLogEntry('Updating Registry.');

                $HKCU->deleteSubKeyRecursive(self::REGISTRY_PATH);
                $this->writeLogEntry("Done.", self::SUCCESS);

                return;
            }

            // Restore the registry keys and values.
            $this->restoreRegistry();
            $this->writeLogEntry("Done.", self::SUCCESS);
        } catch (Exception $e) {
            $this->writeLogEntry($e->getMessage(), self::ERROR);
        }
    }

    /**
     * Write an entry into the processing log.
     *
     * @param mixed  $entry The entry to write into the log.
     * @param string $type  The type of entry.
     */
    protected function writeLogEntry($entry, string $type = self::INFO)
    {
        $this->log[] = [$type, $entry];
    }

    /**
     * Restore registry entries from a backup file.
     *
     * The parameters of this method are for recursive calls only.
     *
     * @param RegistryKey|null $parentKey    Key to restore the entries in.
     * @param object|null      $registryKeys Entries to restore.
     *
     * @throws Exception When a registry operation fails.
     */
    private function restoreRegistry(RegistryKey $parentKey = null, object $registryKeys = null)
    {
        try {
            $parentKey    = $parentKey ?? Registry::connect()->getCurrentUser();
            $registryKeys = $registryKeys ?? json_decode(@file_get_contents($this->regBackupFileName));
            if ($registryKeys === null) {
                throw new Exception('An error occurred while reading registry keys and values from the backup file!');
            }

            $currentKey = $parentKey->createSubKey($registryKeys->name);

            // Create subKeys.
            foreach ($registryKeys->keys as $subKeys) {
                $this->restoreRegistry($currentKey, $subKeys);
            }

            // Create Values.
            foreach ($registryKeys->values as $registryValue) {
                // Deliberately not caught.
                $currentKey->setValue($registryValue->name, $registryValue->value, $registryValue->type);
            }
        } catch (Exception $e) {
            // TODO: Create and throw custom Exceptions.
            throw $e;
        }
    }

    /**
     * Start processing the evaluation key files.
     *
     * Depending on the PhpStorm::revert property, the files are backed up or restored by renaming them.
     *
     * @throws Exception When the current working directory cannot be changed.
     */
    private function processKeyFiles()
    {
        $keyFiles       = new FilesystemIterator($this->evalFolder);
        $fileExtensions = $this->revert ? ['bak', 'key'] : ['key', 'bak'];
        $process        = $this->revert ? 'Restoring' : 'Backing Up';
        $fileCount      = 0;

        $this->chdir($this->evalFolder);
        $this->writeLogEntry("$process Evaluation Keys...");

        foreach ($keyFiles as $fileInfo) {
            if ($fileInfo->getExtension() == $fileExtensions[0]) {
                $fileCount++;
                if (!@rename(
                    $fileInfo->getFilename(),
                    $fileInfo->getBasename(".{$fileExtensions[0]}") . ".{$fileExtensions[1]}"
                )) {
                    $this->writeLogEntry($fileInfo->getFilename(), self::ERROR);
                } else {
                    $this->writeLogEntry($fileInfo->getFilename(), self::SUCCESS);
                }
            }
        }

        if (!$fileCount) {
            $this->writeLogEntry("No Evaluation Keys found.", self::WARNING);
        }

        $this->chdir($this->cwd);
    }

    /**
     * Change the current working directory.
     *
     * @param string $folder Path of the new working directory.
     *
     * @throws Exception When the current working directory cannot be changed.
     */
    private function chdir(string $folder)
    {
        if (!chdir($folder)) {
            throw new Exception('The current working directory cannot be changed.');
        }
    }

    /**
     * Start processing the XML file.
     *
     * Depending on the PhpStorm::revert property, the file is backed up and modified or restored by renaming the
     * backup file to the original name.
     *
     * @throws Exception When the current working directory cannot be changed.
     */
    private function processXML()
    {
        $process  = $this->revert ? 'Restoring' : 'Backing Up';
        $fileInfo = new SplFileInfo($this->otherXML);

        $this->chdir($fileInfo->getPath());
        $this->writeLogEntry("$process XML...");

        if (!$this->revert) {
            // Backup XML file.
            if (!@copy($fileInfo->getFilename(), $fileInfo->getBasename('.xml') . '.bak')) {
                $this->writeLogEntry("$process {$fileInfo->getFilename()} failed.", self::ERROR);
            } else {
                $this->writeLogEntry("{$fileInfo->getFilename()}... Done.", self::SUCCESS);
            }

            $this->writeLogEntry('Modifying XML...');

            $dom = new DOMDocument();
            if (!@$dom->load($this->otherXML)) {
                $this->writeLogEntry("File loading failed.", self::ERROR);
                $this->chdir($this->cwd);

                return;
            }

            $xpath         = new DOMXPath($dom);
            $propertyNodes = $xpath->query(
                '/application/component[@name="PropertiesComponent"]/property[starts-with(@name, "evlsprt")]'
            );
            if (!$propertyNodes) {
                $this->writeLogEntry("Unexpected File Format.", self::ERROR);
                $this->chdir($this->cwd);

                return;
            }

            try {
                foreach ($propertyNodes as $property) {
                    /** @var DOMNode $property */
                    $property->parentNode->removeChild($property);
                }
                $newXML = $dom->saveXML($dom->documentElement);
                if ($newXML && @file_put_contents($this->otherXML, $newXML)) {
                    $this->writeLogEntry("Done.", self::SUCCESS);
                }
            } catch (Exception $e) {
                $this->writeLogEntry("Unable to modify file.", self::ERROR);
            }

            $this->chdir($this->cwd);

            return;
        }

        // Revert changes.
        // Overwrite current XML file with backup file.
        if (!@rename($fileInfo->getBasename('.xml') . '.bak', $fileInfo->getBasename())) {
            $this->writeLogEntry("Error restoring {$fileInfo->getFilename()}", self::ERROR);

            $this->chdir($this->cwd);

            return;
        }

        $this->chdir($this->cwd);
        $this->writeLogEntry("Done.", self::SUCCESS);
    }
}

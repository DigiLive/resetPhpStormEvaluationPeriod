# Reset PhpStorm Evaluation Period

Script to reset the 30-day evaluation period of PhpStorm.

Once in a while it takes over 30 days before Jetbrains releases a new (EAP) version of PhpStorm, so you're only allowed
to run it for 30 minutes. This script will remove and modify local files to reset the evaluation period counter.

> This script isn't meant to be used for having a "free" license of PhpStorm, but meant for continuing your work while
Jetbrains has to release a new (EAP) version. If you intensively use PhpStorm or not participating the EAP, please
consider buying an appropriate license. The script will not change any of the files which belong to the PhpStorm
application itself. It removes evaluation keys and references to these keys in settings files and the Windows registry.

## Requirements

* Windows >=7 (Tested on W10)
* PhpStorm (Tested on 2020.1.1)
* PHP >= 7.2
  * ext-dom
* Composer

### Warning

**Use this at your own risk!**
It's your own responsibility to use this script, and you're accepting the possibility of anything that might break.

## Installation

1. Download or clone this repository to a folder of the machine on which you're using PhpStorm.
2. Enter the folder above and install composer dependencies.
   E.g. `php composer.phar install`.

## Running

Run the script by one of the commands below and follow any given instructions.
> The script expects user input while running. Not all terminals/consoles support sending data to STDIN.
Therefore, it's recommended to run the CLI from command.exe.
>
### Resetting

Run script `runMe.php -v <PhpStorm major.minorVersion>` with the CLI of php which is installed on your machine.

E.g. `php runMe.php -v 2020.1`

The script will create backup files which are used to revert the changes made on your machine.
> Rerunning the script, will overwrite at least some backup files which might result in setting you machine in an
invalid state when reverting.

### Reverting

Run script `runMe.php -v <PhpStorm major.minorVersion>` with the CLI of php which is installed on your machine.

E.g. `php runMe.php -v 2020.1 --revert`

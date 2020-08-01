<?php

/**
 * Echo an ANSI formatted value.
 *
 * The argument must be an array with an even amount of elements.
 * The odd element values define the format, the following element contains the value to echo.
 *
 * @see https://docs.microsoft.com/en-us/windows/console/console-virtual-terminal-sequences
 *
 * @param mixed ...$arguments Format and values to echo.
 */
function echoFormatted(...$arguments)
{
    // Extract colors.
    $formats = $texts = [];
    foreach ($arguments as $key => $value) {
        $value = @(string)$value;
        if ($key % 2) {
            $texts[] = $value;
        } else {
            $formats[] = $value;
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
        echo "\033[{$availableFormats[$formats[$i]]}m", $texts[$i], "\033[0m";
    }
}

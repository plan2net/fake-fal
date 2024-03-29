<?php

declare(strict_types=1);

namespace Plan2net\FakeFal\Utility;

/**
 * Class FileSignature
 *
 * @author  Wolfgang Klinger <wk@plan2.net>
 * @author  Martin Kutschker <mk@plan2.net>
 */
class FileSignature
{
    /**
     * see https://en.wikipedia.org/wiki/List_of_file_signatures,
     * https://www.garykessler.net/library/file_sigs.html
     * and other sources for a reference
     */
    protected static array $signatures = [
        'PDF' => '255044462d',
        'PPT' => 'D0CF11E0A1B11AE1',
        'DOC' => 'D0CF11E0A1B11AE1',
        'XLS' => 'D0CF11E0A1B11AE1',
        'ZIP' => '504B0304',
        'MP4' => '00000000667479704D534E56'
    ];

    /**
     * Returns a binary representation of the signature
     */
    public static function getSignature(string $fileExtension): ?string
    {
        $fileExtension = strtoupper($fileExtension);

        return isset(self::$signatures[$fileExtension]) ? hex2bin(self::$signatures[$fileExtension]) : null;
    }
}

<?php

namespace Plan2net\FakeFal\Utility;

/**
 * Class FileSignature
 *
 * @package Plan2net\FakeFal\Utility
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class FileSignature
{
    /**
     * see https://en.wikipedia.org/wiki/List_of_file_signatures
     * and other sources for a reference
     * @var array
     */
    static protected $signatures = [
        'BMP' => '42 4D',
        'EXE' => '4D 5A',
        'TAR' => '1F 9D',
        'MP3' => 'FF FB',
        'SWF' => '43 57 53',
        'ICO' => '00 00 01 00',
        'MPG' => '00 00 01 BA',
        'WEBM' => '1A 45 DF A3',
        'GZ' => '1F 8B 08 08',
        'TGZ' => '1F 9D 90 70',
        'PDF' => '25 50 44 46 2D 31 2E 0D 74 72 61 69 6C 65 72 3C 3C 2F 52 6F 6F 74 3C 3C 2F 50 61 67 65 73 3C 3C 2F 4B 69 64 73 5B 3C 3C 2F 4D 65 64 69 61 42 6F 78 5B 30 20 30 20 33 20 33 5D 3E 3E 5D 3E 3E 3E 3E 3E 3E',
        'WMV' => '30 26 B2 75',
        'FLV' => '46 4C 56 01',
        'TIFF' => '49 49 2A 00',
        'MIDI' => '4D 54 68 64',
        'DLL' => '4D 5A 90 00',
        'NES' => '4E 45 53 1A',
        'OGG' => '4F 67 67 53',
        'ZIP' => '50 4B 03 04',
        'ODT' => '50 4B 03 04',
        'ODS' => '50 4B 03 04',
        'ODP' => '50 4B 03 04',
        'DOCX' => '50 4B 03 04',
        'XLSX' => '50 4B 03 04',
        'PPTX' => '50 4B 03 04',
        'VSDX' => '50 4B 03 04',
        'JAR' => '5F 27 A8 89',
        'FLAC' => '66 4C 61 43',
        'IMG' => '7E 74 2C 01',
        'JPG' => 'FF D8 FF E0',
        'SYS' => 'FF FF FF FF',
        'ISO' => '43 44 30 30 31',
        '7ZIP' => '37 7A BC AF 27 1C',
        'GIF' => '47 49 46 38 39 61',
        'RAR' => '52 61 72 21 1A 07 00',
        '3GP' => '00 00 00 xx 66 74 79 70 33 67 70',
        'MP4' => '00 00 00 14 66 74 79 70 69 73 6F 6D',
        'MOV' => '00 00 00 14 66 74 79 70 71 74 20 20',
        'WAV' => '52 49 46 46 xx xx xx xx 57 41 56 45',
        'AVI' => '52 49 46 46 xx xx xx xx 41 56 49 20 4C 49 53 54',
        'PPT' => 'D0 CF 11 E0 A1 B1 1A E1',
        'DOC' => 'D0 CF 11 E0 A1 B1 1A E1',
        'XLS' => 'D0 CF 11 E0 A1 B1 1A E1',
        'PSD' => '38 42 50 53'
    ];

    /**
     * @param $fileExtension
     * @return string|null
     */
    static public function getSignature($fileExtension)
    {
        $fileExtension = strtoupper($fileExtension);
        if (isset(self::$signatures[$fileExtension])) {
            return self::$signatures[$fileExtension];
        }

        return null;
    }
}

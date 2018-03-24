<?php

namespace Plan2net\FakeFal\Resource\Processing;

use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Helper
 *
 * @package Plan2net\FakeFal\Resource\Processing
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class Helper
{
    /**
     * @param string $filepath
     * @param int    $width
     * @param int    $height
     */
    public function writeDimensionsOntoImage($filepath, $width, $height)
    {
        $fontSize = 10;
        $textWidth = 0;
        while ($textWidth < $width) {
            $offsets = $this->getGraphicalFunctionsObject()->ImageTTFBBoxWrapper($fontSize, 0,
                GeneralUtility::getFileAbsFileName('EXT:install/Resources/Private/Font/vera.ttf'), $width . 'x' . $height, []);
            $textWidth = abs($offsets[0] - $offsets[2]) + 20;
            $fitsTimes = floor($width / $textWidth);
            if ($fitsTimes > 1 && ($fontSize * $fitsTimes) > $fontSize) {
                $fontSize = $fontSize * $fitsTimes;
            }
            else {
                break;
            }
        }
        /* does nothing … ?
        $image = $this->getGraphicalFunctionsObject()->imageCreateFromFile($filepath);
        if ($image) {
            $white = imagecolorallocate($image, 255, 255, 255);
            $this->getGraphicalFunctionsObject()->ImageTTFTextWrapper($image, $fontSize, 0, 20, 0, $white, GeneralUtility::getFileAbsFileName('EXT:install/Resources/Private/Font/vera.ttf'), $width . 'x' . $height, []);
        }
        */
        // @todo
        // this currently creates a new image, but I want to write the text onto an existing one
        // centered and nice …
        $params = '-size ' . $width . 'x' . $height . ' -background lightgrey';
        $params .= ' -gravity Center -fill white -pointsize ' . $fontSize;
        $params .= ' caption:\'' . $width . 'x' . $height . '\'';
        $cmd = CommandUtility::imageMagickCommand('convert', $params . ' ' . escapeshellarg($filepath));
        CommandUtility::exec($cmd);
        // Change the permissions of the file
        GeneralUtility::fixPermissions($filepath);
    }

    /**
     * @return GraphicalFunctions
     */
    protected function getGraphicalFunctionsObject()
    {
        static $graphicalFunctionsObject = null;

        if ($graphicalFunctionsObject === null) {
            /** @var GraphicalFunctions $graphicalFunctionsObject */
            $graphicalFunctionsObject = GeneralUtility::makeInstance('TYPO3\CMS\Core\Imaging\GraphicalFunctions');
            $graphicalFunctionsObject->init();
        }

        return $graphicalFunctionsObject;
    }

}

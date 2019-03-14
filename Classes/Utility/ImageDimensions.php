<?php
declare(strict_types=1);

namespace Plan2net\FakeFal\Utility;

use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ImageDimensions
 * @package Plan2net\FakeFal\Utility
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class ImageDimensions implements SingletonInterface
{

    /**
     * @var GraphicalFunctions
     */
    protected $graphicalFunctions;

    /**
     * @param string $filepath
     * @param int $width
     * @param int $height
     */
    public function write(string $filepath, int $width, int $height)
    {
        $font = GeneralUtility::getFileAbsFileName('EXT:core/Resources/Private/Font/nimbus.ttf');
        // Calculate font size and text position (centered)
        $text = $width . 'x' . $height;
        $fontSize = $this->calculateFontSize($font, $width, $height, $text);
        list($x, $y) = $this->calculateTextPosition($font, $fontSize, $width, $height, $text);
        // Write text onto image
        $graphicalFunctions = $this->getGraphicalFunctionsObject();
        $image = $graphicalFunctions->imageCreateFromFile($filepath);
        if ($image) {
            $black = imagecolorallocate($image, 100, 100, 100);
            imagettftext($image, $fontSize, 0, $x, $y, $black, $font, $text);
            $graphicalFunctions->ImageWrite($image, $filepath);
        }
    }

    /**
     * @return GraphicalFunctions
     */
    protected function getGraphicalFunctionsObject(): GraphicalFunctions
    {
        if ($this->graphicalFunctions === null) {
            /** @var GraphicalFunctions $graphicalFunctionsObject */
            $this->graphicalFunctions = GeneralUtility::makeInstance(GraphicalFunctions::class);
            $this->graphicalFunctions->init();
        }

        return $this->graphicalFunctions;
    }

    /**
     * Calculate font size based on font, image width and the text
     * so the text fits the image with some margin
     *
     * @param string $font
     * @param int $width
     * @param int $height
     * @param string $text
     * @return int
     */
    protected function calculateFontSize(string $font, int $width, int $height, string $text): int
    {
        // add some margin
        $width -= $width * 0.1;
        $height -= $height * 0.1;

        $fontSize = 12; // default
        $textWidth = 0;
        while ($textWidth < $width) {
            $offsets = imagettfbbox($fontSize, 0, $font, $text);
            $textWidth = abs($offsets[4]);
            $textHeight = abs($offsets[5]);
            $fitsTimes = $width / $textWidth;
            if (($height / $textHeight) < $fitsTimes) {
                $fitsTimes = $height / $textHeight;
            }
            if ($fitsTimes > 1 && ($fontSize * $fitsTimes) > $fontSize) {
                $fontSize *= $fitsTimes;
            } else {
                break;
            }
        }

        return $fontSize < 12 ? 12 : (int)$fontSize;
    }

    /**
     * Calculate text position (centered)
     *
     * @param string $font
     * @param int $fontSize
     * @param int $width
     * @param int $height
     * @param string $text
     * @return array
     */
    protected function calculateTextPosition(
        string $font,
        int $fontSize,
        int $width,
        int $height,
        string $text
    ): array {
        $textBox = imagettfbbox($fontSize, 0, $font, $text);
        $textWidth = $textBox[2] - $textBox[0];
        $textHeight = $textBox[7] - $textBox[1];

        return [
            (int)floor(($width / 2) - ($textWidth / 2)) - 1,
            (int)floor(($height / 2) - ($textHeight / 2))
        ];
    }

}

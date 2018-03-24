<?php

namespace Plan2net\FakeFal\Resource\Processing;

use TYPO3\CMS\Core\Resource\File;

/**
 * Class LocalPreviewHelper
 *
 * @package Plan2net\FakeFal\Resource\Processing
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class LocalPreviewHelper extends \TYPO3\CMS\Core\Resource\Processing\LocalPreviewHelper
{

    /**
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @param array $configuration
     * @param string $targetFilePath
     * @return array
     */
    protected function generatePreviewFromFile(File $file, array $configuration, $targetFilePath)
    {
        $result = parent::generatePreviewFromFile($file, $configuration, $targetFilePath);

        if (!empty($result)) {
            /** @var \Plan2net\FakeFal\Resource\Processing\Helper $processingHelper */
            $processingHelper = GeneralUtility::makeInstance('Plan2net\FakeFal\Resource\Processing\Helper');
            $processingHelper->writeDimensionsOntoImage($result['file'], $result['width'], $result['height']);
        }

        return $result;
    }
}

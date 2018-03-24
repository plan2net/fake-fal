<?php

namespace Plan2net\FakeFal\Resource\Processing;

use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LocalCropScaleMaskHelper
 *
 * @package Plan2net\FakeFal\Resource\Processing
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class LocalCropScaleMaskHelper extends \TYPO3\CMS\Core\Resource\Processing\LocalCropScaleMaskHelper
{
    /**
     * @param \TYPO3\CMS\Core\Resource\Processing\TaskInterface $task
     * @return array|null
     */
    public function process(TaskInterface $task)
    {
        $result = parent::process($task);

        // if the result is empty, we try to use the original file
        if (empty($result) && $task->getSourceFile() &&
            @is_file($task->getSourceFile()->getForLocalProcessing(false))) {
            $result = [
                'filePath' => $task->getSourceFile()->getForLocalProcessing(false),
                'width' => $task->getSourceFile()->getProperty('width'),
                'height' => $task->getSourceFile()->getProperty('height')
            ];
        }

        if ($result) {
            /** @var \Plan2net\FakeFal\Resource\Processing\Helper $processingHelper */
            $processingHelper = GeneralUtility::makeInstance('Plan2net\FakeFal\Resource\Processing\Helper');
            $processingHelper->writeDimensionsOntoImage($result['filePath'], $result['width'], $result['height']);
        }

        return $result;
    }
}

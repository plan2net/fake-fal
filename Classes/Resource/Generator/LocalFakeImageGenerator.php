<?php
declare(strict_types=1);

namespace Plan2net\FakeFal\Resource\Generator;

use Plan2net\FakeFal\Utility\ImageDimensions;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LocalFakeImageGenerator
 * @package Plan2net\FakeFal\Resource\Generator
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class LocalFakeImageGenerator implements ImageGeneratorInterface
{

    /**
     * Generate a new local file based on File $file metadata
     * in $filePath path
     *
     * @param File $file
     * @param string $filePath
     * @return mixed
     */
    public function generate(File $file, string $filePath): string
    {
        list($width, $height) = $this->getFileDimensions($file);
        if ($width && $height) {
            $params = '-size ' . $width . 'x' . $height . ' xc:lightgrey';
            $cmd = CommandUtility::imageMagickCommand(
                'convert',
                $params . ' ' . escapeshellarg($filePath)
            );
            CommandUtility::exec($cmd);
            GeneralUtility::fixPermissions($filePath);

            /** @var ImageDimensions $imageDimensionsWriter */
            $imageDimensionsWriter = GeneralUtility::makeInstance(ImageDimensions::class);
            $imageDimensionsWriter->write($filePath, $width, $height);
        }

        return $filePath;
    }

    /**
     * Return an array with width and height
     *
     * @param File $file
     * @return array
     */
    protected function getFileDimensions(File $file): array
    {
        /** @var MetaDataRepository $metaDataRepository */
        $metaDataRepository = GeneralUtility::makeInstance(MetaDataRepository::class);
        $metaData = $metaDataRepository->findByFile($file);

        return [
            (int)$metaData['width'],
            (int)$metaData['height']
        ];
    }

}

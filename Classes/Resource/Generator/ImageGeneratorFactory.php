<?php
declare(strict_types=1);

namespace Plan2net\FakeFal\Resource\Generator;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ImageGeneratorFactory
 * @package Plan2net\FakeFal\Resource\Generator
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class ImageGeneratorFactory
{

    /**
     * @param string $generatorType
     * @return ImageGeneratorInterface
     * @throws \InvalidArgumentException
     */
    public static function create(string $generatorType): ImageGeneratorInterface
    {
        /** @var ImageGeneratorInterface $generator */
        $generator = GeneralUtility::makeInstance($generatorType);

        if (!($generator instanceof ImageGeneratorInterface)) {
            throw new \InvalidArgumentException($generatorType . ' not found or no instance of ImageGeneratorInterface');
        }

        return $generator;
    }

}

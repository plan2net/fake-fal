<?php
declare(strict_types=1);

namespace Plan2net\FakeFal\Resource\Generator;

use TYPO3\CMS\Core\Resource\File;

/**
 * Interface ImageGeneratorInterface
 * @package Plan2net\FakeFal\Resource\Generator
 */
interface ImageGeneratorInterface
{

    /**
     * @param File $file
     * @param string $filePath
     * @return mixed
     */
    public function generate(File $file, string $filePath): string;

}

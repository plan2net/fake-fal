<?php
declare(strict_types=1);

namespace Plan2net\FakeFal\Utility;

/**
 * Class Configuration
 * @package Plan2net\FakeFal\Utility
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class Configuration
{

    /**
     * Returns the whole extension configuration or a specific property
     *
     * @param string|null $key
     * @return array|string|null
     */
    public static function getExtensionConfiguration($key = null)
    {
        $configuration = [];
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fake_fal'])) {
            $configuration = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fake_fal']);
        }
        if (is_string($key)) {
            if (isset($configuration[$key])) {
                return (string)$configuration[$key];
            }
            return null;
        }

        return $configuration;
    }

}

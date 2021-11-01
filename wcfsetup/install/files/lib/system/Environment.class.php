<?php

namespace wcf\system;

/**
 * Provides functions to interact with the system/hosting enviroment.
 *
 * @author  Tim Duesterhus
 * @copyright   2001-2021 WoltLab GmbH
 * @license GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package WoltLabSuite\Core\System
 */
final class Environment
{
    /**
     * Returns a string representing key system information where
     * changes might affect the software's behavior.
     *
     * Current information includes the location within the file system,
     * the PHP minor version and the MySQL fork and minor version.
     */
    public static function getSystemId(): string
    {
        $ctx = \hash_init('sha256');

        $sqlVersion = WCF::getDB()->getVersion();

        foreach (
            [
                // 1) The path to this class as a proxy for the document root.
                \sprintf("__FILE__ %s", __FILE__),
                // 2) The PHP minor version.
                \sprintf('PHP %s', self::getMinorVersion(\PHP_VERSION)),
                // 3) The MySQL fork and minor version.
                \sprintf(
                    '%s %s',
                    \stripos($sqlVersion, 'MariaDB') !== false ? 'MariaDB' : 'MySQL',
                    self::getMinorVersion($sqlVersion)
                ),
            ] as $field
        ) {
            \hash_update($ctx, $field);
        }

        return \hash_final($ctx);
    }

    /**
     * Extracts the minor version from the given version string.
     */
    private static function getMinorVersion(string $version): string
    {
        return \preg_replace('/^(\d+\.\d+)\..*$/', '\\1', $version);
    }
}

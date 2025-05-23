<?php

namespace wcf\action;

use Laminas\Diactoros\Response\JsonResponse;
use wcf\system\background\BackgroundQueueHandler;
use wcf\system\WCF;

/**
 * Performs background queue jobs.
 *
 * @author  Tim Duesterhus
 * @copyright   2001-2023 WoltLab GmbH
 * @license GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @since   3.0
 */
final class BackgroundQueuePerformAction extends AbstractAction
{
    /**
     * number of jobs that will be processed per invocation
     */
    public static int $jobsPerRun = 10;

    /**
     * @inheritDoc
     */
    public function execute()
    {
        parent::execute();

        for ($i = 0; $i < self::$jobsPerRun; $i++) {
            // Reset the memory usage for each background job, allowing to measure each individual
            // background job's memory usage without a memory-heavy job skewing the numbers for
            // the following jobs.
            if (\PHP_VERSION_ID >= 80200) {
                \memory_reset_peak_usage();
            }

            if (BackgroundQueueHandler::getInstance()->performNextJob() === false) {
                // there were no more jobs
                break;
            }
        }

        WCF::getSession()->deleteIfNew();

        return new JsonResponse(
            BackgroundQueueHandler::getInstance()->getRunnableCount()
        );
    }
}

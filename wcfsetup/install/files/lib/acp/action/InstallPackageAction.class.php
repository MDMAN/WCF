<?php

namespace wcf\acp\action;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use wcf\action\AbstractSecureAction;
use wcf\data\application\Application;
use wcf\data\package\installation\queue\PackageInstallationQueue;
use wcf\system\cache\CacheHandler;
use wcf\system\exception\IllegalLinkException;
use wcf\system\package\PackageInstallationDispatcher;
use wcf\system\search\SearchIndexManager;
use wcf\system\version\VersionTracker;
use wcf\system\WCF;
use wcf\util\StringUtil;

/**
 * Handles an AJAX-based package installation.
 *
 * @author  Tim Duesterhus, Alexander Ebert
 * @copyright   2001-2022 WoltLab GmbH
 * @license GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package WoltLabSuite\Core\Acp\Action
 */
class InstallPackageAction extends AbstractSecureAction
{
    /**
     * current step
     * @var string
     */
    public $step = '';

    /**
     * current node
     * @var string
     */
    public $node = '';

    /**
     * PackageInstallationDispatcher object
     * @var PackageInstallationDispatcher
     */
    public $installation;

    /**
     * PackageInstallationQueue object
     * @var PackageInstallationQueue
     */
    public $queue;

    /**
     * current queue id
     * @var int
     */
    public $queueID = 0;

    /**
     * @inheritDoc
     */
    public function readParameters()
    {
        parent::readParameters();

        if (isset($_REQUEST['step'])) {
            $this->step = StringUtil::trim($_REQUEST['step']);
        }

        switch ($this->step) {
            case 'install':
            case 'prepare':
            case 'rollback':
                // valid steps
                break;

            default:
                throw new IllegalLinkException();
                break;
        }

        if (isset($_POST['node'])) {
            $this->node = StringUtil::trim($_POST['node']);
        }
        if (isset($_POST['queueID'])) {
            $this->queueID = \intval($_POST['queueID']);
        }
        $this->queue = new PackageInstallationQueue($this->queueID);

        if (!$this->queue->queueID) {
            throw new IllegalLinkException();
        }

        $this->installation = new PackageInstallationDispatcher($this->queue);
    }

    public function execute()
    {
        parent::execute();

        $methodName = 'step' . StringUtil::firstCharToUpperCase($this->step);

        $response = $this->{$methodName}();

        $this->executed();

        return $response;
    }

    /**
     * Executes installation based upon nodes.
     */
    protected function stepInstall(): ResponseInterface
    {
        $step = $this->installation->install($this->node);
        $queueID = $this->installation->nodeBuilder->getQueueByNode(
            $this->installation->queue->processNo,
            $step->getNode()
        );

        if ($step->hasDocument()) {
            return new JsonResponse([
                'currentAction' => $this->getCurrentAction($queueID),
                'innerTemplate' => $step->getTemplate(),
                'node' => $step->getNode(),
                'progress' => $this->installation->nodeBuilder->calculateProgress($this->node),
                'step' => 'install',
                'queueID' => $queueID,
            ]);
        } else {
            if ($step->getNode() == '') {
                // perform final actions
                $this->installation->completeSetup();
                $this->finalize();

                WCF::resetZendOpcache();

                // show success
                return new JsonResponse([
                    'currentAction' => $this->getCurrentAction(null),
                    'progress' => 100,
                    'redirectLocation' => $this->getRedirectLink(),
                    'step' => 'success',
                ]);
            }

            WCF::resetZendOpcache();

            // continue with next node
            return new JsonResponse([
                'currentAction' => $this->getCurrentAction($queueID),
                'step' => 'install',
                'node' => $step->getNode(),
                'progress' => $this->installation->nodeBuilder->calculateProgress($this->node),
                'queueID' => $queueID,
            ]);
        }
    }

    /**
     * Returns the link to the page to which the user is redirected after
     * the installation finished.
     *
     * @return  string
     * @since   5.2
     */
    protected function getRedirectLink()
    {
        // get domain path
        $sql = "SELECT  *
                FROM    wcf" . WCF_N . "_application
                WHERE   packageID = ?";
        $statement = WCF::getDB()->prepareStatement($sql);
        $statement->execute([1]);

        /** @var Application $application */
        $application = $statement->fetchObject(Application::class);

        $controller = 'package-list';
        if (WCF::getSession()->getVar('__wcfSetup_completed')) {
            $controller = 'first-time-setup';

            WCF::getSession()->unregister('__wcfSetup_completed');
        }

        // Do not use the LinkHandler here as it is sort of unreliable during WCFSetup.
        return $application->getPageURL() . "acp/index.php?{$controller}/";
    }

    /**
     * Prepares the installation process.
     */
    protected function stepPrepare(): ResponseInterface
    {
        // update package information
        $this->installation->updatePackage();

        // clean-up previously created nodes
        $this->installation->nodeBuilder->purgeNodes();

        if ($this->installation->getAction() === 'update' && $this->queue->package === 'com.woltlab.wcf') {
            WCF::checkWritability();
        }

        // create node tree
        $this->installation->nodeBuilder->buildNodes();
        $nextNode = $this->installation->nodeBuilder->getNextNode();
        $queueID = $this->installation->nodeBuilder->getQueueByNode($this->installation->queue->processNo, $nextNode);

        WCF::getTPL()->assign([
            'installationType' => $this->queue->action,
            'packageName' => $this->installation->queue->packageName,
        ]);

        return new JsonResponse([
            'template' => WCF::getTPL()->fetch('packageInstallationStepPrepare'),
            'step' => 'install',
            'node' => $nextNode,
            'currentAction' => $this->getCurrentAction($queueID),
            'progress' => 0,
            'queueID' => $queueID,
        ]);
    }

    /**
     * Sets the parameters required to perform a rollback.
     */
    protected function stepRollback(): ResponseInterface
    {
        return new JsonResponse([
            'packageID' => $this->queue->packageID,
            'step' => 'rollback',
        ]);
    }

    /**
     * Returns current action by queue id.
     *
     * @param int $queueID
     * @return  string
     */
    protected function getCurrentAction($queueID)
    {
        if ($queueID === null) {
            // success message
            $currentAction = WCF::getLanguage()->get('wcf.acp.package.installation.step.' . $this->queue->action . '.success');
        } else {
            // build package name
            $packageName = $this->installation->nodeBuilder->getPackageNameByQueue($queueID);
            $installationType = $this->installation->nodeBuilder->getInstallationTypeByQueue($queueID);
            $currentAction = WCF::getLanguage()->getDynamicVariable(
                'wcf.acp.package.installation.step.' . $installationType,
                ['packageName' => $packageName]
            );
        }

        return $currentAction;
    }

    /**
     * Clears resources after successful installation.
     */
    protected function finalize()
    {
        // create search index tables
        SearchIndexManager::getInstance()->createSearchIndices();

        VersionTracker::getInstance()->createStorageTables();

        CacheHandler::getInstance()->flushAll();
    }
}

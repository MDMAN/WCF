<?php

namespace wcf\acp\form;

use wcf\data\user\option\UserOption;
use wcf\data\user\option\UserOptionAction;
use wcf\form\AbstractForm;
use wcf\system\exception\IllegalLinkException;
use wcf\system\language\I18nHandler;
use wcf\system\WCF;

/**
 * Shows the user option edit form.
 *
 * @author  Marcel Werk
 * @copyright   2001-2019 WoltLab GmbH
 * @license GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 */
class UserOptionEditForm extends UserOptionAddForm
{
    /**
     * @inheritDoc
     */
    public $activeMenuItem = 'wcf.acp.menu.link.user.option.list';

    /**
     * user option id
     * @var int
     */
    public $optionID = 0;

    /**
     * user option object
     * @var UserOption
     */
    public $userOption;

    /**
     * @inheritDoc
     */
    public function readParameters()
    {
        parent::readParameters();

        if (isset($_REQUEST['id'])) {
            $this->optionID = \intval($_REQUEST['id']);
        }
        $this->userOption = new UserOption($this->optionID);
        if (!$this->userOption->optionID) {
            throw new IllegalLinkException();
        }

        if ($this->userOption->optionName === 'aboutMe') {
            self::$availableOptionTypes[] = 'aboutMe';
        }
    }

    /**
     * @inheritDoc
     */
    public function readFormParameters()
    {
        $this->optionType = $this->userOption->optionType;

        parent::readFormParameters();

        $this->optionType = $this->userOption->optionType;
    }

    /**
     * @inheritDoc
     */
    protected function setDefaultOutputClass() {}

    /**
     * @inheritDoc
     */
    public function save()
    {
        AbstractForm::save();

        I18nHandler::getInstance()->save(
            'optionName',
            'wcf.user.option.' . $this->userOption->optionName,
            'wcf.user.option'
        );
        I18nHandler::getInstance()->save(
            'optionDescription',
            'wcf.user.option.' . $this->userOption->optionName . '.description',
            'wcf.user.option'
        );

        $additionalData = \is_array($this->userOption->additionalData) ? $this->userOption->additionalData : [];
        if ($this->optionType === 'message' && !isset($additionalData['messageObjectType'])) {
            $additionalData['messageObjectType'] = 'com.woltlab.wcf.user.option.generic';
        }

        $this->objectAction = new UserOptionAction([$this->userOption], 'update', [
            'data' => \array_merge($this->additionalFields, [
                'categoryName' => $this->categoryName,
                'optionType' => $this->optionType,
                'defaultValue' => $this->defaultValue,
                'showOrder' => $this->showOrder,
                'outputClass' => $this->outputClass,
                'validationPattern' => $this->validationPattern,
                'selectOptions' => $this->selectOptions,
                'required' => $this->required,
                'askDuringRegistration' => $this->askDuringRegistration,
                'searchable' => $this->searchable,
                'editable' => $this->editable,
                'visible' => $this->visible,
                'additionalData' => !empty($additionalData) ? \serialize($additionalData) : '',
                'labeledUrl' => $this->labeledUrl,
            ]),
        ]);
        $this->objectAction->executeAction();
        $this->saved();

        WCF::getTPL()->assign('success', true);
    }

    /**
     * @inheritDoc
     */
    public function readData()
    {
        parent::readData();

        I18nHandler::getInstance()->setOptions(
            'optionName',
            1,
            'wcf.user.option.' . $this->userOption->optionName,
            'wcf.user.option.option\d+'
        );
        I18nHandler::getInstance()->setOptions(
            'optionDescription',
            1,
            'wcf.user.option.' . $this->userOption->optionName . '.description',
            'wcf.user.option.option\d+.description'
        );

        if (empty($_POST)) {
            $this->categoryName = $this->userOption->categoryName;
            $this->optionType = $this->userOption->optionType;
            $this->defaultValue = $this->userOption->defaultValue;
            $this->validationPattern = $this->userOption->validationPattern;
            $this->selectOptions = $this->userOption->selectOptions;
            $this->required = $this->userOption->required;
            $this->askDuringRegistration = $this->userOption->askDuringRegistration;
            $this->editable = $this->userOption->editable;
            $this->visible = $this->userOption->visible;
            $this->searchable = $this->userOption->searchable;
            $this->showOrder = $this->userOption->showOrder;
            $this->outputClass = $this->userOption->outputClass;
            $this->labeledUrl = $this->userOption->labeledUrl;
        }
    }

    /**
     * @inheritDoc
     */
    public function assignVariables()
    {
        parent::assignVariables();

        I18nHandler::getInstance()->assignVariables(!empty($_POST));

        WCF::getTPL()->assign([
            'action' => 'edit',
            'optionID' => $this->optionID,
            'userOption' => $this->userOption,
        ]);
    }
}

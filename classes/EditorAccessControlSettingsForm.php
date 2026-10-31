<?php

namespace APP\plugins\generic\editorAccessControl\classes;

use APP\facades\Repo;
use APP\plugins\generic\editorAccessControl\EditorAccessControlPlugin;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class EditorAccessControlSettingsForm extends Form
{
	private EditorAccessControlPlugin $plugin;
	private int $contextId;

	public function __construct(EditorAccessControlPlugin $plugin, int $contextId)
	{
		$this->plugin = $plugin;
		$this->contextId = $contextId;

		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	public function initData(): void
	{
		$plugin = $this->plugin;
		$contextId = $this->contextId;

		$valSettings = $plugin->getSetting($contextId, 'restrictSettings');
		$valUsers = $plugin->getSetting($contextId, 'restrictUsers');
		$valTools = $plugin->getSetting($contextId, 'restrictTools');
		$valReadOnly = $plugin->getSetting($contextId, 'readOnlyWorkflow');

		$this->setData('restrictedUsers', (string) $plugin->getSetting($contextId, 'restrictedUsers'));
		$this->setData('restrictedUserGroups', (array) ($plugin->getSetting($contextId, 'restrictedUserGroups') ?: []));
		$this->setData('restrictSettings', $valSettings !== null ? (bool) $valSettings : true);
		$this->setData('restrictUsers', $valUsers !== null ? (bool) $valUsers : true);
		$this->setData('restrictTools', $valTools !== null ? (bool) $valTools : true);
		$this->setData('readOnlyWorkflow', $valReadOnly !== null ? (bool) $valReadOnly : true);

		parent::initData();
	}

	public function readInputData(): void
	{
		$this->readUserVars([
			'restrictedUsers',
			'restrictedUserGroups',
			'restrictSettings',
			'restrictUsers',
			'restrictTools',
			'readOnlyWorkflow',
		]);
	}

	public function fetch($request, $template = null, $display = false): string
	{
		$templateMgr = TemplateManager::getManager($request);

		$groups = Repo::userGroup()->getCollector()
			->filterByContextIds([$this->contextId])
			->getMany();

		$groupOptions = [];
		foreach ($groups as $group) {
			$groupOptions[$group->getId()] = $group->getLocalizedName();
		}

		$templateMgr->assign([
			'pluginName' => $this->plugin->getName(),
			'userGroupOptions' => $groupOptions,
		]);

		return parent::fetch($request, $template, $display);
	}

	public function execute(...$functionArgs)
	{
		$plugin = $this->plugin;
		$contextId = $this->contextId;

		$plugin->updateSetting($contextId, 'restrictedUsers', (string) $this->getData('restrictedUsers'), 'string');
		$plugin->updateSetting($contextId, 'restrictedUserGroups', (array) ($this->getData('restrictedUserGroups') ?: []), 'object');
		$plugin->updateSetting($contextId, 'restrictSettings', (bool) $this->getData('restrictSettings'), 'bool');
		$plugin->updateSetting($contextId, 'restrictUsers', (bool) $this->getData('restrictUsers'), 'bool');
		$plugin->updateSetting($contextId, 'restrictTools', (bool) $this->getData('restrictTools'), 'bool');
		$plugin->updateSetting($contextId, 'readOnlyWorkflow', (bool) $this->getData('readOnlyWorkflow'), 'bool');

		parent::execute(...$functionArgs);
	}
}

<?php

namespace APP\plugins\generic\editorAccessControl;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\plugins\generic\editorAccessControl\classes\EditorAccessControlSettingsForm;
use APP\template\TemplateManager;
use PKP\core\APIRouter;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\notification\PKPNotification;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Validation;
use PKP\user\User;

class EditorAccessControlPlugin extends GenericPlugin
{
	public function register($category, $path, $mainContextId = null)
	{
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId)) {
			Hook::add('LoadHandler', [$this, 'callbackLoadHandler']);
			Hook::add('LoadComponentHandler', [$this, 'callbackLoadComponentHandler']);
			Hook::add('Dispatcher::dispatch', [$this, 'callbackDispatcherDispatch']);
			Hook::add('TemplateManager::display', [$this, 'callbackTemplateDisplay']);
		}
		return $success;
	}

	public function isSitePlugin()
	{
		return false;
	}

	public function getHideManagement()
	{
		$request = Application::get()->getRequest();
		if (!$request->getContext()) {
			return true;
		}
		return parent::getHideManagement();
	}

	public function getDisplayName()
	{
		return __('plugins.generic.editorAccessControl.displayName');
	}

	public function getDescription()
	{
		return __('plugins.generic.editorAccessControl.description');
	}

	public function getActions($request, $verb)
	{
		$context = $request->getContext();
		if (!$context) {
			return [];
		}

		$router = $request->getRouter();
		$user = $request->getUser();
		$canManage = $this->getEnabled($context->getId()) && !$this->isUserRestricted($request, $user);

		return array_merge(
			$canManage ? [
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			] : [],
			parent::getActions($request, $verb)
		);
	}

	public function manage($args, $request)
	{
		$user = $request->getUser();
		if ($this->isUserRestricted($request, $user)) {
			return new JSONMessage(false, __('common.permissionDenied'));
		}

		switch ($request->getUserVar('verb')) {
			case 'settings':
				$context = $request->getContext();
				if (!$context) {
					return new JSONMessage(false);
				}
				$form = new EditorAccessControlSettingsForm($this, $context->getId());

				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						$notificationMgr = new NotificationManager();
						$notificationMgr->createTrivialNotification(
							$request->getUser()->getId(),
							PKPNotification::NOTIFICATION_TYPE_SUCCESS,
							['contents' => __('common.changesSaved')]
						);
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	public function isUserRestricted(PKPRequest $request, ?User $user): bool
	{
		if (!$user || Validation::isSiteAdmin()) {
			return false;
		}

		$context = $request->getContext();
		if (!$context) {
			return false;
		}
		$contextId = $context->getId();

		$restrictedUsersRaw = (string) $this->getSetting($contextId, 'restrictedUsers');
		$restrictedTargets = array_filter(array_map('trim', preg_split('/[\r\n]+/', $restrictedUsersRaw)));
		$username = strtolower($user->getUsername());
		$email = strtolower($user->getEmail());

		foreach ($restrictedTargets as $target) {
			$targetLower = strtolower($target);
			if ($targetLower === $username || $targetLower === $email) {
				return true;
			}
		}

		$restrictedGroups = (array) $this->getSetting($contextId, 'restrictedUserGroups');
		if (!empty($restrictedGroups)) {
			$userGroups = Repo::userGroup()->userUserGroups($user->getId(), $contextId);
			foreach ($userGroups as $userGroup) {
				if (in_array((int) $userGroup->getId(), array_map('intval', $restrictedGroups), true)) {
					return true;
				}
			}
		}

		return false;
	}

	public function callbackLoadHandler(string $hookName, array $params): bool
	{
		$page = &$params[0];
		$op = &$params[1];
		$request = Application::get()->getRequest();
		$user = $request->getUser();

		if (!$this->isUserRestricted($request, $user)) {
			return false;
		}

		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		$valSettings = $this->getSetting($contextId, 'restrictSettings');
		$restrictSettings = $valSettings !== null ? (bool) $valSettings : true;

		$valUsers = $this->getSetting($contextId, 'restrictUsers');
		$restrictUsers = $valUsers !== null ? (bool) $valUsers : true;

		$valTools = $this->getSetting($contextId, 'restrictTools');
		$restrictTools = $valTools !== null ? (bool) $valTools : true;

		$args = (array) $request->getRequestedArgs();

		if ($page === 'admin') {
			$request->redirect(null, 'submissions');
			return true;
		}

		if ($page === 'management') {
			if ($op === 'settings') {
				$settingType = $args[0] ?? '';
				if ($settingType === 'access' && ($restrictSettings || $restrictUsers)) {
					$request->redirect(null, 'submissions');
					return true;
				}
				if ($restrictSettings && in_array($settingType, ['context', 'website', 'workflow', 'distribution'], true)) {
					$request->redirect(null, 'submissions');
					return true;
				}
			}
			if ($op === 'tools' && $restrictTools) {
				$request->redirect(null, 'submissions');
				return true;
			}
		}

		return false;
	}

	public function callbackLoadComponentHandler(string $hookName, array $params): bool
	{
		$component = &$params[0];
		$op = &$params[1];
		$request = Application::get()->getRequest();
		$user = $request->getUser();

		if (!$this->isUserRestricted($request, $user)) {
			return false;
		}

		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		$valSettings = $this->getSetting($contextId, 'restrictSettings');
		$restrictSettings = $valSettings !== null ? (bool) $valSettings : true;

		$valUsers = $this->getSetting($contextId, 'restrictUsers');
		$restrictUsers = $valUsers !== null ? (bool) $valUsers : true;

		$valTools = $this->getSetting($contextId, 'restrictTools');
		$restrictTools = $valTools !== null ? (bool) $valTools : true;

		$valReadOnly = $this->getSetting($contextId, 'readOnlyWorkflow');
		$readOnlyWorkflow = $valReadOnly !== null ? (bool) $valReadOnly : true;

		$compLower = strtolower($component);
		$opLower = strtolower((string) $op);

		$isBlocked = false;

		if ($restrictSettings && (
			str_contains($compLower, 'grid.settings.') ||
			str_contains($compLower, 'tab.settings.') ||
			str_contains($compLower, 'tab.context.') ||
			str_contains($compLower, 'grid.admin.')
		)) {
			$isBlocked = true;
		}

		if ($restrictUsers && (
			str_contains($compLower, 'grid.users.user.') ||
			str_contains($compLower, 'grid.settings.user.') ||
			in_array($opLower, ['signinasuser', 'edituser', 'deleteuser', 'disableuser', 'updateuser'], true)
		)) {
			$isBlocked = true;
		}

		if ($restrictTools && (
			str_contains($compLower, 'grid.tools.') ||
			str_contains($compLower, 'controllers.tools.') ||
			str_contains($compLower, 'controllers.grid.tools.')
		)) {
			$isBlocked = true;
		}

		if ($readOnlyWorkflow && (
			(str_contains($compLower, 'reviewer') && in_array($opLower, ['unassignreviewer', 'sendreviews', 'resendreviewrequest'], true)) ||
			$opLower === 'deletefile'
		)) {
			$isBlocked = true;
		}

		if ($isBlocked) {
			header('HTTP/1.1 403 Forbidden');
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode([
				'status' => false,
				'content' => __('common.permissionDenied'),
			]);
			exit;
		}

		return false;
	}

	public function callbackDispatcherDispatch(string $hookName, array $params): bool
	{
		$request = $params[0];
		$router = $request->getRouter();

		if (!($router instanceof APIRouter)) {
			return false;
		}

		$user = $request->getUser();
		if (!$this->isUserRestricted($request, $user)) {
			return false;
		}

		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		$valSettings = $this->getSetting($contextId, 'restrictSettings');
		$restrictSettings = $valSettings !== null ? (bool) $valSettings : true;

		$valUsers = $this->getSetting($contextId, 'restrictUsers');
		$restrictUsers = $valUsers !== null ? (bool) $valUsers : true;

		$valReadOnly = $this->getSetting($contextId, 'readOnlyWorkflow');
		$readOnlyWorkflow = $valReadOnly !== null ? (bool) $valReadOnly : true;

		$entity = strtolower($router->getEntity());
		$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
		$pathInfo = strtolower($_SERVER['PATH_INFO'] ?? '');

		$isBlocked = false;

		if ($restrictUsers && $entity === 'users') {
			$isBlocked = true;
		}

		if ($restrictSettings && in_array($entity, ['contexts', 'vocabs'], true) && in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
			$isBlocked = true;
		}

		if ($readOnlyWorkflow && in_array($method, ['POST', 'PUT', 'DELETE'], true) && (
			str_contains($pathInfo, '/decisions') || str_contains($pathInfo, '/files/')
		)) {
			$isBlocked = true;
		}

		if ($isBlocked) {
			http_response_code(403);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode([
				'error' => 'api.403.forbidden',
				'errorMessage' => __('common.permissionDenied'),
			]);
			exit;
		}

		return false;
	}

	public function callbackTemplateDisplay(string $hookName, array $params): bool
	{
		$templateMgr = $params[0];
		$request = Application::get()->getRequest();
		$user = $request->getUser();

		if (!$this->isUserRestricted($request, $user)) {
			return false;
		}

		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		$valSettings = $this->getSetting($contextId, 'restrictSettings');
		$restrictSettings = $valSettings !== null ? (bool) $valSettings : true;

		$valUsers = $this->getSetting($contextId, 'restrictUsers');
		$restrictUsers = $valUsers !== null ? (bool) $valUsers : true;

		$valTools = $this->getSetting($contextId, 'restrictTools');
		$restrictTools = $valTools !== null ? (bool) $valTools : true;

		$valReadOnly = $this->getSetting($contextId, 'readOnlyWorkflow');
		$readOnlyWorkflow = $valReadOnly !== null ? (bool) $valReadOnly : true;

		$menu = $templateMgr->getState('menu');
		if (is_array($menu)) {
			if ($restrictSettings) {
				unset($menu['settings']);
			} elseif ($restrictUsers && isset($menu['settings']['submenu']['access'])) {
				unset($menu['settings']['submenu']['access']);
			}
			if ($restrictTools) {
				unset($menu['tools']);
			}
			unset($menu['admin']);
			$templateMgr->setState(['menu' => $menu]);
		}

		if ($readOnlyWorkflow) {
			$css = '
				.pkpWorkflow button[id*="decision"],
				.pkpWorkflow .pkpHeader__actions button.pkpButton--isPrimary,
				.pkpWorkflow a[id*="delete"],
				.pkpWorkflow button:has(.fa-trash),
				.pkpWorkflow button[id*="assignReviewer"],
				.pkpWorkflow button[id*="unassign"] {
					display: none !important;
					pointer-events: none !important;
				}
			';
			$templateMgr->addStyleSheet('editorAccessControlCss', $css, [
				'inline' => true,
				'contexts' => ['backend'],
				'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
			]);
		}

		return false;
	}
}

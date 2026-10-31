<script>
	$(function() {ldelim}
		$('#editorAccessControlSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="editorAccessControlSettingsForm" method="post" action="{url router=\PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="editorAccessControlSettingsNotification"}

	<p class="pkp_help">
		{translate key="plugins.generic.editorAccessControl.settings.description"}
	</p>

	{fbvFormArea id="editorAccessControlSettingsFormArea"}
		{fbvFormSection title="plugins.generic.editorAccessControl.settings.restrictedUsers"}
			{fbvElement type="textarea" id="restrictedUsers" value=$restrictedUsers label="plugins.generic.editorAccessControl.settings.restrictedUsers.description" subLabelTranslate=true placeholder="plugins.generic.editorAccessControl.settings.restrictedUsers.placeholder"|translate rows=4}
		{/fbvFormSection}

		{if $userGroupOptions}
			{fbvFormSection title="plugins.generic.editorAccessControl.settings.restrictedUserGroups" list=true description="plugins.generic.editorAccessControl.settings.restrictedUserGroups.description"}
				{foreach from=$userGroupOptions key=groupId item=groupName}
					{fbvElement type="checkbox" id="restrictedUserGroups[]" value=$groupId checked=in_array($groupId, $restrictedUserGroups) label=$groupName|escape translate=false}
				{/foreach}
			{/fbvFormSection}
		{/if}

		{fbvFormSection title="plugins.generic.editorAccessControl.settings.restrictions" list=true}
			{fbvElement type="checkbox" id="restrictSettings" checked=$restrictSettings label="plugins.generic.editorAccessControl.settings.restrictSettings" translate=true}
			{fbvElement type="checkbox" id="restrictUsers" checked=$restrictUsers label="plugins.generic.editorAccessControl.settings.restrictUsers" translate=true}
			{fbvElement type="checkbox" id="restrictTools" checked=$restrictTools label="plugins.generic.editorAccessControl.settings.restrictTools" translate=true}
			{fbvElement type="checkbox" id="readOnlyWorkflow" checked=$readOnlyWorkflow label="plugins.generic.editorAccessControl.settings.readOnlyWorkflow" translate=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>

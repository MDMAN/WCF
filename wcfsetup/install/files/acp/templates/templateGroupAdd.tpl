{include file='header' pageTitle='wcf.acp.template.group.'|concat:$action}

<header class="boxHeadline">
	<h1>{lang}wcf.acp.template.group.{$action}{/lang}</h1>
</header>

{include file='formError'}

{if $success|isset}
	<p class="success">{lang}wcf.global.success.{$action}{/lang}</p>
{/if}

<div class="contentNavigation">
	<nav>
		<ul>
			<li><a href="{link controller='TemplateGroupList'}{/link}" class="button"><span class="icon icon16 icon-list"></span> <span>{lang}wcf.acp.menu.link.template.group.list{/lang}</span></a></li>
			
			{event name='contentNavigationButtons'}
		</ul>
	</nav>
</div>

<form method="post" action="{if $action == 'add'}{link controller='TemplateGroupAdd'}{/link}{else}{link controller='TemplateGroupEdit' id=$templateGroupID}{/link}{/if}">
	<div class="container containerPadding marginTop">
		<fieldset>
			<legend>{lang}wcf.global.form.data{/lang}</legend>
			
			{if $availableTemplateGroups|count}
				<dl>
					<dt><label for="parentTemplateGroupID">{lang}wcf.acp.template.group.parentTemplateGroup{/lang}</label></dt>
					<dd>
						<select name="parentTemplateGroupID" id="parentTemplateGroupID">
							<option value="0">{lang}wcf.acp.template.group.default{/lang}</option>
							{htmlOptions options=$availableTemplateGroups selected=$parentTemplateGroupID disableEncoding=true}
						</select>
						{if $errorField == 'parentTemplateGroupID'}
							<small class="innerError">
								{if $errorType == 'empty'}
								{lang}wcf.global.form.error.empty{/lang}
								{else}
								{lang}wcf.acp.template.group.parentTemplateGroupID.error.{@$errorType}{/lang}
								{/if}
							</small>
						{/if}
					</dd>
				</dl>
			{/if}
			
			<dl{if $errorField == 'templateGroupName'} class="formError"{/if}>
				<dt><label for="templateGroupName">{lang}wcf.global.name{/lang}</label></dt>
				<dd>
					<input type="text" id="templateGroupName" name="templateGroupName" value="{$templateGroupName}" required="required" class="long" />
					{if $errorField == 'templateGroupName'}
						<small class="innerError">
							{if $errorType == 'empty'}
								{lang}wcf.global.form.error.empty{/lang}
							{else}
								{lang}wcf.acp.template.group.name.error.{@$errorType}{/lang}
							{/if}
						</small>
					{/if}
				</dd>
			</dl>
			
			<dl{if $errorField == 'templateGroupFolderName'} class="formError"{/if}>
				<dt><label for="templateGroupFolderName">{lang}wcf.acp.template.group.folderName{/lang}</label></dt>
				<dd>
					<input type="text" id="templateGroupFolderName" name="templateGroupFolderName" value="{$templateGroupFolderName}" required="required" class="long" />
					{if $errorField == 'templateGroupFolderName'}
						<small class="innerError">
							{if $errorType == 'empty'}
								{lang}wcf.global.form.error.empty{/lang}
							{else}
								{lang}wcf.acp.template.group.folderName.error.{@$errorType}{/lang}
							{/if}
						</small>
					{/if}
				</dd>
			</dl>
			
			{event name='dataFields'}
		</fieldset>
		
		{event name='fieldsets'}
	</div>
	
	<div class="formSubmit">
		<input type="submit" value="{lang}wcf.global.button.submit{/lang}" accesskey="s" />
		{@SECURITY_TOKEN_INPUT_TAG}
	</div>
</form>

{include file='footer'}

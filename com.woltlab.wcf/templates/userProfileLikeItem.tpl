{foreach from=$likeList item=like}
	<li>
		<div class="box48">
			<a href="{link controller='User' object=$like->getUserProfile()}{/link}" title="{$like->getUserProfile()->username}" class="framed">{@$like->getUserProfile()->getAvatar()->getImageTag(48)}</a>
			
			<div>
				<div class="containerHeadline">
					<h3><a href="{link controller='User' object=$like->getUserProfile()}{/link}" class="userLink" data-user-id="{@$like->getUserProfile()->userID}">{$like->getUserProfile()->username}</a><small> - {@$like->time|time}</small></h3> 
					<p><strong>{@$like->getTitle()}</strong></p>
					<small class="containerContentType">{lang}wcf.like.objectType.{@$like->getObjectTypeName()}{/lang}</small>
				</div>
				
				<div>{@$like->getDescription()}</div>
			</div>
		</div>
	</li>
{/foreach}
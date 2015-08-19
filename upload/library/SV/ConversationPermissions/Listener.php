<?php

class SV_ConversationPermissions_Listener
{
	public static function loadClassModel($class, &$extend)
	{
		$extend[] = 'SV_ConversationPermissions_' . $class;
	}
}
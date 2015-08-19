<?php

class SV_ConversationPermissions_XenForo_Model_Conversation extends XFCP_SV_ConversationPermissions_XenForo_Model_Conversation
{
    public function canReplyToConversation(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'canReply'))
            return false;

        $replylimit = XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'replyLimit');
        if ($replylimit >= 0 && $conversation['reply_count'] >= $replylimit)
            return false;

        return parent::canReplyToConversation($conversation, $errorPhraseKey, $viewingUser);
    }
}
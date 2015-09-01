<?php

class SV_ConversationImprovements_NewsFeedHandler_ConversationMessage extends XenForo_NewsFeedHandler_Abstract
{
    var $_conversationModel = null;

    public function getContentByIds(array $contentIds, $model, array $viewingUser)
    {
        $conversationModel = $this->_getConversationModel();

        $messages = $conversationModel->getConversationMessagesByIds($contentIds);

        $conversationIds = XenForo_Application::arrayColumn($messages, 'conversation_id');
        $conversations = $conversationModel->getConversationsForUserByIdsWithMessage($viewingUser['user_id'], $conversationIds);
        foreach ($conversations AS $key => &$conversation)
        {
            if (!$conversationModel->canViewConversation($conversation, $null, $viewingUser))
            {
                unset($conversations[$key]);
            }
        }

        foreach ($messages AS $key => &$message)
        {
            if (isset($conversations[$message['conversation_id']]))
            {
                $message['title'] = $conversations[$message['conversation_id']]['title'];
            }
            else
            {
                unset($messages[$key]);
            }
        }
        return $messages;
    }

    public function canViewAlert(array $alert, $content, array $viewingUser)
    {
        // permission check occurs in getContentByIds()
        return true;
    }

    protected function _getConversationModel()
    {
        if (empty($this->_conversationModel))
        {
            $this->_conversationModel = XenForo_Model::create('XenForo_Model_Conversation');
        }

        return $this->_conversationModel;
    }
}
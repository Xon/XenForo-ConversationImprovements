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
        // link up all recipients
        $recipients = array();
        $flattenedRecipients = $conversationModel->getConversationsRecipients($conversationIds);
        foreach ($flattenedRecipients AS &$recipient)
        {
            $recipients[$recipient['conversation_id']][$recipient['user_id']] = $recipient;
        }
        // link up all conversations
        foreach ($conversations AS $key => &$conversation)
        {
            $conversation['all_recipients'] = $recipients[$key];
            if (!$conversationModel->canViewConversation($conversation, $null, $viewingUser))
            {
                unset($conversations[$key]);
            }
        }

        foreach ($messages AS $key => &$message)
        {
            $conversation_id = $message['conversation_id'];
            if (isset($conversations[$conversation_id]))
            {
                $message['title'] = $conversations[$conversation_id]['title'];
                $message['all_recipients'] = $conversations[$conversation_id]['all_recipients'];
            }
            else
            {
                unset($messages[$key]);
            }
        }
        return $messages;
    }

	public function canViewNewsFeedItem(array $item, $content, array $viewingUser)
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
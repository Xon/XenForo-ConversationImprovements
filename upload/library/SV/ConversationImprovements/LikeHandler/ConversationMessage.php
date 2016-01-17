<?php

class SV_ConversationImprovements_LikeHandler_ConversationMessage extends XenForo_LikeHandler_Abstract
{
    protected $enabled = false;
    protected $_conversationModel = null;

    public function __construct()
    {
        // use the proxy class existence as a cheap check for if this addon is enabled.
        $this->_getConversationModel();
        $this->enabled = class_exists('XFCP_SV_ConversationImprovements_XenForo_Model_Conversation', false);
    }

    public function incrementLikeCounter($contentId, array $latestLikes, $adjustAmount = 1)
    {
        $dw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage');
        if ($dw->setExistingData($contentId))
        {
            $dw->set('_likes', $dw->get('_likes') + $adjustAmount);
            $dw->set('like_users', $latestLikes);
            $dw->save();
        }
    }

    public function getContentData(array $contentIds, array $viewingUser)
    {
        if (!$this->enabled)
        {
            return array();
        }
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

    public function batchUpdateContentUser($oldUserId, $newUserId, $oldUsername, $newUsername)
    {
        if (!$enabled)
        {
            return;
        }
        $this->_getConversationModel()->batchUpdateConversationMessageLikeUser($oldUserId, $newUserId, $oldUsername, $newUsername);
    }

    public function getListTemplateName()
    {
        return 'news_feed_item_conversation_message_like';
    }

    protected function _getConversationModel()
    {
        if ($this->_conversationModel === null)
        {
            $this->_conversationModel = XenForo_Model::create('XenForo_Model_Conversation');
        }
        return $this->_conversationModel;
    }
}
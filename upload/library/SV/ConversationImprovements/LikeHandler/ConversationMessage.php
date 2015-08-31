<?php

class SV_ConversationImprovements_LikeHandler_ConversationMessage extends XenForo_LikeHandler_Abstract
{
    public function incrementLikeCounter($contentId, array $latestLikes, $adjustAmount = 1)
    {
        $dw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage');
        if ($dw->setExistingData($contentId))
        {
            $dw->set('likes', $dw->get('likes') + $adjustAmount);
            $dw->set('like_users', $latestLikes);
            $dw->save();
        }
    }

    public function getContentData(array $contentIds, array $viewingUser)
    {
        $conversationModel = XenForo_Model::create('XenForo_Model_Conversation');

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

    public function batchUpdateContentUser($oldUserId, $newUserId, $oldUsername, $newUsername)
    {
        $conversationModel = XenForo_Model::create('XenForo_Model_Conversation');
        $conversationModel->batchUpdateConversationMessageLikeUser($oldUserId, $newUserId, $oldUsername, $newUsername);
    }

    public function getListTemplateName()
    {
        return 'news_feed_item_conversation_message_like';
    }
}
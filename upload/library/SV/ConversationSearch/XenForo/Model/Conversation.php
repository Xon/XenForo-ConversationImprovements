<?php

class SV_ConversationSearch_XenForo_Model_Conversation extends XFCP_SV_ConversationSearch_XenForo_Model_Conversation
{
    public function getConversationMessagesByIds($messageIds, array $fetchOptions = array())
    {
        $joinOptions = $this->prepareMessageFetchOptions($fetchOptions);

        return $this->fetchAllKeyed('
            SELECT message.*,
                user.*, IF(user.username IS NULL, message.username, user.username) AS username,
                user_profile.*
                ' . $joinOptions['selectFields'] . '
            FROM xf_conversation_message AS message
            LEFT JOIN xf_user AS user ON
                (user.user_id = message.user_id)
            LEFT JOIN xf_user_profile AS user_profile ON
                (user_profile.user_id = message.user_id)
            ' . $joinOptions['joinTables'] . '
            WHERE message.message_id IN (' . $this->_getDb()->quote($messageIds) . ')
        ', 'message_id');
    }

    public function getConversationMessageIdsInRange($start, $limit)
    {
        $db = $this->_getDb();

        return $db->fetchCol($db->limit('
            SELECT message_id
            FROM xf_conversation_message
            WHERE message_id > ?
            ORDER BY message_id
        ', $limit), $start);
    }

    public function getConversationsByIds($conversationIds, $user_id = 0)
    {
        return $this->fetchAllKeyed('
            SELECT conversation_master.*, recipient.*
            FROM xf_conversation_master AS conversation_master
            LEFT JOIN xf_conversation_recipient as recipient ON conversation_master.conversation_id = recipient.conversation_id
            WHERE conversation_master.conversation_id IN (' . $this->_getDb()->quote($conversationIds) . ') and recipient.user_id = ?
        ', 'conversation_id', $user_id);
    }

    public function getConversationById($conversationId, $user_id = 0)
    {
        return $this->_getDb()->fetchRow('
            SELECT conversation_master.*, recipient.*
            FROM xf_conversation_master AS conversation_master
            LEFT JOIN xf_conversation_recipient as recipient ON conversation_master.conversation_id = recipient.conversation_id
            WHERE conversation_master.conversation_id = ? and recipient.user_id = ?
        ', array($conversationId,$user_id));
    }

    public function canViewConversation(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        if (empty($viewingUser['user_id']))
        {
            return false;
        }

        if (!isset($conversation['recipientNames']))
        {
            $recipientNames = $conversation['recipients'] ? @unserialize($conversation['recipients']) : array();
        }
        else
        {
            $recipientNames = $conversation['recipientNames'];
        }

        foreach($recipientNames as $recipientName)
        {
            if($recipientName['user_id'] == $viewingUser['user_id'])
            {
                return true;
            }
        }

        return false;
    }
}
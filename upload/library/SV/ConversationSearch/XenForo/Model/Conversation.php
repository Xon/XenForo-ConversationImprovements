<?php

class SV_ConversationSearch_XenForo_Model_Conversation extends XFCP_SV_ConversationSearch_XenForo_Model_Conversation
{
    public function rebuildUnreadConversationCountForUser($userId)
    {
        if (SV_ConversationSearch_Globals::$UsersToUpdate !== null)
        {
            SV_ConversationSearch_Globals::$UsersToUpdate[] = $userId;
            return;
        }
        parent::rebuildUnreadConversationCountForUser($userId);
    }

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

    public function getConversationsByIds($conversationIds)
    {
        return $this->fetchAllKeyed('
            SELECT conversation_master.*
            FROM xf_conversation_master AS conversation_master
            WHERE conversation_master.conversation_id IN (' . $this->_getDb()->quote($conversationIds) . ')
        ', 'conversation_id');
    }

    public function getConversationsForUserByIdsWithMessage($userId, array $conversationIds)
    {
        if (!$conversationIds)
        {
            return array();
        }

        return $this->fetchAllKeyed('
            SELECT conversation_master.*,
                conversation_user.*,
                conversation_starter.*,
                conversation_master.username AS username,
                conversation_recipient.recipient_state, conversation_recipient.last_read_date,
                first_conversation_message.message
            FROM xf_conversation_user AS conversation_user
            INNER JOIN xf_conversation_master AS conversation_master ON
                (conversation_user.conversation_id = conversation_master.conversation_id)
            INNER JOIN xf_conversation_recipient AS conversation_recipient ON
                (conversation_user.conversation_id = conversation_recipient.conversation_id
                AND conversation_user.owner_user_id = conversation_recipient.user_id)
            LEFT JOIN xf_user AS conversation_starter ON
                (conversation_starter.user_id = conversation_master.user_id)
            JOIN xf_conversation_message as first_conversation_message ON
                (conversation_master.first_message_id = first_conversation_message.message_id)
            WHERE conversation_user.owner_user_id = ?
                AND conversation_user.conversation_id IN (' . $this->_getDb()->quote($conversationIds) . ')
            ORDER BY conversation_user.last_message_date DESC
        ', 'conversation_id', $userId);
    }

	public function getConversationRecipientsForSearch($conversationId)
	{
		return $this->fetchAllKeyed('
			SELECT conversation_recipient.*
			FROM xf_conversation_recipient AS conversation_recipient
			WHERE conversation_recipient.conversation_id = ?
            order by conversation_recipient.user_id
		', 'user_id', $conversationId);
	}

    public function canViewConversation(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        if (empty($viewingUser['user_id']))
        {
            return false;
        }

        if (!isset($conversation['all_recipients']))
        {
            $conversation['all_recipients'] = $this->getConversationRecipientsForSearch($conversation['conversation_id']);
        }

        return isset($conversation['all_recipients'][$viewingUser['user_id']]);
    }
}
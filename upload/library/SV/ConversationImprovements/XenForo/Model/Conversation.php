<?php

class SV_ConversationImprovements_XenForo_Model_Conversation extends XFCP_SV_ConversationImprovements_XenForo_Model_Conversation
{
    public function sv_deferRebuildUnreadCounters()
    {
        if (SV_ConversationImprovements_Globals::$UsersToUpdate === null)
        {
            SV_ConversationImprovements_Globals::$UsersToUpdate = array();
        }
        SV_ConversationImprovements_Globals::$UsersToUpdateRefs++;
    }
    
    public function sv_rebuildPendingUnreadCounters()
    {
        SV_ConversationImprovements_Globals::$UsersToUpdateRefs--;
        if (SV_ConversationImprovements_Globals::$UsersToUpdateRefs > 0)
        {
            return;
        }

        if (SV_ConversationImprovements_Globals::$UsersToUpdate !== null)
        {
            $userIds = SV_ConversationImprovements_Globals::$UsersToUpdate;
            SV_ConversationImprovements_Globals::$UsersToUpdate = null;
            foreach($userIds as $userId => $null)
            {
                XenForo_Db::beginTransaction();
                $this->rebuildUnreadConversationCountForUser($userId);
                XenForo_Db::commit();
            }
        }
    }

    const FETCH_PERMISSIONS = 0x10000;

    public function rebuildUnreadConversationCountForUser($userId)
    {
        if (SV_ConversationImprovements_Globals::$UsersToUpdate !== null)
        {
            SV_ConversationImprovements_Globals::$UsersToUpdate[$userId] = true;
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

    public function canViewIps(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
    {
        return $this->_getUserModel()->canViewIps($errorPhraseKey, $viewingUser);
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


    public function insertConversationAlert(array $conversation, array $alertUser, $action,
        array $triggerUser = null, array $extraData = null, array &$messageInfo = null
    )
    {
        if (empty($alertUser['permissions']))
        {
            if (empty($alertUser['global_permission_cache']))
            {
                $alertUser['global_permission_cache'] = $this->_getDb()->fetchOne('
                    SELECT cache_value
                    FROM xf_permission_combination
                    WHERE permission_combination_id = ?
                ', $alertUser['permission_combination_id']);


            }
            $alertUser['permissions'] = XenForo_Permission::unserializePermissions($alertUser['global_permission_cache']);
        }

        $emailParticipantLimit = XenForo_Permission::hasPermission($alertUser['permissions'], 'conversation', 'emailParticipantLimit');
        if ($emailParticipantLimit >= 0 && count($conversation['recipients']) >= $emailParticipantLimit)
        {
            $alertUser['email_on_conversation'] = false;
        }

        parent::insertConversationAlert($conversation,$alertUser,$action,$triggerUser,$extraData);
    }

    public function getConversationRecipients($conversationId, array $fetchOptions = array())
    {
        if (empty($fetchOptions['join']))
        {
            $fetchOptions['join'] = 0;
        }
        $fetchOptions['join'] = $fetchOptions['join'] | self::FETCH_PERMISSIONS;

        return parent::getConversationRecipients($conversationId,$fetchOptions);
    }

    public function prepareConversationFetchOptions(array $fetchOptions)
    {
        $conversationFetchOptions = parent::prepareConversationFetchOptions($fetchOptions);

        if (!empty($fetchOptions['join']))
        {
            if ($fetchOptions['join'] & self::FETCH_PERMISSIONS)
            {
                $conversationFetchOptions['selectFields'] .= ',
                    permission_combination.cache_value AS global_permission_cache';
                $conversationFetchOptions['joinTables'] .= '
                    LEFT JOIN xf_permission_combination AS permission_combination ON
                        (permission_combination.permission_combination_id = user.permission_combination_id)';
            }
        }

        return $conversationFetchOptions;
    }
}
<?php

class SV_ConversationImprovements_XenForo_Model_Conversation extends XFCP_SV_ConversationImprovements_XenForo_Model_Conversation
{
    public function getConversationMessages($conversationId, array $fetchOptions = array())
    {
        if (!isset($fetchOptions['likeUserId']))
        {
            $fetchOptions['likeUserId'] = XenForo_Visitor::getUserId();
        }
        return parent::getConversationMessages($conversationId, $fetchOptions);
    }

    public function prepareMessageFetchOptions(array $fetchOptions)
    {
        $joinOptions = parent::prepareMessageFetchOptions($fetchOptions);

        if (isset($fetchOptions['likeUserId']))
        {
            if (empty($fetchOptions['likeUserId']))
            {
                $joinOptions['selectFields'] .= ',
                    0 AS like_date';
            }
            else
            {
                $db = $this->_getDb();

                $joinOptions['selectFields'] .= ',
                    liked_content.like_date';
                $joinOptions['joinTables'] .= '
                    LEFT JOIN xf_liked_content AS liked_content
                        ON (liked_content.content_type = \'conversation_message\'
                            AND liked_content.content_id = message.message_id
                            AND liked_content.like_user_id = ' .$db->quote($fetchOptions['likeUserId']) . ')';
            }
        }

        if (!empty($fetchOptions['includeConversationTitle']))
        {
                $joinOptions['selectFields'] .= ',
                    conversation.title';
                $joinOptions['joinTables'] .= '
                    LEFT JOIN xf_conversation_master AS conversation
                        ON (conversation.conversation_id = message.conversation_id)';
        }

        return $joinOptions;
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

    public function getConversationsRecipients(array $conversationIds)
    {
        if (!$conversationIds)
        {
            return array();
        }

        $sql = implode(',', array_fill(0, count($conversationIds), '?'));

        return $this->_getDb()->fetchAll('
            SELECT conversation_recipient.*
            FROM xf_conversation_recipient AS conversation_recipient
            WHERE conversation_recipient.conversation_id IN (' .  $sql . ')
            order by conversation_recipient.conversation_id
        ', $conversationIds);
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

        return isset($conversation['all_recipients'][$viewingUser['user_id']]) && ($conversation['all_recipients'][$viewingUser['user_id']]['recipient_state'] == 'active');
    }

    public function canReplyToConversation(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'canReply'))
        {
            return false;
        }

        $replylimit = XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'replyLimit');
        if ($replylimit >= 0 && $conversation['reply_count'] >= $replylimit)
        {
            return false;
        }

        return parent::canReplyToConversation($conversation, $errorPhraseKey, $viewingUser);
    }

    public function canViewConversationHistory(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        if (!$viewingUser['user_id'])
        {
            return false;
        }

        if (!XenForo_Application::getOptions()->editHistory['enabled'])
        {
            return false;
        }

        if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'sv_manageConversation'))
        {
            return true;
        }

        return ($conversation['user_id'] == $viewingUser['user_id']);
    }

    public function canEditConversation(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        $response = parent::canEditConversation($conversation, $errorPhraseKey, $viewingUser);
        if($response)
        {
            return true;
        }

        if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'sv_manageConversation'))
        {
            return true;
        }

        return false;
    }

    public function canViewMessageHistory(array $message, array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        if (!$viewingUser['user_id'])
        {
            return false;
        }

        if (!XenForo_Application::getOptions()->editHistory['enabled'])
        {
            return false;
        }

        if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'editAnyPost') ||
            XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'sv_manageConversation') )
        {
            return true;
        }

        return false;
    }

    public function canLikeConversationMessage(array $message, array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        if (!$viewingUser['user_id'])
        {
            return false;
        }

        if ($message['user_id'] == $viewingUser['user_id'])
        {
            $errorPhraseKey = 'liking_own_content_cheating';
            return false;
        }

        return XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'like');
    }

    public function prepareMessage(array $message, array $conversation)
    {
        $this->standardizeViewingUserReference($viewingUser);

        $message = parent::prepareMessage($message, $conversation);
        $message['canViewIps'] = $this->canViewIps($conversation, $null, $viewingUser);
        $message['canLike'] = $this->canLikeConversationMessage($message, $conversation, $null, $viewingUser);
        $message['canViewHistory'] = $this->canViewMessageHistory($message, $conversation, $null, $viewingUser);

        if (!isset($message['likes']))
        {
            if (!empty($message['_likes']))
            {
                $message['likes'] = $message['_likes'];
                $message['likeUsers'] = @unserialize($message['like_users']);
                if (empty($message['likeUsers']))
                {
                    $message['likeUsers'] = array();
                    $message['likes'] = 0;
                }
            }
            else
            {
                $message['likes'] = 0;
                $message['likeUsers'] = array();
            }
        }

        return $message;
    }

    public function batchUpdateConversationMessageLikeUser($oldUserId, $newUserId, $oldUsername, $newUsername)
    {
        $db = $this->_getDb();

        // note that xf_liked_content should have already been updated with $newUserId
        $db->query('
            UPDATE (
                SELECT content_id FROM xf_liked_content
                WHERE content_type = \'conversation_message\'
                AND like_user_id = ?
            ) AS temp
            INNER JOIN xf_conversation_message AS message ON (message.message_id = temp.content_id)
            SET like_users = REPLACE(like_users, ' .
            $db->quote('i:' . $oldUserId . ';s:8:"username";s:' . strlen($oldUsername) . ':"' . $oldUsername . '";') . ', ' .
            $db->quote('i:' . $newUserId . ';s:8:"username";s:' . strlen($newUsername) . ':"' . $newUsername . '";') . ')
        ', $newUserId);
    }
}
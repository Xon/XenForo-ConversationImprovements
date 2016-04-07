<?php

class SV_ConversationImprovements_Search_DataHandler_ConversationMessage extends XenForo_Search_DataHandler_Abstract
{
    protected $enabled = false;
    protected $_conversationModel = null;
    protected $_userModel = null;

    public function __construct()
    {
        // use the proxy class existence as a cheap check for if this addon is enabled.
        $this->_getConversationModel();
        $this->enabled = class_exists('XFCP_SV_ConversationImprovements_XenForo_Model_Conversation', false);
    }

    /**
     * Inserts into (or replaces a record) in the index.
     *
     * @see XenForo_Search_DataHandler_Abstract::_insertIntoIndex()
     */
    protected function _insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null)
    {
        if (!($this->enabled)) return;

        //if ($data['message_state'] != 'visible')
        //{
        //    return;
        //}

        $metadata = array();
        $title = '';

        if ($parentData)
        {
            $conversation = $parentData;
            //if ($conversation['discussion_state'] != 'visible')
            //{
            //    return;
            //}

            if ($data['message_id'] == $conversation['first_message_id'] || $conversation['first_message_id'] === 0)
            {
                $title = $conversation['title'];
                if (!empty($conversation['prefix_id']))
                {
                    $metadata['prefix'] = $conversation['prefix_id'];
                }
            }

            if (!isset($conversation['all_recipients']))
            {
                $conversation['all_recipients'] = $this->_getConversationModel()->getConversationRecipientsForSearch($conversation['conversation_id']);
            }
            $metadata['recipients'] = array_keys($conversation['all_recipients']);
        }

        $metadata['conversation'] = $data['conversation_id'];

        $indexer->insertIntoIndex(
            'conversation_message', $data['message_id'],
            $title, $data['message'],
            $data['message_date'], $data['user_id'], $data['conversation_id'], $metadata
        );
    }

    /**
     * Updates a record in the index.
     *
     * @see XenForo_Search_DataHandler_Abstract::_updateIndex()
     */
    protected function _updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates)
    {
        if (!($this->enabled)) return;
        $indexer->updateIndex('conversation_message', $data['message_id'], $fieldUpdates);
    }

    /**
     * Deletes one or more records from the index.
     *
     * @see XenForo_Search_DataHandler_Abstract::_deleteFromIndex()
     */
    protected function _deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList)
    {
        if (!($this->enabled)) return;
        $conversationIds = array();
        foreach ($dataList AS $data)
        {
            $conversationIds[] = is_array($data) ? $data['message_id'] : $data;
        }

        $indexer->deleteFromIndex('conversation_message', $conversationIds);
    }

    /**
     * Rebuilds the index for a batch.
     *
     * @see XenForo_Search_DataHandler_Abstract::rebuildIndex()
     */
    public function rebuildIndex(XenForo_Search_Indexer $indexer, $lastId, $batchSize)
    {
        if (!($this->enabled)) return false;
        $conversationIds = $this->_getConversationModel()->getConversationMessageIdsInRange($lastId, $batchSize);
        if (!$conversationIds)
        {
            return false;
        }

        $this->quickIndex($indexer, $conversationIds);

        return max($conversationIds);
    }

    /**
     * Rebuilds the index for the specified content.

     * @see XenForo_Search_DataHandler_Abstract::quickIndex()
     */
    public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds)
    {
        if (!($this->enabled)) return false;
        $conversationModel = $this->_getConversationModel();
        $messages = $conversationModel->getConversationMessagesByIds($contentIds, array(
        ));

        $conversationIds = array();
        foreach ($messages AS $message)
        {
            $conversationIds[] = $message['conversation_id'];
        }

        $conversations = $conversationModel->getConversationsByIds(array_unique($conversationIds));
        $recipients = array();
        $flattenedRecipients = $conversationModel->getConversationsRecipients($conversationIds);
        foreach ($flattenedRecipients AS &$recipient)
        {
            $recipients[$recipient['conversation_id']][$recipient['user_id']] = $recipient;
        }
        foreach ($conversations AS $conversation_id => &$conversation)
        {
            $conversation['all_recipients'] = isset($recipients[$conversation_id])
                                              ? $recipients[$conversation_id]
                                              : array();
            if (empty($conversation['all_recipients']))
            {
                unset($conversations[$conversation_id]);
            }
        }

        foreach ($messages AS &$message)
        {
            $conversation = (isset($conversations[$message['conversation_id']]) ? $conversations[$message['conversation_id']] : null);
            if (!$conversation)
            {
                continue;
            }

            $this->insertIntoIndex($indexer, $message, $conversation);
        }

        return true;
    }

    public function getInlineModConfiguration()
    {
        return array();
    }

    /**
     * Gets the type-specific data for a collection of results of this content type.
     *
     * @see XenForo_Search_DataHandler_Abstract::getDataForResults()
     */
    public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
    {
        if (!($this->enabled)) return array();
        $conversationModel = $this->_getConversationModel();

        $messages = $conversationModel->getConversationMessagesByIds($ids, array(
        ));

        $conversationIds = array();
        foreach ($messages AS $message)
        {
            $conversationIds[] = $message['conversation_id'];
        }

        $conversations = $conversationModel->getConversationsForUserByIds($viewingUser['user_id'], array_unique($conversationIds));

        foreach ($messages AS $messageId => &$message)
        {
            $message['conversation'] = (isset($conversations[$message['conversation_id']]) ? $conversations[$message['conversation_id']] : null);
            if (!isset($message['conversation']) || $message['message_id'] == $message['conversation']['first_message_id'] && isset($resultsGrouped['conversation'][$message['conversation_id']]))
            {
                // matched first message and conversation, skip the message
                unset($messages[$messageId]);
            }
        }

        return $messages;
    }

    /**
     * Determines if this result is viewable.
     *
     * @see XenForo_Search_DataHandler_Abstract::canViewResult()
     */
    public function canViewResult(array $result, array $viewingUser)
    {
        if (!($this->enabled)) return false;
        return $this->_getConversationModel()->canViewConversation($result['conversation'], $null, $viewingUser);
    }

    /**
     * Prepares a result for display.
     *
     * @see XenForo_Search_DataHandler_Abstract::prepareResult()
     */
    public function prepareResult(array $result, array $viewingUser)
    {
        if (!($this->enabled)) return $result;
        $result = $this->_getConversationModel()->prepareMessage($result, $result['conversation']);
        $result['title'] = XenForo_Helper_String::censorString($result['conversation']['title']);

        return $result;
    }

    public function addInlineModOption(array &$result)
    {
        return array();
    }

    /**
     * Gets the date of the result (from the result's content).
     *
     * @see XenForo_Search_DataHandler_Abstract::getResultDate()
     */
    public function getResultDate(array $result)
    {
        return $result['message_date'];
    }

    /**
     * Renders a result to HTML.
     *
     * @see XenForo_Search_DataHandler_Abstract::renderResult()
     */
    public function renderResult(XenForo_View $view, array $result, array $search)
    {
        return $view->createTemplateObject('search_result_conversation_message', array(
            'conversation' => $result['conversation'],
            'conversation_message' => $result,
            'search' => $search,
        ));
    }

    /**
     * Gets the content types searched in a type-specific search.
     *
     * @see XenForo_Search_DataHandler_Abstract::getSearchContentTypes()
     */
    public function getSearchContentTypes()
    {
        return array('conversation_message', 'conversation');
    }

    /**
     * Get type-specific constraints from input.
     *
     * @param XenForo_Input $input
     *
     * @return array
     */
    public function getTypeConstraintsFromInput(XenForo_Input $input)
    {
        if (!($this->enabled)) return array();
        $constraints = array();

        $replyCount = $input->filterSingle('reply_count', XenForo_Input::UINT);
        if ($replyCount)
        {
            $constraints['reply_count'] = $replyCount;
        }

        $prefixes = $input->filterSingle('prefixes', XenForo_Input::UINT, array('array' => true));
        if ($prefixes && reset($prefixes))
        {
            $prefixes = array_unique($prefixes);
            $constraints['prefix'] = implode(' ', $prefixes);
            if (!$constraints['prefix'])
            {
                unset($constraints['prefix']); // just 0
            }
        }

        $conversationId = $input->filterSingle('conversation_id', XenForo_Input::UINT);
        if ($conversationId)
        {
            $constraints['conversation'] = $conversationId;

            // undo things that don't make sense with this
            $constraints['titles_only'] = false;
        }

        $recipients = $input->filterSingle('recipients', XenForo_Input::STRING);
        if ($recipients)
        {
            $usernames = array_unique(explode(',', $recipients));
            $users = $this->_getUserModel()->getUsersByNames($usernames, array(), $notFound);
            $constraints['recipients'] = array_keys($users);
        }

        $this->EnforceUserAccess($constraints);

        return $constraints;
    }

    protected function EnforceUserAccess(array &$constraints)
    {
        $constraints['require_recipient'] = XenForo_Visitor::getUserId();
    }

    /**
     * Process a type-specific constraint.
     *
     * @see XenForo_Search_DataHandler_Abstract::processConstraint()
     */
    public function processConstraint(XenForo_Search_SourceHandler_Abstract $sourceHandler, $constraint, $constraintInfo, array $constraints)
    {
        if (!($this->enabled)) return array();
        switch ($constraint)
        {
            case 'reply_count':
                $replyCount = intval($constraintInfo);
                if ($replyCount > 0)
                {
                    return array(
                        'query' => array('conversation', 'reply_count', '>=', $replyCount)
                    );
                }
                break;

            case 'prefix':
                if ($constraintInfo)
                {
                    return array(
                        'metadata' => array('prefix', preg_split('/\D+/', strval($constraintInfo))),
                    );
                }
                break;

            case 'conversation':
                $conversationId = intval($constraintInfo);
                if ($conversationId > 0)
                {
                    return array(
                        'metadata' => array('conversation', $conversationId)
                    );
                }
                break;
            case 'require_recipient':
                if ($constraintInfo)
                {
                    return array(
                       'metadata' => array('recipients', $constraintInfo)
                    );
                }
            case 'recipients':
                if ($constraintInfo)
                {
                    return array(
                       'metadata' => array('recipients', $constraintInfo)
                    );
                }
                break;
        }

        return false;
    }

    /**
     * Gets the search form controller response for this type.
     *
     * @see XenForo_Search_DataHandler_Abstract::getSearchFormControllerResponse()
     */
    public function getSearchFormControllerResponse(XenForo_ControllerPublic_Abstract $controller, XenForo_Input $input, array $viewParams)
    {
        if (!($this->enabled)) return null;
        $params = $input->filterSingle('c', XenForo_Input::ARRAY_SIMPLE);

        if (!XenForo_Visitor::getUserId()) return null;

        $viewParams['search']['reply_count'] = empty($params['reply_count']) ? '' : $params['reply_count'];

        if (!empty($params['prefix']))
        {
            $viewParams['search']['prefixes'] = array_fill_keys(explode(' ', $params['prefix']), true);
        }
        else
        {
            $viewParams['search']['prefixes'] = array();
        }

        /** @var $threadPrefixModel XenForo_Model_ThreadPrefix */
        $threadPrefixModel = XenForo_Model::create('XenForo_Model_ThreadPrefix');

        $viewParams['prefixes'] = $threadPrefixModel->getPrefixesByGroups();
        if ($viewParams['prefixes'])
        {
            $visiblePrefixes = $threadPrefixModel->getVisiblePrefixIds();
            foreach ($viewParams['prefixes'] AS $key => &$prefixes)
            {
                foreach ($prefixes AS $prefixId => $prefix)
                {
                    if (!isset($visiblePrefixes[$prefixId]))
                    {
                        unset($prefixes[$prefixId]);
                    }
                }

                if (!count($prefixes))
                {
                    unset($viewParams['prefixes'][$key]);
                }
            }
        }

        $viewParams['search']['conversation'] = array();
        if (!empty($params['conversation']))
        {
            $conversationModel = $this->_getConversationModel();
            $viewingUser = XenForo_Visitor::getInstance()->toArray();

            $conversation = $conversationModel->getConversationForUser($params['conversation'], $viewingUser);

            if ($conversation)
            {
                if($conversationModel->canViewConversation($conversation, $null, $viewingUser))
                {
                    $viewParams['search']['conversation'] = $conversation;
                }
            }
        }

        if (!empty($params['recipients']) && is_array($params['recipients']))
        {
            $users = $this->_getUserModel()->getUsersByIds($params['recipients']);
            $usernames = XenForo_Application::arrayColumn($users, 'username');
            $viewParams['search']['recipients'] = implode(', ', $usernames);
        }

        return $controller->responseView('XenForo_ViewPublic_Search_Form_Post', 'search_form_conversation_message', $viewParams);
    }

    /**
     * Gets the search order for a type-specific search.
     *
     * @see XenForo_Search_DataHandler_Abstract::getOrderClause()
     */
    public function getOrderClause($order)
    {
        if ($order == 'replies')
        {
            return array(
                array('conversation', 'reply_count', 'desc'),
                array('search_index', 'item_date', 'desc')
            );
        }

        return false;
    }

    /**
     * Gets the necessary join structure information for this type.
     *
     * @see XenForo_Search_DataHandler_Abstract::getJoinStructures()
     */
    public function getJoinStructures(array $tables)
    {
        if (!($this->enabled)) return array();
        $structures = array();
        if (isset($tables['conversation']))
        {
            $structures['conversation'] = array(
                'table' => 'xf_conversation_master',
                'key' => 'conversation_id',
                'relationship' => array('search_index', 'discussion_id'),
            );
        }

        return $structures;
    }

    /**
     * Gets the content type that will be used when grouping for this type.
     *
     * @see XenForo_Search_DataHandler_Abstract::getGroupByType()
     */
    public function getGroupByType()
    {
        return 'conversation';
    }

    protected function _getConversationModel()
    {
        if ($this->_conversationModel === null)
        {
            $this->_conversationModel = XenForo_Model::create('XenForo_Model_Conversation');
        }
        return $this->_conversationModel;
    }

    protected function _getUserModel()
    {
        if ($this->_userModel === null)
        {
            $this->_userModel = XenForo_Model::create('XenForo_Model_User');
        }
        return $this->_userModel;
    }
}
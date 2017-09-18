<?php

class SV_ConversationImprovements_Search_DataHandler_Conversation extends XenForo_Search_DataHandler_Abstract
{
    /** @var bool */
    protected $enabled = false;
    /** @var SV_ConversationImprovements_XenForo_Model_Conversation|null */
    protected $_conversationModel = null;

    public function __construct()
    {
        // use the proxy class existence as a cheap check for if this addon is enabled.
        $this->_getConversationModel();
        $this->enabled = class_exists('XFCP_SV_ConversationImprovements_XenForo_Model_Conversation', false);
    }

    public function getCustomMapping(array $mapping = [])
    {
        $mapping['properties']['recipients'] = ["type" => "long"];
        $mapping['properties']['conversation'] = ["type" => "long"];

        return $mapping;
    }

    protected function _insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null)
    {
        if (!($this->enabled))
        {
            return;
        }

        $metadata = [];
        $metadata['conversation'] = $data['conversation_id'];
        if (!empty($data['prefix_id']))
        {
            $metadata['prefix'] = $data['prefix_id'];
        }

        if (!isset($data['all_recipients']))
        {
            $data['all_recipients'] = $this->_getConversationModel()->getConversationRecipientsForSearch(
                $data['conversation_id']
            );
        }
        $metadata['recipients'] = array_keys($data['all_recipients']);

        $indexer->insertIntoIndex(
            'conversation', $data['conversation_id'],
            $data['title'], '',
            $data['start_date'], $data['user_id'], $data['conversation_id'], $metadata
        );
    }

    protected function _updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates)
    {
        if (!($this->enabled))
        {
            return;
        }
        $indexer->updateIndex('conversation', $data['conversation_id'], $fieldUpdates);
    }

    protected function _deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList)
    {
        if (!($this->enabled))
        {
            return;
        }
        $conversationIds = [];
        foreach ($dataList AS $data)
        {
            $conversationIds[] = is_array($data) ? $data['conversation_id'] : $data;
        }

        $indexer->deleteFromIndex('conversation', $conversationIds);
    }

    public function rebuildIndex(XenForo_Search_Indexer $indexer, $lastId, $batchSize)
    {
        if (!($this->enabled))
        {
            return false;
        }
        $conversationIds = $this->_getConversationModel()->getConversationIdsInRange($lastId, $batchSize);
        if (!$conversationIds)
        {
            return false;
        }

        $this->quickIndex($indexer, $conversationIds);

        return max($conversationIds);
    }

    public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds)
    {
        if (!($this->enabled))
        {
            return false;
        }
        $conversationModel = $this->_getConversationModel();
        $conversations = $conversationModel->sv_getConversationsByIds($contentIds);
        $recipients = [];
        $flattenedRecipients = $conversationModel->getConversationsRecipients($contentIds);
        foreach ($flattenedRecipients AS &$recipient)
        {
            $recipients[$recipient['conversation_id']][$recipient['user_id']] = $recipient;
        }

        foreach ($conversations AS $conversation_id => &$conversation)
        {
            $conversation['all_recipients'] = isset($recipients[$conversation_id])
                ? $recipients[$conversation_id]
                : [];
            if (empty($conversation['all_recipients']))
            {
                continue;
            }
            $this->insertIntoIndex($indexer, $conversation);
        }

        return true;
    }

    public function getInlineModConfiguration()
    {
        return [];
    }

    public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
    {
        if (!($this->enabled))
        {
            return [];
        }
        $ids = array_unique($ids);
        $conversationModel = $this->_getConversationModel();
        $conversations = $conversationModel->getConversationsForUserByIdsWithMessage($viewingUser['user_id'], $ids);
        // unflatten conversation recipients in a single query
        $recipients = [];
        $flattenedRecipients = $conversationModel->getConversationsRecipients($ids);
        foreach ($flattenedRecipients AS &$recipient)
        {
            $recipients[$recipient['conversation_id']][$recipient['user_id']] = $recipient;
        }
        // link up all conversations
        foreach ($conversations AS $conversation_id => &$conversation)
        {
            $conversation['all_recipients'] = isset($recipients[$conversation_id])
                ? $recipients[$conversation_id]
                : [];
        }

        return $conversations;
    }

    public function canViewResult(array $result, array $viewingUser)
    {
        if (!($this->enabled))
        {
            return false;
        }

        return $this->_getConversationModel()->canViewConversation($result, $null, $viewingUser);
    }

    public function prepareResult(array $result, array $viewingUser)
    {
        if (!($this->enabled))
        {
            return $result;
        }

        return $this->_getConversationModel()->prepareConversation($result);
    }

    public function addInlineModOption(array &$result)
    {
        return [];
    }

    public function getResultDate(array $result)
    {
        return $result['start_date'];
    }

    public function renderResult(XenForo_View $view, array $result, array $search)
    {
        if (!($this->enabled))
        {
            return null;
        }

        return $view->createTemplateObject(
            'search_result_conversation', [
                                            'conversation'         => $result,
                                            'conversation_message' => $result,
                                            'search'               => $search,
                                            'enableInlineMod'      => $this->_inlineModEnabled
                                        ]
        );
    }

    public function getSearchContentTypes()
    {
        return ['conversation'];
    }

    public function filterConstraints(XenForo_Search_SourceHandler_Abstract $sourceHandler, array $constraints)
    {
        $constraints = parent::filterConstraints($sourceHandler, $constraints);
        $constraints['require_recipient'] = XenForo_Visitor::getUserId();

        return $constraints;
    }

    public function processConstraint(XenForo_Search_SourceHandler_Abstract $sourceHandler, $constraint, $constraintInfo, array $constraints)
    {
        if (!($this->enabled))
        {
            return [];
        }
        switch ($constraint)
        {
            case 'reply_count':
                $replyCount = intval($constraintInfo);
                if ($replyCount > 0)
                {
                    return [
                        'query' => ['conversation', 'reply_count', '>=', $replyCount]
                    ];
                }
                break;

            case 'prefix':
                if ($constraintInfo)
                {
                    return [
                        'metadata' => ['prefix', preg_split('/\D+/', strval($constraintInfo))],
                    ];
                }
                break;

            case 'conversation':
                $conversationId = intval($constraintInfo);
                if ($conversationId > 0)
                {
                    return [
                        'metadata' => ['conversation', $conversationId]
                    ];
                }
                break;
            case 'require_recipient':
                if ($constraintInfo)
                {
                    return [
                        'metadata' => ['recipients', $constraintInfo]
                    ];
                }
                break;
            case 'recipients':
                if ($constraintInfo)
                {
                    return [
                        'metadata' => ['recipients', $constraintInfo]
                    ];
                }
                break;
        }

        return false;
    }

    /**
     * @return null|SV_ConversationImprovements_XenForo_Model_Conversation
     */
    protected function _getConversationModel()
    {
        if ($this->_conversationModel === null)
        {
            $this->_conversationModel = XenForo_Model::create('XenForo_Model_Conversation');
        }

        return $this->_conversationModel;
    }
}

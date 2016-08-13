<?php

class SV_ConversationImprovements_XenForo_DataWriter_ConversationMaster extends XFCP_SV_ConversationImprovements_XenForo_DataWriter_ConversationMaster
{
    const OPTION_INDEX_FOR_SEARCH = 'indexForSearch';
    const OPTION_LOG_EDIT = 'logEdit';
    const DATA_CONVERSATION = 'conversationInfo';


    protected function _getFields()
    {
        $fields = parent::_getFields();
        $fields["xf_conversation_master"]['conversation_last_edit_date'] = array('type' => self::TYPE_UINT, 'default' => 0);
        $fields["xf_conversation_master"]['conversation_last_edit_user_id'] = array('type' => self::TYPE_UINT, 'default' => 0);
        $fields["xf_conversation_master"]['conversation_edit_count'] = array('type' => self::TYPE_UINT_FORCED, 'default' => 0);
        $options = XenForo_Application::get('options');
        $defaults[self::OPTION_LOG_EDIT] = $options->editHistory['enabled'];
        return $fields;
    }

    protected function _getDefaultOptions()
    {
        $defaultOptions = parent::_getDefaultOptions();
        $defaultOptions[self::OPTION_INDEX_FOR_SEARCH] = true;
        return $defaultOptions;
    }

    protected function _preSave()
    {
        if ($this->isUpdate() && $this->isChanged('title'))
        {
            if (!$this->isChanged('conversation_last_edit_date'))
            {
                $this->set('conversation_last_edit_date', XenForo_Application::$time);
                if (!$this->isChanged('conversation_last_edit_user_id'))
                {
                    $this->set('conversation_last_edit_user_id', XenForo_Visitor::getUserId());
                }
            }

            if (!$this->isChanged('conversation_edit_count'))
            {
                $this->set('conversation_edit_count', $this->get('conversation_edit_count') + 1);
            }
        }
        if ($this->isChanged('conversation_edit_count') && $this->get('conversation_edit_count') == 0)
        {
            $this->set('conversation_last_edit_date', 0);
        }
        if (!$this->get('conversation_last_edit_date'))
        {
            $this->set('conversation_last_edit_user_id', 0);
        }
        parent::_preSave();
        if ($this->isInsert() && !$this->_newRecipients && XenForo_Application::getOptions()->sv_conversation_with_no_one)
        {
            if (!empty($this->_errors['recipients']) && 
                $this->_errors['recipients'] instanceof XenForo_Phrase &&
                $this->_errors['recipients']->getPhraseName() == 'please_enter_at_least_one_valid_recipient')
            {
                unset($this->_errors['recipients']);
            }
        }
    }

    protected function _postSave()
    {
        if ($this->isUpdate() && $this->isChanged('title'))
        {
            $this->_insertEditHistory();
        }

        if ($this->_firstMessageDw)
        {
            $this->_firstMessageDw->setOption(self::OPTION_INDEX_FOR_SEARCH, false);
            $this->_firstMessageDw->setExtraData(self::DATA_CONVERSATION, $this->getMergedData());
        }

        parent::_postSave();
    }

    protected function _postSaveAfterTransaction()
    {
        parent::_postSaveAfterTransaction();

        if ($this->getOption(self::OPTION_INDEX_FOR_SEARCH))
        {
            $this->_insertOrUpdateSearchIndex();
        }
    }

    public function delete()
    {
        parent::delete();
        // update search index outside the transaction
        $this->_deleteFromSearchIndex();
    }

    protected function _insertEditHistory()
    {
        $historyDw = XenForo_DataWriter::create('XenForo_DataWriter_EditHistory', XenForo_DataWriter::ERROR_SILENT);
        $historyDw->bulkSet(array(
            'content_type' => 'conversation',
            'content_id' => $this->get('conversation_id'),
            'edit_user_id' => XenForo_Visitor::getUserId(),
            'old_text' => $this->getExisting('title')
        ));
        $historyDw->save();
    }

    protected function _insertOrUpdateSearchIndex()
    {
        $dataHandler = $this->_getSearchDataHandler();
        if (!$dataHandler)
        {
            return;
        }

        if ($this->isInsert() || $this->isUpdate() && ($this->isChanged('recipients') || $this->isChanged('title')))
        {
            $indexer = new XenForo_Search_Indexer();
            $dataHandler->insertIntoIndex($indexer, $this->getMergedData(), null);
            if ($this->_firstMessageDw)
            {
                $dataHandler = $this->_getSearchDataHandlerForMessage();
                $dataHandler->insertIntoIndex($indexer, $this->_firstMessageDw->getMergedData(), $this->getMergedData());
            }
        }

        // limit how what can trigger re-indexing of the conversation
        if ($this->isUpdate() && $this->isChanged('recipients'))
        {
            $this->_insertOrUpdateSearchIndexForAllMessages();
        }
    }

    protected function _insertOrUpdateSearchIndexForAllMessages()
    {
        XenForo_Application::defer('SV_ConversationImprovements_Deferred_SingleConversationIndex', array(
            'conversationId' => $this->get('conversation_id'),
            'start' => 1
        ));
    }

    protected function _deleteFromSearchIndex()
    {
        $dataHandler = $this->_getSearchDataHandler();
        if (!$dataHandler)
        {
            return;
        }

        $indexer = new XenForo_Search_Indexer();
        $dataHandler->deleteFromIndex($indexer, $this->getMergedData());

        $messageHandler = $this->_getSearchDataHandlerForMessage();
        if ($messageHandler)
        {
            $messageHandler->deleteFromIndex($indexer, $this->_getDiscussionMessageIds());
        }
    }

    protected function _getDiscussionMessageIds()
    {
        $db = $this->_db;
        return $db->fetchCol("
            SELECT message_id
            FROM xf_conversation_message
            WHERE conversation_id = ?
        ", $this->get('conversation_id'));
    }

    protected function _getSearchDataHandler()
    {
        return XenForo_Search_DataHandler_Abstract::create('SV_ConversationImprovements_Search_DataHandler_Conversation');
    }

    public function _getSearchDataHandlerForMessage()
    {
        return XenForo_Search_DataHandler_Abstract::create('SV_ConversationImprovements_Search_DataHandler_ConversationMessage');
    }

    protected function _getConversationModel()
    {
        return $this->getModelFromCache('XenForo_Model_Conversation');
    }
}
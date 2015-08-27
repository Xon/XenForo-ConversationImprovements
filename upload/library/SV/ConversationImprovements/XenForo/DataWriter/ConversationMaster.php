<?php

class SV_ConversationImprovements_XenForo_DataWriter_ConversationMaster extends XFCP_SV_ConversationImprovements_XenForo_DataWriter_ConversationMaster
{
    const OPTION_INDEX_FOR_SEARCH = 'indexForSearch';

    protected function _getDefaultOptions()
    {
        $defaultOptions = parent::_getDefaultOptions();
        $defaultOptions[self::OPTION_INDEX_FOR_SEARCH] = true;
        return $defaultOptions;
    }

    protected function _postSave()
    {
        if ($this->_firstMessageDw)
        {
            $this->_firstMessageDw->setOption(self::OPTION_INDEX_FOR_SEARCH, false);
        }

        $this->_getConversationModel()->sv_deferRebuildUnreadCounters();

        parent::_postSave();
    }

    protected function _postSaveAfterTransaction()
    {
        $this->_getConversationModel()->sv_rebuildPendingUnreadCounters();

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
            XenForo_Application::defer('SearchIndexPartial', array(
                'contentType' => 'conversation_message',
                'contentIds' => $this->_getDiscussionMessageIds()
            ));
        }
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
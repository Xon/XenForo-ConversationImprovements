<?php

class SV_ConversationSearch_XenForo_DataWriter_ConversationMaster extends XFCP_SV_ConversationSearch_XenForo_DataWriter_ConversationMaster
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
        if ($this->_firstMessageDw && $this->getOption(self::OPTION_INDEX_FOR_SEARCH))
        {
            $this->_firstMessageDw->setOption(SV_ConversationSearch_Search_DataHandler_ConversationMessage::OPTION_INDEX_FOR_SEARCH, false);
        }

        parent::_postSave();

        if ($this->getOption(self::OPTION_INDEX_FOR_SEARCH))
        {
            $this->_insertOrUpdateSearchIndex();
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();
    }

    protected function _insertOrUpdateSearchIndex()
    {
        $dataHandler = $this->_getSearchDataHandler();
        if (!$dataHandler)
        {
            return;
        }

        $viewingUser = XenForo_Visitor::getInstance()->toArray();
        $conversationModel = $this->_getConversationModel();
        $conversation = $conversationModel->getConversationById($this->get('conversation_id'), $viewingUser['user_id']);

        $indexer = new XenForo_Search_Indexer();
        $dataHandler->insertIntoIndex($indexer, $this->getMergedData(), $conversation);

        if ($this->isUpdate())
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
        return XenForo_Search_DataHandler_Abstract::create('SV_ConversationSearch_Search_DataHandler_Conversation');
    }

    public function _getSearchDataHandlerForMessage()
    {
        return XenForo_Search_DataHandler_Abstract::create('SV_ConversationSearch_Search_DataHandler_ConversationMessage');
    }

    protected function _getConversationModel()
    {
        return $this->getModelFromCache('XenForo_Model_Conversation');
    }
}
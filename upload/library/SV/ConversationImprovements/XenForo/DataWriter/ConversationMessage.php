<?php

class SV_ConversationImprovements_XenForo_DataWriter_ConversationMessage extends XFCP_SV_ConversationImprovements_XenForo_DataWriter_ConversationMessage
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
		$this->_getConversationModel()->sv_deferRebuildUnreadCounters();
        parent::_postSave();
    }

    protected function _postSaveAfterTransaction()
    {
        $this->_getConversationModel()->sv_rebuildPendingUnreadCounters();

        parent::_postSaveAfterTransaction();

        if ($this->getOption(self::OPTION_INDEX_FOR_SEARCH))
        {
            $this->_insertIntoSearchIndex();
        }
    }

    public function delete()
    {
        parent::delete();
        // update search index outside the transaction
        $this->_deleteFromSearchIndex();
    }

    protected function _insertIntoSearchIndex()
    {
        $dataHandler = $this->_getSearchDataHandler();
        if (!$dataHandler)
        {
            return;
        }

        $viewingUser = XenForo_Visitor::getInstance()->toArray();
        $conversationModel = $this->_getConversationModel();
        $conversation_id = $this->get('conversation_id');
        $conversations = $conversationModel->getConversationsByIds($conversation_id);
        $conversation = isset($conversations[$conversation_id]) ? $conversations[$this->get('conversation_id')] : null;

        $indexer = new XenForo_Search_Indexer();
        $dataHandler->insertIntoIndex($indexer, $this->getMergedData(), $conversation);
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
    }

    public function _getSearchDataHandler()
    {
        return XenForo_Search_DataHandler_Abstract::create('SV_ConversationImprovements_Search_DataHandler_ConversationMessage');
    }

    protected function _getConversationModel()
    {
        return $this->getModelFromCache('XenForo_Model_Conversation');
    }
}
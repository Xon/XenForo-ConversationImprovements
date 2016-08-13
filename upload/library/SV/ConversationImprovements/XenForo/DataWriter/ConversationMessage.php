<?php

class SV_ConversationImprovements_XenForo_DataWriter_ConversationMessage extends XFCP_SV_ConversationImprovements_XenForo_DataWriter_ConversationMessage
{
    const OPTION_INDEX_FOR_SEARCH = 'indexForSearch';
    const OPTION_LOG_EDIT = 'logEdit';

    const DATA_CONVERSATION = 'conversationInfo';

    protected function _getConversationInfo()
    {
        if (!$conversation = $this->getExtraData(self::DATA_CONVERSATION))
        {
            $conversation_id = $this->get('conversation_id');
            $conversations = $this->_getConversationModel()->getConversationsByIds($conversation_id);
            $conversation = isset($conversations[$conversation_id]) ? $conversations[$conversation_id] : null;

            $this->setExtraData(self::DATA_CONVERSATION, $conversation ? $conversation : array());
        }

        return $conversation;
    }

    protected function _getFields()
    {
        $fields = parent::_getFields();
        if (!isset($fields['xf_conversation_message']['likes']))
        {
            $fields['xf_conversation_message']['_likes'] = array('type' => self::TYPE_UINT_FORCED, 'default' => 0);
            $fields['xf_conversation_message']['like_users'] = array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}');
        }
        $fields["xf_conversation_message"]['last_edit_date'] = array('type' => self::TYPE_UINT, 'default' => 0);
        $fields["xf_conversation_message"]['last_edit_user_id'] = array('type' => self::TYPE_UINT, 'default' => 0);
        $fields["xf_conversation_message"]['edit_count'] = array('type' => self::TYPE_UINT_FORCED, 'default' => 0);
        return $fields;
    }

    protected function _getDefaultOptions()
    {
        $defaultOptions = parent::_getDefaultOptions();
        $defaultOptions[self::OPTION_INDEX_FOR_SEARCH] = true;
        $options = XenForo_Application::get('options');
        $defaults[self::OPTION_LOG_EDIT] = $options->editHistory['enabled'];
        return $defaultOptions;
    }

    protected function _preSave()
    {
        if ($this->isUpdate() && $this->isChanged('message'))
        {
            if (!$this->isChanged('last_edit_date'))
            {
                $this->set('last_edit_date', XenForo_Application::$time);
                if (!$this->isChanged('last_edit_user_id'))
                {
                    $this->set('last_edit_user_id', XenForo_Visitor::getUserId());
                }
            }

            if (!$this->isChanged('edit_count'))
            {
                $this->set('edit_count', $this->get('edit_count') + 1);
            }
        }
        if ($this->isChanged('edit_count') && $this->get('edit_count') == 0)
        {
            $this->set('last_edit_date', 0);
        }
        if (!$this->get('last_edit_date'))
        {
            $this->set('last_edit_user_id', 0);
        }
        return parent::_preSave();
    }

    protected function _postSave()
    {
        if ($this->isUpdate() && $this->isChanged('message'))
        {
            $this->_insertEditHistory();
        }
        return parent::_postSave();
    }

    protected function _postSaveAfterTransaction()
    {
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

    protected function _insertEditHistory()
    {
        $historyDw = XenForo_DataWriter::create('XenForo_DataWriter_EditHistory', XenForo_DataWriter::ERROR_SILENT);
        $historyDw->bulkSet(array(
            'content_type' => 'conversation_message',
            'content_id' => $this->get('message_id'),
            'edit_user_id' => XenForo_Visitor::getUserId(),
            'old_text' => $this->getExisting('message')
        ));
        $historyDw->save();
    }

    protected function _insertIntoSearchIndex()
    {
        $dataHandler = $this->_getSearchDataHandler();
        if (!$dataHandler)
        {
            return;
        }

        $viewingUser = XenForo_Visitor::getInstance()->toArray();
        $indexer = new XenForo_Search_Indexer();
        $dataHandler->insertIntoIndex($indexer, $this->getMergedData(), $this->_getConversationInfo());
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
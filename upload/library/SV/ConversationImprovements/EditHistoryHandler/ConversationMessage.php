<?php

class SV_ConversationImprovements_EditHistoryHandler_ConversationMessage extends XenForo_EditHistoryHandler_Abstract
{
    protected $_prefix = 'conversations/message';

    protected function _getContent($contentId, array $viewingUser)
    {
        $conversationModel = $this->_getConversationModel();

        return $conversationModel->getConversationMessageById($contentId, array(
            'includeConversationTitle' => true
        ));
    }

    protected function _canViewHistoryAndContent(array $content, array $viewingUser)
    {
        $conversationModel = $this->_getConversationModel();

        return $conversationModel->canViewConversation($content, $null, $viewingUser) &&
               $conversationModel->canViewMessageHistory($content, $content, $null, $viewingUser);
    }

    protected function _canRevertContent(array $content, array $viewingUser)
    {
        $conversationModel = $this->_getConversationModel();

        return $conversationModel->canEditMessage($content, $content, $null, $viewingUser);
    }

    public function getText(array $content)
    {
        return htmlspecialchars($content['message']);
    }

    public function getTitle(array $content)
    {
        //return new XenForo_Phrase('post_in_thread_x', array('title' => $content['title']));
        return htmlspecialchars($content['title']); // TODO
    }

    public function getBreadcrumbs(array $content)
    {
        return array(
            array(
                'href' => XenForo_Link::buildPublicLink('full:conversations'),
                'value' => new XenForo_Phrase('conversations')
            ),
            array(
                'href' => XenForo_Link::buildPublicLink('full:conversations/message', $content, array('message_id' => $content['message_id'])),
                'value' => $content['title']
            )
        );
    }

    public function getNavigationTab()
    {
        return 'conversations';
    }

    public function formatHistory($string, XenForo_View $view)
    {
        return htmlspecialchars($string);
    }

    public function revertToVersion(array $content, $revertCount, array $history, array $previous = null)
    {
        $dw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage', XenForo_DataWriter::ERROR_SILENT);
        $dw->setExistingData($content);
        $dw->set('message', $history['old_text']);
        $dw->set('edit_count', $dw->get('edit_count') + 1);
        if ($dw->get('edit_count'))
        {
            if (!$previous || $previous['edit_user_id'] != $content['user_id'])
            {
                // if previous is a mod edit, don't show as it may have been hidden
                $dw->set('last_edit_date', 0);
            }
            else if ($previous && $previous['edit_user_id'] == $content['user_id'])
            {
                $dw->set('last_edit_date', $previous['edit_date']);
                $dw->set('last_edit_user_id', $previous['edit_user_id']);
            }
        }

        return $dw->save();
    }

    protected $_conversationModel = null;

    protected function _getConversationModel()
    {
        if ($this->_conversationModel === null)
        {
            $this->_conversationModel = XenForo_Model::create('XenForo_Model_Conversation');
        }
        return $this->_conversationModel;
    }
}

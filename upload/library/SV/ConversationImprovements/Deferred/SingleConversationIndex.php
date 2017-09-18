<?php


class SV_ConversationImprovements_Deferred_SingleConversationIndex extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        if (!isset($data['conversationId']))
        {
            return false;
        }

        $conversationId = $data['conversationId'];

        /** @var SV_ConversationImprovements_XenForo_Model_Conversation|null $conversationModel */
        $conversationModel = XenForo_Model::create('XenForo_Model_Conversation');
        if (!class_exists('XFCP_SV_ConversationImprovements_XenForo_Model_Conversation', false))
        {
            return false;
        }

        $conversation = $conversationModel->getConversationMasterById($conversationId);
        $messagesPerPage = XenForo_Application::get('options')->messagesPerPage;

        $messageFetchOptions = [
            'perPage' => $messagesPerPage < 100 ? 100 : $messagesPerPage,
            'page'    => $data['start'],
            'join'    => 0
        ];

        $messages = $conversationModel->getConversationMessages($conversationId, $messageFetchOptions);

        if (empty($messages))
        {
            return false;
        }

        XenForo_Application::defer(
            'SearchIndexPartial',
            [
                'contentType' => 'conversation_message',
                'contentIds'  => XenForo_Application::arrayColumn($messages, 'message_id')
            ]
        );

        $data['start']++;
        $lastPage = intval(ceil(($conversation['reply_count'] + 1) / $messageFetchOptions['perPage']));
        if ($data['start'] > $lastPage)
        {
            return $data;
        }

        return false;
    }


    public function canCancel()
    {
        return false;
    }
}

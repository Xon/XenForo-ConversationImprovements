<?php

class SV_ConversationImprovements_XenForo_ControllerPublic_Conversation extends XFCP_SV_ConversationImprovements_XenForo_ControllerPublic_Conversation
{
    public function actionView()
    {
        $response = parent::actionView();

        if ($response instanceof XenForo_ControllerResponse_View)
        {
            $conversation = $response->params['conversation'];
            $response->params['canViewIps'] = $this->_getConversationModel()->canViewIps($conversation);
        }

        return $response;
    }

    public function actionIp()
    {
        $conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
        $messageId = $this->_input->filterSingle('m', XenForo_Input::UINT);

        list($conversation, $conversationMessage) = $this->_getConversationAndMessageOrError($messageId, $conversationId);

        if (!$this->_getConversationModel()->canViewIps($conversation, $errorPhraseKey))
        {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $ipInfo = $this->getModelFromCache('XenForo_Model_Ip')->getContentIpInfo($conversationMessage);

        if (empty($ipInfo['contentIp']))
        {
            return $this->responseError(new XenForo_Phrase('no_ip_information_available'));
        }

        $viewParams = array(
            'conversation' => $conversation,
            'message' => $conversationMessage,
            'ipInfo' => $ipInfo
        );

        return $this->responseView('SV_ConversationImprovements_ViewPublic_Conversation_Message_Ip', 'sv_conversation_message_ip', $viewParams);
    }

    protected function _getUserModel()
    {
        return $this->getModelFromCache('XenForo_Model_User');
    }
}
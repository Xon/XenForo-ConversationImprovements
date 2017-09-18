<?php

class SV_ConversationImprovements_XenForo_ControllerPublic_Conversation extends XFCP_SV_ConversationImprovements_XenForo_ControllerPublic_Conversation
{
    public function actionLike()
    {
        $conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
        $messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);

        list($conversation, $conversationMessage) = $this->_getConversationAndMessageOrError(
            $messageId, $conversationId
        );

        /** @var SV_ConversationImprovements_XenForo_Model_Conversation $conversationModel */
        $conversationModel = $this->_getConversationModel();
        /** @var XenForo_Model_Like $likeModel */
        $likeModel = $this->getModelFromCache('XenForo_Model_Like');

        if (!$conversationModel->canLikeConversationMessage($conversationMessage, $conversation, $errorPhraseKey))
        {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        if (!isset($conversationMessage['likes']))
        {
            throw $this->getNoPermissionResponseException();
        }

        $existingLike = $likeModel->getContentLikeByLikeUser(
            'conversation_message', $messageId, XenForo_Visitor::getUserId()
        );

        if ($this->_request->isPost())
        {
            if ($existingLike)
            {
                $latestUsers = $likeModel->unlikeContent($existingLike);
            }
            else
            {
                $latestUsers = $likeModel->likeContent(
                    'conversation_message', $messageId, $conversationMessage['user_id']
                );
            }

            $liked = ($existingLike ? false : true);

            if ($this->_noRedirect() && $latestUsers !== false)
            {
                $conversationMessage['likeUsers'] = $latestUsers;
                $conversationMessage['likes'] += ($liked ? 1 : -1);
                $conversationMessage['like_date'] = ($liked ? XenForo_Application::$time : 0);

                $viewParams = [
                    'message'      => $conversationMessage,
                    'conversation' => $conversation,
                    'liked'        => $liked,
                ];

                return $this->responseView(
                    'SV_ConversationImprovements_ViewPublic_Conversation_Message_LikeConfirmed', '', $viewParams
                );
            }
            else
            {
                return $this->responseRedirect(
                    XenForo_ControllerResponse_Redirect::SUCCESS,
                    XenForo_Link::buildPublicLink(
                        'conversations/message', $conversation, ['message_id' => $conversationMessage['message_id']]
                    )
                );
            }
        }
        else
        {
            $viewParams = [
                'message'      => $conversationMessage,
                'conversation' => $conversation,
                'like'         => $existingLike
            ];

            return $this->responseView(
                'SV_ConversationImprovements_ViewPublic_Conversation_Message_Like', 'sv_conversation_message_like',
                $viewParams
            );
        }
    }

    public function actionLikes()
    {
        $conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
        $messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);

        list($conversation, $conversationMessage) = $this->_getConversationAndMessageOrError(
            $messageId, $conversationId
        );

        $page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
        $perPage = 100;

        /** @var XenForo_Model_Like $likeModel */
        $likeModel = $this->getModelFromCache('XenForo_Model_Like');

        $total = $likeModel->countContentLikes('conversation_message', $messageId);
        if (!$total)
        {
            return $this->responseError(new XenForo_Phrase('sv_no_one_has_liked_this_conversation_message_yet'));
        }

        $likes = $likeModel->getContentLikes(
            'conversation_message', $messageId, [
                                      'page'    => $page,
                                      'perPage' => $perPage
                                  ]
        );

        $viewParams = [
            'message'      => $conversationMessage,
            'conversation' => $conversation,

            'likes'   => $likes,
            'page'    => $page,
            'perPage' => $perPage,
            'total'   => $total,
            'hasMore' => ($page * $perPage) < $total
        ];

        return $this->responseView(
            'SV_ConversationImprovements_ViewPublic_Conversation_Message_Likes', 'sv_conversation_message_likes',
            $viewParams
        );
    }

    public function actionIp()
    {
        $conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
        $messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);

        list($conversation, $conversationMessage) = $this->_getConversationAndMessageOrError(
            $messageId, $conversationId
        );

        /** @var SV_ConversationImprovements_XenForo_Model_Conversation $conversationModel */
        $conversationModel = $this->_getConversationModel();
        /** @var XenForo_Model_Ip $ipModel */
        $ipModel = $this->getModelFromCache('XenForo_Model_Ip');

        if (!$conversationModel->canViewIps($conversation, $errorPhraseKey))
        {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $ipInfo = $ipModel->getContentIpInfo($conversationMessage);

        if (empty($ipInfo['contentIp']))
        {
            return $this->responseError(new XenForo_Phrase('no_ip_information_available'));
        }

        $viewParams = [
            'conversation' => $conversation,
            'message'      => $conversationMessage,
            'ipInfo'       => $ipInfo
        ];

        return $this->responseView(
            'SV_ConversationImprovements_ViewPublic_Conversation_Message_Ip', 'sv_conversation_message_ip', $viewParams
        );
    }

    public function actionMessageHistory()
    {
        $conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
        $messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);

        $this->_request->setParam('content_type', 'conversation_message');
        $this->_request->setParam('content_id', $messageId);

        return $this->responseReroute('XenForo_ControllerPublic_EditHistory', 'index');
    }

    public function actionConversationHistory()
    {
        $conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);

        $this->_request->setParam('content_type', 'conversation');
        $this->_request->setParam('content_id', $conversationId);

        return $this->responseReroute('XenForo_ControllerPublic_EditHistory', 'index');
    }

    public function actionView()
    {
        $response = parent::actionView();

        if ($response instanceof XenForo_ControllerResponse_View && !empty($response->params['conversation']))
        {
            /** @var SV_ConversationImprovements_XenForo_Model_Conversation $conversationModel */
            $conversationModel = $this->_getConversationModel();

            $conversation = $response->params['conversation'];
            $response->params['canViewConversationHistory'] = $conversationModel->canViewConversationHistory(
                $conversation
            );
        }

        return $response;
    }

    /**
     * @return XenForo_Model|XenForo_Model_User
     */
    protected function _getUserModel()
    {
        return $this->getModelFromCache('XenForo_Model_User');
    }
}

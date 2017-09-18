<?php

class SV_ConversationImprovements_ViewPublic_Conversation_Message_LikeConfirmed extends XenForo_ViewPublic_Base
{
    public function renderJson()
    {
        $message = $this->_params['message'];
        $conversation = $this->_params['conversation'];

        if (!empty($message['likes']))
        {
            $params = [
                'message' => $message,
                'likesUrl' => XenForo_Link::buildPublicLink(
                    'conversations/likes', $conversation, ['message_id' => $message['message_id']]
                )
            ];

            /** @var XenForo_ViewRenderer_Json $renderer */
            $renderer = $this->_renderer;
            $output = $renderer->getDefaultOutputArray(get_class($this), $params, 'likes_summary');
        }
        else
        {
            $output = ['templateHtml' => '', 'js' => '', 'css' => ''];
        }

        $output += XenForo_ViewPublic_Helper_Like::getLikeViewParams($this->_params['liked']);

        return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
    }
}

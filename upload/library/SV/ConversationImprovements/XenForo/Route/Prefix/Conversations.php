<?php

class SV_ConversationImprovements_XenForo_Route_Prefix_Conversations extends XFCP_SV_ConversationImprovements_XenForo_Route_Prefix_Conversations
{
    public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
    {
        if (isset($data['message_id']) && 
            !isset($extraParams['m']) &&
            !isset($extraParams['message_id']) &&
            ($action == 'message'))
        {
            $extraParams['message_id'] = $data['message_id'];
        }
        return parent::buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, $extraParams);
    }
}
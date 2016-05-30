<?php

class SV_ConversationImprovements_XenES_Model_ElasticSearch extends XFCP_SV_ConversationImprovements_XenES_Model_ElasticSearch
{
    public function optimizeMapping($type, $deleteFirst = true, array $extra = array())
    {
        if (isset(SV_ConversationImprovements_Installer::$extraMappings[$type]))
        {
            $extra = array_merge($extra, SV_ConversationImprovements_Installer::$extraMappings[$type]);
        }

        parent::optimizeMapping($type, $deleteFirst, $extra);
    }
}
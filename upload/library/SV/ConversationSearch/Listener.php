<?php

class SV_ConversationSearch_Listener
{
    const AddonNameSpace = 'SV_ConversationSearch';

    public static function install($existingAddOn, $addOnData)
    {
        $version = isset($existingAddOn['version_id']) ? $existingAddOn['version_id'] : 0;

        $db = XenForo_Application::getDb();

        $db->query("
            INSERT IGNORE INTO xf_content_type_field
                (content_type, field_name, field_value)
            VALUES
                ('conversation', 'search_handler_class', 'SV_ConversationSearch_Search_DataHandler_Conversation'),
                ('conversation_message', 'search_handler_class', 'SV_ConversationSearch_Search_DataHandler_ConversationMessage')
        ");

        XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();

        return true;
    }

    public static function uninstall()
    {
        $db->query("
            DELETE FROM xf_content_type_field
            WHERE xf_content_type_field.field_value in ('SV_ConversationSearch_Search_DataHandler_Conversation','SV_ConversationSearch_Search_DataHandler_ConversationMessage')
        ");


        XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
        return true;
    }

    public static function load_class($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.'_'.$class;
    }
}

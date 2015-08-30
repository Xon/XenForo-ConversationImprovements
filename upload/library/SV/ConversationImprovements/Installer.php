<?php

class SV_ConversationImprovements_Installer
{
    const AddonNameSpace = 'SV_ConversationImprovements_';

    public static function install($existingAddOn, $addOnData)
    {
        $version = isset($existingAddOn['version_id']) ? $existingAddOn['version_id'] : 0;

        $addonsToUninstall = array('SV_ConversationSearch' => array(), 'SVConversationPermissions' => array());
        SV_Utils_Install::removeOldAddons($addonsToUninstall);

        $db = XenForo_Application::getDb();

        $db->query("
            INSERT IGNORE INTO xf_content_type_field
                (content_type, field_name, field_value)
            VALUES
                ('conversation', 'search_handler_class', '".self::AddonNameSpace."Search_DataHandler_Conversation'),
                ('conversation_message', 'search_handler_class', '".self::AddonNameSpace."Search_DataHandler_ConversationMessage')
        ");

        XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();

        return true;
    }

    public static function uninstall()
    {
        $db = XenForo_Application::getDb();

        $db->query("
            DELETE FROM xf_content_type_field
            WHERE xf_content_type_field.field_value in ('".self::AddonNameSpace."Search_DataHandler_Conversation','".self::AddonNameSpace."Search_DataHandler_ConversationMessage')
        ");


        XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
        return true;
    }
}

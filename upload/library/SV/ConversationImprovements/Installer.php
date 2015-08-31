<?php

class SV_ConversationImprovements_Installer
{
    const AddonNameSpace = 'SV_ConversationImprovements_';

    public static function install($existingAddOn, $addOnData)
    {
        $version = isset($existingAddOn['version_id']) ? $existingAddOn['version_id'] : 0;

        $addonsToUninstall = array('SV_ConversationSearch' => array(), 'SVConversationPermissions' => array());
        SV_Utils_Install::removeOldAddons($addonsToUninstall);

        SV_Utils_Install::addColumn('xf_conversation_message', 'likes', 'INT UNSIGNED NOT NULL DEFAULT 0');
        SV_Utils_Install::addColumn('xf_conversation_message', 'like_users', 'BLOB NOT NULL');

        $db = XenForo_Application::getDb();

        $db->query("
            INSERT IGNORE INTO xf_content_type_field
                (content_type, field_name, field_value)
            VALUES
                ('conversation', 'search_handler_class', '".self::AddonNameSpace."Search_DataHandler_Conversation'),
                ('conversation_message', 'like_handler_class', '".self::AddonNameSpace."LikeHandler_ConversationMessage'),
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
            WHERE xf_content_type_field.field_value like '".self::AddonNameSpace."%'
        ");

        // if XF ever supports likes on conversations this will break it:
        //SV_Utils_Install::dropColumn('xf_conversation_message', 'likes');
        //SV_Utils_Install::dropColumn('xf_conversation_message', 'like_users');

        XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
        return true;
    }
}

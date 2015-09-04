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
                ('conversation_message', 'alert_handler_class', '".self::AddonNameSpace."AlertHandler_ConversationMessage'),
                ('conversation_message', 'news_feed_handler', '".self::AddonNameSpace."NewsFeedHandler_ConversationMessage'),
                ('conversation_message', 'search_handler_class', '".self::AddonNameSpace."Search_DataHandler_ConversationMessage')
        ");

        XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();

        $requireIndexing = array();

        if ($version == 0)
        {
            $requireIndexing['conversation'] = true;
            $requireIndexing['conversation_message'] = true;
        }

        // if Elastic Search is installed, determine if we need to push optimized mappings for the search types
        SV_Utils_Install::updateXenEsMapping($requireIndexing, array(
            'conversation' => array(
                "properties" => array(
                    "recipients" => array("type" => "long"),
                    "conversation" => array("type" => "long"),
                )
            ),
            'conversation_message' => array(
                "properties" => array(
                    "recipients" => array("type" => "long"),
                    "conversation" => array("type" => "long"),
                )
            )
        ));

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

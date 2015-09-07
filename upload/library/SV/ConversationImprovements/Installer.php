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
        SV_Utils_Install::modifyColumn('xf_conversation_message', 'like_users', 'BLOB', 'BLOB');
        SV_Utils_Install::addColumn('xf_conversation_message', 'like_users', 'BLOB');

        $db = XenForo_Application::getDb();

        $db->query("insert ignore into xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
            select distinct user_group_id, user_id, convert(permission_group_id using utf8), 'canReply', permission_value, permission_value_int
            from xf_permission_entry
            where permission_group_id = 'conversation' and permission_id in ('start')
        ");

        $db->query("insert ignore into xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
            select distinct user_group_id, user_id, convert(permission_group_id using utf8), 'replyLimit', permission_value, -1
            from xf_permission_entry
            where permission_group_id = 'conversation' and permission_id in ('start')
        ");

        $db->query("
            INSERT IGNORE INTO xf_content_type_field
                (content_type, field_name, field_value)
            VALUES
                ('conversation', 'search_handler_class', '".self::AddonNameSpace."Search_DataHandler_Conversation'),
                ('conversation_message', 'like_handler_class', '".self::AddonNameSpace."LikeHandler_ConversationMessage'),
                ('conversation_message', 'alert_handler_class', '".self::AddonNameSpace."AlertHandler_ConversationMessage'),
                ('conversation_message', 'news_feed_handler_class', '".self::AddonNameSpace."NewsFeedHandler_ConversationMessage'),
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

        $db->query("
            DELETE FROM xf_permission_entry
            where permission_group_id = 'conversation' and permission_id in ('replyLimit', 'canReply')
        ");

        // if XF ever supports likes on conversations this will break it:
        //SV_Utils_Install::dropColumn('xf_conversation_message', 'likes');
        //SV_Utils_Install::dropColumn('xf_conversation_message', 'like_users');

        XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
        return true;
    }
}

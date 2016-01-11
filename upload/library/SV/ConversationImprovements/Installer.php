<?php

class SV_ConversationImprovements_Installer
{
    const AddonNameSpace = 'SV_ConversationImprovements_';

    public static function install($existingAddOn, $addOnData)
    {
        $version = isset($existingAddOn['version_id']) ? $existingAddOn['version_id'] : 0;

        $addonsToUninstall = array('SV_ConversationSearch' => array(), 'SVConversationPermissions' => array());
        SV_Utils_Install::removeOldAddons($addonsToUninstall);

        if ($version > 1000301 && $version <= 1020200)
        {
            SV_Utils_Install::renameColumn('xf_conversation_message', 'likes', '_likes', 'INT UNSIGNED NOT NULL DEFAULT 0');
        }
        $db = XenForo_Application::get('db');
        if (!$db->fetchRow("SHOW COLUMNS FROM `xf_conversation_message` WHERE Field = 'likes'"))
        {
            SV_Utils_Install::addColumn('xf_conversation_message', '_likes', 'INT UNSIGNED NOT NULL DEFAULT 0');
        }
        if ($version && $version <= 1010100)
        {
            SV_Utils_Install::modifyColumn('xf_conversation_message', 'like_users', 'BLOB', 'BLOB');
        }
        SV_Utils_Install::addColumn('xf_conversation_message', 'like_users', 'BLOB');
        SV_Utils_Install::addColumn('xf_conversation_message', 'edit_count', 'int not null default 0');
        SV_Utils_Install::addColumn('xf_conversation_message', 'last_edit_date', 'int not null default 0');
        SV_Utils_Install::addColumn('xf_conversation_message', 'last_edit_user_id', 'int not null default 0');
        SV_Utils_Install::addColumn('xf_conversation_master', 'conversation_edit_count', 'int not null default 0');
        SV_Utils_Install::addColumn('xf_conversation_master', 'conversation_last_edit_date', 'int not null default 0');
        SV_Utils_Install::addColumn('xf_conversation_master', 'conversation_last_edit_user_id', 'int not null default 0');

        $db = XenForo_Application::getDb();

        if ($version < 1010100)
        {
            $db->query("insert ignore into xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                select distinct user_group_id, user_id, convert(permission_group_id using utf8), 'canReply', permission_value, permission_value_int
                from xf_permission_entry
                where permission_group_id = 'conversation' and permission_id in ('start')
            ");
        }

        if ($version < 1010200)
        {
            $db->query("insert ignore into xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                select distinct user_group_id, user_id, convert(permission_group_id using utf8), 'replyLimit', permission_value, -1
                from xf_permission_entry
                where permission_group_id = 'conversation' and permission_id in ('start')
            ");
        }

        if ($version < 1020003)
        {
            $db->query("insert ignore into xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                select distinct user_group_id, user_id, convert(permission_group_id using utf8), 'sv_manageConversation', permission_value, permission_value_int
                from xf_permission_entry
                where permission_group_id = 'conversation' and permission_id in ('editAnyPost')
            ");
        }

        $db->query("
            INSERT IGNORE INTO xf_content_type_field
                (content_type, field_name, field_value)
            VALUES
                ('conversation', 'search_handler_class', '".self::AddonNameSpace."Search_DataHandler_Conversation'),
                ('conversation', 'edit_history_handler_class', '".self::AddonNameSpace."EditHistoryHandler_Conversation'),
                ('conversation_message', 'edit_history_handler_class', '".self::AddonNameSpace."EditHistoryHandler_ConversationMessage'),
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
            where permission_group_id = 'conversation' and permission_id in ('replyLimit', 'canReply', 'sv_manageConversation')
        ");

        // if XF ever supports likes on conversations this will break it:
        //SV_Utils_Install::dropColumn('xf_conversation_message', 'likes');
        // if XF ever supports History on conversations this will break it:
        //SV_Utils_Install::dropColumn('xf_conversation_message', 'like_users');
        //SV_Utils_Install::dropColumn('xf_conversation_message', 'edit_count');
        //SV_Utils_Install::dropColumn('xf_conversation_message', 'last_edit_date');
        //SV_Utils_Install::dropColumn('xf_conversation_message', 'last_edit_user_id');
        //SV_Utils_Install::dropColumn('xf_conversation_master', 'conversation_edit_count');
        //SV_Utils_Install::dropColumn('xf_conversation_master', 'conversation_last_edit_date');
        //SV_Utils_Install::dropColumn('xf_conversation_master', 'conversation_last_edit_user_id');

        /*
        $db->query("
            DELETE FROM xf_liked_content
            WHERE content_type = 'conversation_message';
        ");

        $db->query("
            DELETE FROM xf_edit_history
            WHERE content_type in ('conversation', 'conversation_message');
        ");
        */

        XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
        return true;
    }
}

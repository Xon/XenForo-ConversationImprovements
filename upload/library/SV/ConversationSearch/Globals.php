<?php

// This class is used to encapsulate global state between layers without using $GLOBAL[] or
// relying on the consumer being loaded correctly by the dynamic class autoloader
class SV_ConversationSearch_Globals
{
    public static $UsersToUpdate = null;

    // workaround for a bug in Waindigo_EmailReport_Extend_XenForo_Model_Report::reportContent
    public static $reportId = false;

    private function __construct() {}
}

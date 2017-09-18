<?php

class SV_ConversationImprovements_Listener
{
    public static function load_class($class, array &$extend)
    {
        $extend[] = 'SV_ConversationImprovements_' . $class;
    }
}

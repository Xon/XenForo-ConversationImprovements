<?php

class SV_ConversationImprovements_Listener
{
    const AddonNameSpace = 'SV_ConversationImprovements_';

    public static function load_class($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.$class;
    }
}

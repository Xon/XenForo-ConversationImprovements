<?php

class SV_ConversationImprovements_XenES_Model_Elasticsearch extends XFCP_SV_ConversationImprovements_XenES_Model_Elasticsearch
{
    public function getOptimizableMappingFor($type)
    {
        switch($type)
        {
            case 'conversation':
            case 'conversation_message':
                $mapping = array(
                    "properties" => array(
                        "recipients" => array("type" => "long"),
                        "conversation" => array("type" => "long"),
                        )
                    );
                break;
            default:
                $mapping = array();
                break;
        }
        if (is_callable('parent::getOptimizableMapping'))
        {
            $mapping = array_merge(parent::getOptimizableMappingFor($type), $mapping);
        }
        return $mapping;
    }

    // copied from XenES_Model_Elasticsearch, as it isn't extendable
    public function getOptimizableMappings(array $mappingTypes = null, $mappings = null)
    {
        if ($mappingTypes === null)
        {
            $mappingTypes = $this->getAllSearchContentTypes();
        }
        if ($mappings === null)
        {
            $mappings = $this->getMappings();
        }

        $optimizable = array();

        foreach ($mappingTypes AS $type)
        {
            if (!$mappings || !isset($mappings->$type)) // no index or no mapping
            {
                $optimize = true;
            }
            else
            {
                // our change
                $expectedMapping = array_merge(static::$optimizedGenericMapping, $this->getOptimizableMappingFor($type));
                $optimize = $this->_verifyMapping($mappings->$type, $expectedMapping);
            }

            if ($optimize)
            {
                $optimizable[] = $type;
            }
        }

        return $optimizable;
    }

    public function optimizeMapping($type, $deleteFirst = true, array $extra = array())
    {
        parent::optimizeMapping($type, $deleteFirst, array_merge($extra, $this->getOptimizableMappingFor($type)));
    }
}
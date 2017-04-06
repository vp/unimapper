<?php

namespace UniMapper\Adapter;

use UniMapper\Entity\Reflection;
use UniMapper\Entity\Reflection\Property;

abstract class Mapping
{

    public function mapValue(Property $property, $value)
    {
        return $value;
    }

    public function unmapValue(Property $property, $value)
    {
        return $value;
    }
    
    public function unmapFilterJoins(Reflection $reflection, array $filter)
    {
        return [];
    }
    
    public function unmapFilterJoinProperty(Reflection $reflection, $name)
    {
        return $name;
    }

    /**
     * Unmap selection
     *
     * @param \UniMapper\Entity\Reflection $reflection   Entity reflection
     * @param array                        $selection    Selection array
     * @param \UniMapper\Association[]     $associations Optional associations
     * @param \UniMapper\Mapper            $mapper       Mapper instance
     *
     * @return array
     */
    public function unmapSelection(Reflection $reflection, array $selection, array $associations = [], \UniMapper\Mapper $mapper)
    {
        return $selection;
    }

}
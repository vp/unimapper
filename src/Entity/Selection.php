<?php

namespace UniMapper\Entity;


class Selection
{
    /**
     * Generates selection for only entity properties
     *
     * @param \UniMapper\Entity\Reflection $entityReflection Entity reflection
     *
     * @return array
     */
    public static function generateEntitySelection(Reflection $entityReflection)
    {
        return self::_traverseEntityForSelection($entityReflection);
    }

    /**
     * Internal function for entity property selection generation
     *
     * @param \UniMapper\Entity\Reflection $entityReflection Entity reflection
     * @param array                        $nesting          Nesting check
     *
     * @return array
     */
    private static function _traverseEntityForSelection(Reflection $entityReflection, &$nesting = [])
    {
        $nesting[] = $entityReflection->getName();
        $selection = [];
        foreach ($entityReflection->getProperties() as $property) {
            // Exclude not mapped
            if (/*!$property->hasOption(Reflection\Property\Option\Computed::KEY)
                &&*/ !$property->hasOption(Reflection\Property\Option\Assoc::KEY)
                /*&& !($property->hasOption(Reflection\Property\Option\Map::KEY) && $property->getOption(Reflection\Property\Option\Map::KEY) === false)*/
            ) {
                if ($property->getType() === Reflection\Property::TYPE_COLLECTION || $property->getType() === Reflection\Property::TYPE_ENTITY) {
                    $targetReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                    if (in_array($targetReflection->getName(), $nesting) !== false) {
                        continue;
                    }
                    $selection[$property->getName()] = self::_traverseEntityForSelection($targetReflection, $nesting);
                } else {
                    $selection[] = $property->getName();
                }
            } else if ($property->hasOption(Reflection\Property\Option\Computed::KEY)) {
                $selection[] = $property->getName();
            }
        }

        // public properties handling
        foreach ($entityReflection->getPublicProperties() as $publicProperty) {
            $selection[] = $publicProperty;
        }

        array_pop($nesting);
        return $selection;
    }
    
    public static function checkEntitySelection(Reflection $reflection, array $selection)
    {
        $returnSelection = [];
        foreach ($selection as $index => $value) {
            if (is_scalar($value)) {
                $property = $reflection->getProperty($value);
                if ($property->getType() === Reflection\Property::TYPE_ENTITY
                    || $property->getType() === Reflection\Property::TYPE_COLLECTION
                ) {
                    $targetReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                    $returnSelection[$value] = self::generateEntitySelection($targetReflection);
                } else {
                    $returnSelection[$index] = $value;
                }
            } else if (is_array($value)) {
                $property = $reflection->getProperty($index);
                if ($property->getType() === Reflection\Property::TYPE_ENTITY
                    || $property->getType() === Reflection\Property::TYPE_COLLECTION
                ) {
                    $targetReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                    $returnSelection[$index] = self::checkEntitySelection($targetReflection, $value);
                } else {
                    $returnSelection[$index] = $value;
                }
            } else {
                $returnSelection[$index] = $value;
            }
        }

        return $returnSelection;
    }


    /**
     * Normalize selection structure for query
     *
     * - Filter out properties witch are not selectable by query (assoc, computed, ...)
     * - Reformat partial selections ['foo' => ['bar', 'baz']]  to [0 => [0 => 'foo', 1 => ['bar', 'baz']]]
     * - merge duplicates
     *
     * @param \UniMapper\Entity\Reflection $entityReflection Entity reflection
     * @param array                        $selection        Selection array
     *
     * @return array Normalized selection
     * @throws \UniMapper\Exception\PropertyException
     */
    public static function normalizeEntitySelection(\UniMapper\Entity\Reflection $entityReflection, $selection)
    {
        $returnSelection = ['entity' => [], 'associated' => []];
        $map = [];

        $publicProperties = $entityReflection->getPublicProperties();
        foreach ($selection as $index => $name) {

            if (is_array($name)) {
                $partialSelection = $name;
                $name = $index;
            } else {
                $partialSelection = null;
            }

            if (in_array($name, $publicProperties) === true) {
                continue;
            }

            if (!$entityReflection->hasProperty($name)) {
                throw new \UniMapper\Exception\PropertyException(
                    "Property '" . $name . "' is not defined on entity "
                    . $entityReflection->getClassName() . "!"
                );
            }

            $property = $entityReflection->getProperty($name);
            $isEntityOrCollection = in_array(
                $property->getType(),
                [
                    \UniMapper\Entity\Reflection\Property::TYPE_ENTITY,
                    \UniMapper\Entity\Reflection\Property::TYPE_COLLECTION
                ]
                ) === true;

            if ($isEntityOrCollection
                && !$property->hasOption(Reflection\Property\Option\Assoc::KEY)
                && !$property->hasOption(Reflection\Property\Option\Computed::KEY)
                && ($property->hasOption(Reflection\Property\Option\Map::KEY) 
                    && $property->getOption(Reflection\Property\Option\Map::KEY) !== false
                    && $property->getOption(Reflection\Property\Option\Map::KEY)->getFilterIn()
                )
            ) {
                $returnSelection['entity'][] = $name;
            } else if ($property->hasOption(Reflection\Property\Option\Assoc::KEY)
            ) {
                $targetReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                $targetSelection = self::normalizeEntitySelection($targetReflection, $partialSelection);
                if (isset($returnSelection['associated'][$name])) {
                    $returnSelection['associated'][$name]
                        = array_merge($returnSelection['associated'][$name], $targetSelection['entity']);
                } else {
                    $returnSelection['associated'][$name] = $targetSelection['entity'];
                }
            } else if ($partialSelection) {
                $targetReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                $targetSelection =  self::normalizeEntitySelection($targetReflection, $partialSelection);
                if (isset($map[$name])) {
                    $returnSelection['entity'][$map[$name]][1]
                        = array_merge($returnSelection[$map[$name]][1], $targetSelection['entity']);
                } else {
                    $returnSelection['entity'][] = [$name, $targetSelection['entity']];
                }
                $map[$name] = count($returnSelection['entity']) - 1;
            } else if (!array_search($name, $returnSelection)) {
                $returnSelection['entity'][] = $name;
            }
        }

        return $returnSelection;
    }

    public static function filterValues(Reflection $reflection, array $values, array $selection = []) {

        if (!$selection) {
            return $values;
        }

        if (!$values) {
            return $values;
        }

        $result = [];
        foreach ($values as $k => $v) {
            $index = array_search($k, $selection);
            if ($index === false && isset($selection[$k])) {
                $index = $k;
            }
            if ($index !== false) {
                $result[$k] = $v;
            } else if ($v) {
                if (!$reflection->hasProperty($k)) {
                    // was set
                    $result[$k] = $v;
                } else if ($reflection->getProperty($k)->hasOption(\UniMapper\Entity\Reflection\Property\Option\Computed::KEY)) {
                    // computed with value
                    $result[$k] = $v;
                }
            }
        }

        return $result;
    }

    public static function validateInputSelection(Reflection $reflection, array $selection)
    {
        foreach ($selection as $index => $value) {
            if (is_array($value)) {
                $propertyName = $index;
            } else if (is_scalar($value)) {
                $propertyName = $value;
                $value = null;
            } else {
                throw new \UniMapper\Exception\QueryException(
                    "Invalid selection for "
                    . $reflection->getClassName() . "!"
                );
            }

            if (!$reflection->hasProperty($propertyName)) {
                throw new \UniMapper\Exception\QueryException(
                    "Property '" . $propertyName . "' is not defined on entity "
                    . $reflection->getClassName() . "!"
                );
            }

            if (is_array($value)) {
                $property = $reflection->getProperty($propertyName);

                if (
                    in_array($property->getType(), [\UniMapper\Entity\Reflection\Property::TYPE_ENTITY, \UniMapper\Entity\Reflection\Property::TYPE_COLLECTION]) === false
                ) {
                    throw new \UniMapper\Exception\QueryException(
                        "Invalid nested selection, property '" . $propertyName . "' is not entity or collection on entity "
                        . $reflection->getClassName() . "!"
                    );
                }

                $propertyTypeReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                self::validateInputSelection($propertyTypeReflection, $value);
            }
        }
    }

    /**
     * Create's unmapped selection for adapter query
     *
     * @param \UniMapper\Mapper                                    $mapper             Mapper instance
     * @param \UniMapper\Entity\Reflection                         $reflection         Target entity reflection
     * @param array                                                $selection          Query selection
     * @param \UniMapper\Entity\Reflection\Property\Option\Assoc[] $associations       All associations definitions
     * @param \UniMapper\Association[]                             $remoteAssociations Remote associations instances
     *
     * @return array
     */
    public static function createAdapterSelection(\UniMapper\Mapper $mapper, Reflection $reflection, array $selection = [], array $associations = [], array $remoteAssociations = [])
    {
        //- normalize it before unmap
        $selection = self::normalizeEntitySelection($reflection, $selection);

        //- unmap selection for adapter
        $selectionUnmapped = $mapper->unmapSelection(
            $reflection,
            $selection,
            $associations,
            $remoteAssociations
        );

        return $selectionUnmapped;
    }

    /**
     * Recursively appends elements of remaining keys from the second array to the first.
     * @return array
     */
    public static function mergeArrays($arr1, $arr2)
    {
        $res = $arr1 + $arr2;
        foreach (array_intersect_key($arr1, $arr2) as $k => $v) {
            if (is_array($v) && is_array($arr2[$k])) {
                $res[$k] = self::mergeArrays($v, $arr2[$k]);
            }
        }
        return $res;
    }
}
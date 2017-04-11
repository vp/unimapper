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
            }
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
        $returnSelection = [];
        $map = [];
        foreach ($selection as $index => $name) {

            if (is_array($name)) {
                $partialSelection = $name;
                $name = $index;
            } else {
                $partialSelection = null;
            }

            if (!$entityReflection->hasProperty($name)) {
                throw new \UniMapper\Exception\PropertyException(
                    "Property '" . $name . "' is not defined on entity "
                    . $entityReflection->getClassName() . "!"
                );
            }

            $property = $entityReflection->getProperty($name);
            if ($property->hasOption(Reflection\Property\Option\Assoc::KEY)
                || $property->hasOption(Reflection\Property\Option\Computed::KEY)
            ) {
                //- skip assoc and computed
                continue;
            }

            if ($partialSelection) {
                $targetReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                if (isset($map[$name])) {
                    $returnSelection[$map[$name]][1]
                        = array_merge($returnSelection[$map[$name]][1], self::normalizeEntitySelection($targetReflection, $partialSelection));
                } else {
                    $returnSelection[] = [$name, self::normalizeEntitySelection($targetReflection, $partialSelection)];
                }
                $map[$name] = count($returnSelection) - 1;
            } else if (!array_search($name, $returnSelection)) {
                $returnSelection[] = $name;
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
                if (is_array($selection[$index])) {
                    $property = $reflection->getProperty($k);
                    if ($property->getType() === \UniMapper\Entity\Reflection\Property::TYPE_COLLECTION) {
                        $propertyTypeReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                        if ($v) {
                            foreach ($v as $row) {
                                $result[$k][] = is_array($row) 
                                    ? self::filterValues($propertyTypeReflection, $row, $selection[$index])
                                    : $row;
                            }
                        } else {
                            $result[$k] = $v;
                        }
                    } else if ($property->getType() ===  \UniMapper\Entity\Reflection\Property::TYPE_ENTITY) {
                        $propertyTypeReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                        $result[$k] = $v && !$v instanceof \UniMapper\Entity ? self::filterValues($propertyTypeReflection, $v, $selection[$index]) : $v;
                    } else {
                        $result[$k] = $v;
                    }
                } else {
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
     * @param \UniMapper\Entity\Reflection\Property\Option\Assoc[] $associations       Local associations
     * @param \UniMapper\Association[]                             $remoteAssociations Remote associations
     *
     * @return array
     */
    public static function createAdapterSelection(\UniMapper\Mapper $mapper, Reflection $reflection, array $selection = [], array $associations = [], array $remoteAssociations = [])
    {
        //- normalize it before unmap
        $selection = self::normalizeEntitySelection($reflection, $selection);

        //- unmap selection for adapter
        $selection = $mapper->unmapSelection(
            $reflection,
            $selection,
            $associations ? $associations : []
        );

        if ($remoteAssociations) {
            // Add required keys from remote associations (must be after unmapping because ref key is unmapped)
            foreach ($remoteAssociations as $association) {

                if (($association instanceof \UniMapper\Association\ManyToOne || $association instanceof \UniMapper\Association\OneToOne)
                    && !in_array($association->getKey(), $selection, true)
                ) {
                    $selection[$association->getKey()] = $association->getKey();
                }
            }
        }

        return $selection;
    }

    /**
     * Recursively appends elements of remaining keys from the second array to the first.
     * @return array
     */
    public static function mergeArrays($arr1, $arr2)
    {
        $arrays = func_get_args();
        $result = array();

        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                // Renumber integer keys as array_merge_recursive() does. Note that PHP
                // automatically converts array keys that are integer strings (e.g., '1')
                // to integers.
                if (is_integer($key)) {
                    $result[] = $value;
                }
                // Recurse when both values are arrays.
                elseif (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
                    $result[$key] = self::mergeArrays($result[$key], $value);
                }
                // Otherwise, use the latter value, overriding any previous value.
                else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }
}
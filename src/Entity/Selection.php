<?php

namespace UniMapper\Entity;


class Selection
{

    /**
     * Selection entity reflection
     *
     * @var \UniMapper\Entity\Reflection
     */
    protected $entityReflection;

    /**
     * @param mixed $entity Entity object, class or name
     */
    public function __construct($entity)
    {
        $this->entityReflection = \UniMapper\Entity\Reflection::load($entity);
    }

    /**
     * Generates selection for only entity properties
     *
     * @param \UniMapper\Entity\Reflection $entityReflection Entity reflection
     *
     * @return array
     */
    public static function generateEntitySelection(Reflection $entityReflection)
    {
        $selection = [];
        foreach ($entityReflection->getProperties() as $property) {
            // Exclude not mapped
            if (!$property->hasOption(Reflection\Property::OPTION_COMPUTED)
                && !$property->hasOption(Reflection\Property::OPTION_ASSOC)
                && !$property->hasOption(Reflection\Property::OPTION_NOT_MAP)
            ) {
                if ($property->getType() === Reflection\Property::TYPE_COLLECTION || $property->getType() === Reflection\Property::TYPE_ENTITY) {
                    $targetReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                    $selection[$property->getName()] = self::generateEntitySelection($targetReflection);
                } else {
                    $selection[] = $property->getName();
                }
            }
        }
        return $selection;
    }


    /**
     * Normalize selection structure for query
     *
     * - Filter out properties witch are not selectable by query (assoc, computed, ...)
     * - Reformat partial selections ['foo' => ['bar', 'baz']]  to [0 => [0 => 'foo', 1 => ['bar', 'baz']]]
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
            if ($property->hasOption(Reflection\Property::OPTION_ASSOC)
                || $property->hasOption(Reflection\Property::OPTION_COMPUTED)
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
                        foreach ($v as $row) {
                            $result[] = self::filterValues($propertyTypeReflection, $row, $selection);
                        }
                    } else if ($property->getType() ===  \UniMapper\Entity\Reflection\Property::TYPE_ENTITY) {
                        $propertyTypeReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                        $result[$k] = self::filterValues($propertyTypeReflection, $v, $selection[$index]);
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
}
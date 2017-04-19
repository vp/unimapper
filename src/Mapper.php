<?php

namespace UniMapper;

use UniMapper\Entity\Filter;
use UniMapper\Entity\Reflection;

class Mapper
{

    /** @var array|\UniMapper\Adapter\Mapping */
    private $adapterMappings = [];

    public function registerAdapterMapping($name, Adapter\Mapping $mapping)
    {
        if (isset($this->adapterMappings[$name])) {
            throw new Exception\InvalidArgumentException(
                "Mapping on adapater " . $name . " already registered!"
            );
        }

        $this->adapterMappings[$name] = $mapping;
    }

    /**
     * Convert value to defined property format
     *
     * @param Entity\Reflection\Property $property
     * @param mixed                      $value
     *
     * @return mixed
     *
     * @throws Exception\InvalidArgumentException
     */
    public function mapValue(Entity\Reflection\Property $property, $value)
    {
        // Call adapter's mapping if needed
        if (!$property->getEntityReflection()->hasAdapter()) {
            throw new Exception\InvalidArgumentException(
                "Entity " . $property->getEntityReflection()->getClassName()
                . " has no adapter defined!"
            );
        }
        if (isset($this->adapterMappings[$property->getEntityReflection()->getAdapterName()])) {
            $value = $this->adapterMappings[$property->getEntityReflection()->getAdapterName()]
                ->mapValue($property, $value);
        }

        // Call map filter from property option
        if ($property->hasOption(Entity\Reflection\Property::OPTION_MAP_FILTER)) {
            $value = call_user_func($property->getOption(Entity\Reflection\Property::OPTION_MAP_FILTER)[0], $value);
        }

        if ($value === null || $value === "") {
            return null;
        }

        if ($property->isScalarType($property->getType())
            || $property->getType() === Entity\Reflection\Property::TYPE_ARRAY
        ) {
            // Scalar & array

            if ($property->getType() === Reflection\Property::TYPE_BOOLEAN
                && in_array($value, ["false", "true"], true)
            ) {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            if (Validator::isTraversable($value)
                && $property->getType() !== Entity\Reflection\Property::TYPE_ARRAY
            ) {
                throw new Exception\InvalidArgumentException(
                    "Traversable value can not be mapped to scalar!",
                    $value
                );
            }

            if (settype($value, $property->getType())) {
                return $value;
            }
        } elseif ($property->getType() === Entity\Reflection\Property::TYPE_COLLECTION) {
            // Collection

            return $this->mapCollection($property->getTypeOption(), $value);
        } elseif ($property->getType() === Entity\Reflection\Property::TYPE_ENTITY) {
            // Entity

            if ($value instanceof Entity && $property->hasOption(Entity\Reflection\Property::OPTION_MAP_FILTER)) {
                // if value is entity created by filter don't map it again
                return $value;
            }
            return $this->mapEntity($property->getTypeOption(), $value);
        } elseif ($property->getType() === Entity\Reflection\Property::TYPE_DATETIME
            || $property->getType() === Entity\Reflection\Property::TYPE_DATE
        ) {
            // DateTime & Date

            if ($value instanceof \DateTime) {
                return $value;
            } elseif (is_array($value) && isset($value["date"])) {
                $date = $value["date"];
            } elseif (is_object($value) && isset($value->date)) {
                $date = $value->date;
            } else {
                $date = $value;
            }

            if (isset($date)) {

                try {
                    return new \DateTime($date);
                } catch (\Exception $e) {
                    throw new Exception\InvalidArgumentException(
                        "Can not map value to DateTime automatically! "
                        . $e->getMessage(),
                        $value
                    );
                }
            }
        }

        // Unexpected value type
        throw new Exception\InvalidArgumentException(
            "Value can not be mapped automatically!",
            $value
        );
    }

    public function mapCollection($name, $data)
    {
        if (!Validator::isTraversable($data)) {
            throw new Exception\InvalidArgumentException(
                "Input data must be traversable!",
                $data
            );
        }

        $collection = new Entity\Collection($name);
        foreach ($data as $value) {
            $collection[] = $this->mapEntity($name, $value);
        }
        return $collection;
    }

    public function mapEntity($name, $data)
    {
        if (!Validator::isTraversable($data)) {
            throw new Exception\InvalidArgumentException(
                "Input data must be traversable!",
                $data
            );
        }

        $entityReflection = Entity\Reflection::load($name);

        $values = [];
        foreach ($data as $index => $value) {

            $propertyName = $index;

            // Map property name if needed
            foreach ($entityReflection->getProperties() as $propertyReflection) {

                if ($propertyReflection->getName(true) === $index) {

                    $propertyName = $propertyReflection->getName();
                    break;
                }
            }

            // Skip undefined properties
            if (!$entityReflection->hasProperty($propertyName)) {
                continue;
            }

            // Map value
            $values[$propertyName] = $this->mapValue(
                $entityReflection->getProperty($propertyName),
                $value
            );
        }

        return $entityReflection->createEntity($values);
    }

    /**
     * Convert entity to simple array
     *
     *  @param Entity $entity
     *
     *  @return array
     */
    public function unmapEntity(Entity $entity)
    {
        $output = [];
        foreach ($entity->getData() as $propertyName => $value) {

            $property = Entity\Reflection::load($entity)->getProperty($propertyName);

            // Skip associations & readonly
            if ($property->hasOption(Entity\Reflection\Property::OPTION_ASSOC)
                || !$property->isWritable()
            ) {
                continue;
            }

            $output[$property->getName(true)] = $this->unmapValue(
                $property,
                $value
            );
        }
        return $output;
    }

    public function unmapValue(Entity\Reflection\Property $property, $value)
    {
        // Call map filter from property option
        if ($property->hasOption(Entity\Reflection\Property::OPTION_MAP_FILTER)) {
            $value = call_user_func($property->getOption(Entity\Reflection\Property::OPTION_MAP_FILTER)[1], $value);
        }

        if ($value instanceof Entity\Collection) {
            return $this->unmapCollection($value);
        } elseif ($value instanceof Entity) {
            return $this->unmapEntity($value);
        }

        // Call adapter's mapping if needed
        if (!$property->getEntityReflection()->hasAdapter()) {
            throw new Exception\InvalidArgumentException(
                "Entity " . $property->getEntityReflection()->getClassName()
                . " has no adapter defined!"
            );
        }

        if (isset($this->adapterMappings[$property->getEntityReflection()->getAdapterName()])) {
            return $this->adapterMappings[$property->getEntityReflection()->getAdapterName()]
                ->unmapValue($property, $value);
        }

        return $value;
    }

    /**
     * Convert entity to simple array
     *
     *  @param Entity\Collection $collection
     *
     *  @return array
     */
    public function unmapCollection(Entity\Collection $collection)
    {
        $data = [];
        foreach ($collection as $index => $entity) {
            $data[$index] = $this->unmapEntity($entity);
        }
        return $data;
    }

    /**
     * @param Reflection $reflection
     * @param array      $filter
     *
     * @return array
     */
    public function unmapFilter(Reflection $reflection, array $filter)
    {
        $result = [];

        if (Filter::isGroup($filter)) {

            foreach ($filter as $modifier => $item) {
                $result[$modifier] = $this->unmapFilter($reflection, $item);
            }
        } else {

            foreach ($filter as $name => $item) {
                $assocDelimiterPos = strpos($name, '.');
                if ($assocDelimiterPos !== false) {
                    if (isset($this->adapterMappings[$reflection->getAdapterName()])) {
                        $assocPropertyName = substr($name, 0, $assocDelimiterPos);
                        $assocPropertyTargetName = substr($name, $assocDelimiterPos + 1);
                        $assocProperty = $reflection->getProperty($assocPropertyName);
                        $assocEntityReflection = \UniMapper\Entity\Reflection::load($assocProperty->getTypeOption());
                        $property = $assocEntityReflection->getProperty($assocPropertyTargetName);
                        $unmappedName = $this->adapterMappings[$reflection->getAdapterName()]
                            ->unmapFilterJoinProperty($reflection, $name);
                    } else {
                        throw new Exception\InvalidArgumentException(
                            "Unknown filter property " . $name . "!"
                        );
                    }
                } else {
                    $property = $reflection->getProperty($name);
                    $unmappedName = $property->getName(true);
                }
                $this->unmapFilterProperty($property, $unmappedName, $item, $result);
            }
        }

        return $result;
    }

    /**
     * @param \UniMapper\Entity\Reflection\Property $property
     * @param string                                $unmappedName
     * @param array                                 $item
     * @param array                                 $result
     *
     * @throws \UniMapper\Exception\InvalidArgumentException
     */
    protected function unmapFilterProperty(Reflection\Property $property, $unmappedName, array $item, &$result)
    {
        foreach ($item as $modifier => $value) {

            if ($property->getType() !== Reflection\Property::TYPE_ARRAY
                && in_array($modifier, [Filter::EQUAL, Filter::NOT], true)
                && is_array($value)
            ) {
                // IN/NOT IN cases

                $result[$unmappedName][$modifier] = [];
                foreach ($value as $key => $valueVal) {
                    $result[$unmappedName][$modifier][$key] = $this->unmapValue(
                        $property,
                        $valueVal
                    );
                }
            } else {

                $result[$unmappedName][$modifier] = $this->unmapValue(
                    $property,
                    $value
                );
            }
        }
    }

    /**
     * @param \UniMapper\Entity\Reflection $reflection
     * @param array                        $filter
     *
     * @return mixed
     */
    public function unmapFilterJoins(Reflection $reflection, array $filter)
    {
        if (isset($this->adapterMappings[$reflection->getAdapterName()])) {
            return $this->adapterMappings[$reflection->getAdapterName()]
                ->unmapFilterJoins($reflection, $filter);
        }
        
        return [];
    }

    /**
     * Unmap entity property selection
     *
     * @param \UniMapper\Entity\Reflection $reflection   Entity reflection
     * @param array                        $selection    Normalized selection
     * @param array                        $associations Associations definitions
     *
     * @return array
     */
    public function unmapSelection(Reflection $reflection, array $selection, array $associations = []){
        $unampped = $this->traverseSelectionForUnmap($reflection, $selection['entity']);
        $this->unmapSelectionAssociations($selection, $unampped, $associations);

        if (isset($this->adapterMappings[$reflection->getAdapterName()])) {
            $unampped = $this->adapterMappings[$reflection->getAdapterName()]
                ->unmapSelection($reflection, $unampped, $associations, $this);
        }

        return $unampped;
    }

    /**
     * Traverse selection and unmap it
     *
     * @param \UniMapper\Entity\Reflection $reflection Entity reflection
     * @param array                        $selection  Normalized entity selection
     *
     * @return array
     */
    protected function traverseSelectionForUnmap(Reflection $reflection, array $selection){
        $unampped = [];
        foreach ($selection as $name) {
            if (is_array($name)) {
                $property = $reflection->getProperty($name[0]);
                if (!$property->hasOption(Reflection\Property::OPTION_NOT_MAP)) {
                    $targetReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                    if (isset($unampped[$property->getName()])) {
                        $unampped[$property->getName()][$property->getName(true)] = array_merge($unampped[$property->getName()], $this->traverseSelectionForUnmap($targetReflection, $name[1]));
                    } else {
                        $unampped[$property->getName()] = [$property->getName(true) => $this->traverseSelectionForUnmap($targetReflection, $name[1])];
                    }
                }
            } else {
                $property = $reflection->getProperty($name);
                if (!$property->hasOption(Reflection\Property::OPTION_NOT_MAP)) {
                    $unampped[$property->getName()] = $property->getName(true);
                }
            }
        }

        return $unampped;
    }

    /**
     * Unmap associations selection
     *
     * - set's association target selection unmapped
     * - add required fields to target selection (referenced key, target primary)
     * - add required fields (if any) to unmapped entity selection (referencing key)
     *
     * @param array $selection         Normalized selection
     * @param array $selectionUnmapped Unmapped entity selection
     * @param array $associations      Associations definition
     */
    protected function unmapSelectionAssociations(array $selection, array &$selectionUnmapped, array $associations = [])
    {
        if ($associations) {
            /** @var \UniMapper\Association $association */
            foreach (array_merge($associations['local'], $associations['remote']) as $association) {

                // Add required keys from remote associations (must be after unmapping because ref key is unmapped)
                if ($association->isRemote()
                    && ($association instanceof \UniMapper\Association\ManyToOne || $association instanceof \UniMapper\Association\OneToOne)
                    && !in_array($association->getReferencingKey(), $selectionUnmapped, true)
                ) {
                    $selectionUnmapped[$association->getReferencingKey()] = $association->getReferencingKey();
                }

                if (isset($selection['associated'][$association->getPropertyName()])) {

                    $associationSelectionUnmapped = $this->unmapSelection(
                        $association->getTargetReflection(),
                        ['entity' => $selection['associated'][$association->getPropertyName()]],
                        []
                    );

                    // Remote required keys on target selection
                    if ($association->isRemote()) {
                        $key = null;
                        if ($association instanceof \UniMapper\Association\OneToMany) {
                            $key = $association->getReferencedKey();
                        } else if ($association instanceof \UniMapper\Association\ManyToMany) {
                            $key = $association->getTargetPrimaryKey();
                        } else if ($association instanceof \UniMapper\Association\ManyToOne) {
                            $key = $association->getTargetPrimaryKey();
                        } else if ($association instanceof \UniMapper\Association\OneToOne) {
                            $key = $association->getTargetPrimaryKey();
                        }

                        if ($key && in_array($key, $associationSelectionUnmapped) === false) {
                            $associationSelectionUnmapped[] = $key;
                        }
                    }

                    // done
                    $association->setTargetSelectionUnampped($associationSelectionUnmapped);
                }
            }
        }
    }
}
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
        if (!$property->getReflection()->hasAdapter()) {
            throw new Exception\InvalidArgumentException(
                "Entity " . $property->getReflection()->getClassName()
                . " has no adapter defined!"
            );
        }
        if (isset($this->adapterMappings[$property->getReflection()->getAdapterName()])) {
            $value = $this->adapterMappings[$property->getReflection()->getAdapterName()]
                ->mapValue($property, $value);
        }

        if ($property->hasOption(Reflection\Property\Option\Map::KEY)) {

            $mapOption = $property->getOption(Reflection\Property\Option\Map::KEY);
            if ($mapOption) {
                // Call map filter from property option
                $filterIn = $mapOption->getFilterIn();
                if ($filterIn) {
                    $value = call_user_func($filterIn, $value);
                }
            }
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

            if ($value instanceof Entity
                && $property->hasOption(Entity\Reflection\Property\Option\Map::KEY)
                && $property->getOption(Entity\Reflection\Property\Option\Map::KEY) !== false
                && $property->getOption(Entity\Reflection\Property\Option\Map::KEY)->getFilterIn()
            ) {
                // if value is entity created by filter don't map it again
                return $value;
            }
            return $this->mapEntity($property->getTypeOption(), $value);
        } elseif ($property->getType() === Entity\Reflection\Property::TYPE_DATETIME
            || $property->getType() === Entity\Reflection\Property::TYPE_DATE
        ) {
            // DateTime & Date

            if ($value instanceof \DateTimeInterface) {
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

        $reflection = Entity\Reflection::load($name);

        $values = [];
        foreach ($data as $name => $value) {

            // Map property name if needed
            foreach ($reflection->getProperties() as $property) {

                if ($property->hasOption(Reflection\Property\Option\Map::KEY) && !$property->getOption(Reflection\Property\Option\Map::KEY)) {

                    if ($property->getName() === $name) {
                        continue 2; // Skip disabled
                    }
                    continue;
                }

                if ($property->getUnmapped() === $name) {

                    $name = $property->getName();
                    break;
                }
            }

            // Skip undefined properties
            if (!$reflection->hasProperty($name)) {
                continue;
            }

            // Map value
            $values[$name] = $this->mapValue(
                $reflection->getProperty($name),
                $value
            );
        }

        return $reflection->createEntity($values);
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
        foreach ($entity->getData() as $name => $value) {

            $property = $entity::getReflection()->getProperty($name);

            // Skip associations & readonly & disabled mapping
            if ($property->hasOption(Reflection\Property\Option\Assoc::KEY)
                || !$property->isWritable()
                || ($property->hasOption(Reflection\Property\Option\Map::KEY)
                    && !$property->getOption(Reflection\Property\Option\Map::KEY))
            ) {
                continue;
            }

            $output[$property->getUnmapped()] = $this->unmapValue($property, $value);
        }
        return $output;
    }

    public function unmapValue(Entity\Reflection\Property $property, $value)
    {
        if ($property->hasOption(Reflection\Property\Option\Map::KEY)) {

            $mapOption = $property->getOption(Reflection\Property\Option\Map::KEY);
            if ($mapOption) {
                // Call map filter from property option
                $filterOut = $mapOption->getFilterOut();
                if ($filterOut) {
                    $value = call_user_func($filterOut, $value);
                }
            }
        }

        if ($value instanceof Entity\Collection) {
            return $this->unmapCollection($value);
        } elseif ($value instanceof Entity) {
            return $this->unmapEntity($value);
        }

        // Call adapter's mapping if needed
        if (!$property->getReflection()->hasAdapter()) {
            throw new Exception\InvalidArgumentException(
                "Entity " . $property->getReflection()->getClassName()
                . " has no adapter defined!"
            );
        }

        if (isset($this->adapterMappings[$property->getReflection()->getAdapterName()])) {
            return $this->adapterMappings[$property->getReflection()->getAdapterName()]
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
                    if (!isset($this->adapterMappings[$reflection->getAdapterName()])) {
                        throw new Exception\InvalidArgumentException(
                            "Adapter not support nested filters " . $name . "!"
                        );
                    }
                } else if ($name === Filter::_NATIVE) {
                    $result[$name] = $item;
                } else {
                    $property = $reflection->getProperty($name);
                    $unmappedName = $property->getUnmapped();
                    $this->unmapFilterProperty($property, $unmappedName, $item, $result);
                }
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
    public function unmapFilterProperty(Reflection\Property $property, $unmappedName, array $item, &$result)
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
                ->unmapFilterJoins($this, $reflection, $filter);
        }

        return [];
    }

    /**
     * Unmap entity property selection
     *
     * @param \UniMapper\Entity\Reflection                         $reflection         Entity reflection
     * @param array                                                $selection          Normalized selection
     * @param \UniMapper\Entity\Reflection\Property\Option\Assoc[] $associations       Associations definitions
     * @param \UniMapper\Association[]                             $remoteAssociations Remote associations instances
     *
     * @return array
     */
    public function unmapSelection(Reflection $reflection, array $selection, array $associations = [], array $remoteAssociations = []){
        $unampped = $this->traverseSelectionForUnmap($reflection, $selection['entity']);
        $this->unmapSelectionAssociations($selection, $unampped, $associations, $remoteAssociations);

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
        $unmapped = [];
        foreach ($selection as $name) {
            $property = $reflection->getProperty(is_array($name) ? $name[0] : $name);
            $dontMap = ($property->hasOption(Reflection\Property\Option\Map::KEY)
                && $property->getOption(Reflection\Property\Option\Map::KEY) === false);
            if (is_array($name)) {
                if (!$dontMap && !$property->hasOption(Reflection\Property\Option\Computed::KEY)) {
                    $targetReflection = \UniMapper\Entity\Reflection::load($property->getTypeOption());
                    if (isset($unmapped[$property->getName()])) {
                        $unmapped[$property->getName()][$property->getUnmapped()] = array_merge($unmapped[$property->getName()], $this->traverseSelectionForUnmap($targetReflection, $name[1]));
                    } else {
                        $unmapped[$property->getName()] = [$property->getUnmapped() => $this->traverseSelectionForUnmap($targetReflection, $name[1])];
                    }
                }
            } else {
                if (!$dontMap && !$property->hasOption(Reflection\Property\Option\Computed::KEY)) {
                    $unmapped[$property->getName()] = $property->getUnmapped();
                }
            }
        }

        return $unmapped;
    }

    /**
     * Unmap associations selection
     *
     * - set's association target selection unmapped
     * - add required fields to target selection (referenced key, target primary)
     * - add required fields (if any) to unmapped entity selection (referencing key)
     *
     * @param array                                                $selection          Normalized selection
     * @param array                                                $selectionUnmapped  Unmapped entity selection
     * @param \UniMapper\Entity\Reflection\Property\Option\Assoc[] $associations       All associations definitions
     * @param \UniMapper\Association[]                             $remoteAssociations Remote associations instances
     */
    protected function unmapSelectionAssociations(array $selection, array &$selectionUnmapped, array $associations = [], array $remoteAssociations = [])
    {
        if ($associations) {
            /** @var \UniMapper\Association $association */
            foreach ($associations as $propertyName => $option) {

                // Add required keys from remote associations (must be after unmapping because ref key is unmapped)
                if ($option->isRemote()) {
                    $association = $remoteAssociations[$propertyName];
                    if (($association instanceof \UniMapper\Association\ManyToOne || $association instanceof \UniMapper\Association\OneToOne)
                        && !in_array($association->getKey(), $selectionUnmapped, true)
                    ) {
                        $selectionUnmapped[$association->getKey()] = $association->getKey();
                    }
                }

                if (isset($selection['associated'][$propertyName])) {
                    // Leave only adapter association selection
                    if (!$option->isRemote()) {
                        $selectionUnmapped[$propertyName] = $this->unmapSelection(
                            $option->getTargetReflection(),
                            ['entity' => $selection['associated'][$propertyName]],
                            []
                        );
                    } else {
                        unset($selectionUnmapped[$propertyName]);
                    }
                }
            }
        }
    }
}
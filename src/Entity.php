<?php

namespace UniMapper;

use UniMapper\Entity\Collection;
use UniMapper\Entity\Reflection;
use UniMapper\Entity\Reflection\Property\Option\Computed;

abstract class Entity implements \JsonSerializable, \Serializable, \IteratorAggregate
{
    public static $_MAGIC_COLLECTION_CREATION = false;

    public static $dateFormat = "Y-m-d";

    const CHANGE_ATTACH = 1;
    const CHANGE_DETACH = 2;
    const CHANGE_ADD = 3;
    const CHANGE_REMOVE = 4;

    /** @var array $data Stored variables */
    private $data = [];

    /** @var \UniMapper\Validator $validator */
    private $validator;

    /** @var array $changes Properties with changes */
    private $changes = [];

    /** @var integer $change */
    private $changeType;

    private $selection = [];

    /**
     * @param array $selection
     */
    public function setSelection($selection)
    {
        $this->selection = $selection;

        if ($selection) {
            foreach ($selection as $k => $v) {
                if (is_array($v)
                    && $this->getReflection()->hasProperty($k)
                    && isset($this->{$k})
                    && in_array($this->getReflection()->getProperty($k)->getType(), [\UniMapper\Entity\Reflection\Property::TYPE_ENTITY, \UniMapper\Entity\Reflection\Property::TYPE_COLLECTION])
                ) {
                   $this->{$k}->setSelection($v);
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getSelection($property = null)
    {
        if ($property) {
            if (isset($this->selection[$property])) {
                return $this->selection[$property];
            } else {
                return [];
            }
        }
        return $this->selection;
    }

    /**
     * @param mixed $values
     */
    public function __construct($values = null)
    {
        if ($values) {
            $this->_setProperties($values, true, true, true, true);
        }
    }

    private function _setProperties(
        $values,
        $autoConvert = true,
        $skipUndefined = false,
        $setReadonly = false,
        $skipUnwritable = false
    ) {
        if (!Validator::isTraversable($values)) {
            throw new Exception\InvalidArgumentException(
                "Values must be traversable data!",
                $values
            );
        }

        $reflection = $this::getReflection();

        foreach ($values as $name => $value) {

            if (\UniMapper\Entity\Iterator::$ITERATE_OPTIONS[\UniMapper\Entity\Iterator::ITERATE_PUBLIC]) {
                // Public
                if (in_array($name, $reflection->getPublicProperties())) {
                    $this->{$name} = $value;
                    continue;
                }
            }
            
            // Undefined
            if (!$reflection->hasProperty($name)) {

                if ($skipUndefined) {
                    continue;
                }

                throw new Exception\InvalidArgumentException(
                    "Undefined property '" . $name . "'!"
                );
            }

            $property = $reflection->getProperty($name);

            // Computed
            if ($property->hasOption(Computed::KEY)) {

                if ($skipUnwritable) {
                    continue;
                }

                throw new Exception\InvalidArgumentException(
                    "Computed property is read-only!"
                );
            }

            // Readonly
            if (!$property->isWritable() && !$setReadonly) {

                if ($skipUnwritable) {
                    continue;
                }

                throw new Exception\InvalidArgumentException(
                    "Property '" . $name . "' is read-only!"
                );
            }

            // Validate type
            try {
                $property->validateValueType($value);
            } catch (Exception\InvalidArgumentException $e) {

                if ($autoConvert) {
                    $value = $property->convertValue($value);
                } else {
                    throw $e;
                }
            }

            // Set value
            $this->data[$name] = $value;
        }
    }

    private function _validateChangeType($primaryRequired = false)
    {
        $reflection = $this::getReflection();

        if (!$reflection->hasPrimary()) {
            throw new Exception\InvalidArgumentException(
                "Only entity with primary can define changes!"
            );
        }

        $primaryName = $reflection->getPrimaryProperty()->getName();
        if ($primaryRequired && empty($this->{$primaryName})) {
            throw new Exception\InvalidArgumentException(
                "Primary value can not be empty!"
            );
        }
    }

    /**
     * Create new entity collection
     *
     * @param mixed $values
     *
     * @return Collection
     */
    public static function createCollection($values = null)
    {
        return new Collection(get_called_class(), $values);
    }

    public function attach()
    {
        $this->_validateChangeType(true);
        $this->changeType = self::CHANGE_ATTACH;
    }

    public function detach()
    {
        $this->_validateChangeType();
        $this->changeType = self::CHANGE_DETACH;
    }

    public function add()
    {
        $this->_validateChangeType();
        $this->changeType = self::CHANGE_ADD;
    }

    public function remove()
    {
        $this->_validateChangeType(true);
        $this->changeType = self::CHANGE_REMOVE;
    }

    /**
     * Serialize entity
     *
     * Only data and selection are serialized
     *
     * @return string
     */
    public function serialize()
    {
        $values = $this::getReflection()
            ->createIterator($this, ['computed' => false, 'defined' => false])
            ->toArray();

        // serialize merged with data
        return serialize(
            [
                $values,
                $this->getSelection()
            ]
        );
    }

    /**
     * Unserialize Entity
     *
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        list($values, $selection) = unserialize($serialized);
        foreach ($values as $k => $v) {
            $this->{$k} = $v;
        }
        $this->setSelection($selection);
    }

    /**
     * Import and try to convert values automatically if possible, skip readonly
     * and undefined.
     *
     * @param mixed $values Traversable structure (array/object)
     */
    public function import($values)
    {
        $this->_setProperties($values, true, true, false, true);
    }

    /**
     * Manage entity and collection changes on target property
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return Entity|Entity\Collection
     *
     * @throws Exception\InvalidArgumentException
     */
    public function __call($name, $arguments)
    {
        $reflection = $this::getReflection();

        if (!$reflection->hasProperty($name)) {
            throw new Exception\InvalidArgumentException(
                "Undefined property '" . $name . "'!"
            );
        }

        $propertyReflection = $reflection->getProperty($name);

        if ($propertyReflection->getType() !== Entity\Reflection\Property::TYPE_ENTITY
            && $propertyReflection->getType() !== Entity\Reflection\Property::TYPE_COLLECTION
        ) {
            throw new Exception\InvalidArgumentException(
                "Only properties with type entity or collection can call changes!"
            );
        }

        if (isset($arguments[0])) {

            if ($arguments[0] === false) {
                unset($this->changes[$name]);
            } else {

                if (!$arguments[0] instanceof Entity\Collection
                    && $propertyReflection->getType() === Entity\Reflection\Property::TYPE_COLLECTION
                ) {
                    throw new Exception\InvalidArgumentException(
                        "You must pass instance of entity collection!",
                        $arguments[0]
                    );
                }

                if (!$arguments[0] instanceof Entity
                    && $propertyReflection->getType() === Entity\Reflection\Property::TYPE_ENTITY
                ) {
                    throw new Exception\InvalidArgumentException(
                        "You must pass instance of entity!",
                        $arguments[0]
                    );
                }

                $this->changes[$name] = $arguments[0];
            }
        }

        if (!isset($this->changes[$name])) {

            if ($propertyReflection->getType() === Entity\Reflection\Property::TYPE_COLLECTION) {
                $this->changes[$name] = new Entity\Collection($propertyReflection->getTypeOption());
            } else {
                $this->changes[$name] = Entity\Reflection::load($propertyReflection->getTypeOption())->createEntity();
            }
        }

        return $this->changes[$name];
    }

    /**
     * Get property value
     *
     * @param string $name Property name
     *
     * @return mixed
     *
     * @throws Exception\InvalidArgumentException
     */
    public function __get($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        $reflection = $this::getReflection();
        if (!$reflection->hasProperty($name)) {
            throw new Exception\InvalidArgumentException(
                "Undefined property '" . $name . "'!"
            );
        }

        $property = $reflection->getProperty($name);

        // computed property
        if ($property->hasOption(Computed::KEY)) {

            $computedValue = $this->{$property->getOption(Computed::KEY)->getName()}();
            if ($computedValue === null) {
                return null;
            }
            $property->validateValueType($computedValue);
            return $computedValue;
        }

        // empty collection
        if (self::$_MAGIC_COLLECTION_CREATION && $property->getType() === Entity\Reflection\Property::TYPE_COLLECTION) {
            //trigger_error(__METHOD__ . ' Calling on empty collection is deprecated.', E_USER_DEPRECATED);
            return $this->data[$name] = new Entity\Collection($property->getTypeOption());
        }
        
        return null;
    }

    /**
     * Set property value
     *
     * @param string $name
     * @param mixed  $value
     */
    public function __set($name, $value)
    {
        // automatic corversion of array to collection
        $reflection = self::getReflection();
        if ($reflection->hasProperty($name)
            && $reflection->getProperty($name)->getType() === Entity\Reflection\Property::TYPE_COLLECTION
            && is_array($value)
        ) {
            $value = new Entity\Collection($reflection->getProperty($name)->getTypeOption(), $value);
        }

        $this->_setProperties([$name => $value], false);
    }

    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    public function __unset($name)
    {
        $reflection = $this::getReflection();
        if ($reflection->hasProperty($name)
            && !$reflection->getProperty($name)->isWritable()
        ) {
            throw new Exception\InvalidArgumentException(
                "Property '" . $name . "' is read-only!"
            );
        }
        unset($this->data[$name]);
    }

    public function getChangeType()
    {
        return $this->changeType;
    }

    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * Get entity reflection
     *
     * @return Reflection
     */
    public static function getReflection()
    {
        return Entity\Reflection::load(get_called_class());
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get entity validator
     *
     * @return \UniMapper\Validator
     */
    public function getValidator()
    {
        if (!$this->validator) {
            $this->validator = new Validator($this);
        }
        return $this->validator->onEntity();
    }

    /**
     * Query on entity
     *
     * @return QueryBuilder
     */
    public static function query()
    {
        return new QueryBuilder(get_called_class());
    }

    /**
     * Get entity values as array
     *
     * @param boolean $nesting Convert nested entities and collections too
     *
     * @return array
     */
    public function toArray($nesting = false)
    {
        $self = $this;
        return $this->getIterator()
            ->setMapCallback(function ($value, $propertyName) use ($nesting, $self) {
                if (($value instanceof Entity\Collection || $value instanceof Entity)
                    && $nesting
                ) {
                    $value->setSelection($self->getSelection($propertyName));
                    return $value->toArray($nesting);
                } else {
                    return $value;
                }
            })
            ->toArray();
    }

    /**
     * Gets data which should be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $reflection = $this::getReflection();
        return $this->getIterator()
            ->setMapCallback(function ($value, $propertyName) use ($reflection) {
                $property = $reflection->hasProperty($propertyName)
                    ? $reflection->getProperty($propertyName)
                    : false;

                if ($value instanceof Entity\Collection || $value instanceof Entity) {
                    $value->setSelection($this->getSelection($propertyName));
                    return $value->jsonSerialize();
                } elseif ($value instanceof \DateTime
                    && $property && $property->getType() === Entity\Reflection\Property::TYPE_DATE
                ) {
                    $a = (array)$value;
                    $a["date"] = $value->format(self::$dateFormat);
                    return $a;
                } else {
                    return $value;
                }
            })
            ->toArray();
    }

    /**
     * Entity iterator
     *
     * @return \UniMapper\Entity\Iterator
     */
    public function getIterator()
    {
        return $this->getReflection()
            ->createIterator($this);
    }
}
<?php

namespace UniMapper\Entity\Reflection\Property\Option;

use UniMapper\Entity\Reflection;
use UniMapper\Exception\OptionException;

class Computed implements Reflection\Property\IOption
{

    const KEY = "computed";

    private static $computedFactories;
    private static $computedInstances;

    /** @var string */
    private $name;

    /** @var  string */
    private $factoryName;

    /** @var  string[] */
    private $dependsOn = [];

    public static function registerFactory($entityName, $factory) {
        self::$computedFactories[$entityName] = $factory;
    }


    public function __construct(Reflection\Property $property, $factory = null, $method = null, array $parameters = [])
    {

        $this->name = $method ?: "compute" . ucfirst($property->getName());
        $this->factoryName = $factory ?: $this->factoryName;
        $this->dependsOn = isset($parameters['computed-depends']) ? explode(',', $parameters['computed-depends']) : [];

        if (!method_exists($property->getReflection()->getClassName(), $this->name) && !isset(self::$computedFactories[$this->factoryName])) {

            throw new OptionException(
                "Computed method " . $this->name . " not found in "
                . $property->getReflection()->getClassName() . "!"
            );
        }
    }

    /**
     * Get method name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function hasDependencies(\UniMapper\Entity $entity)
    {
        if ($this->dependsOn) {
            foreach ($this->dependsOn as $propertyName) {
                if (!isset($entity->{$propertyName})) {
                    return false;
                }
            }
        }
        return true;
    }

    public function compute(\UniMapper\Entity $entity) {
        if (isset(self::$computedFactories[$this->factoryName])) {
            $factory = self::$computedFactories[$this->factoryName];
            $instance = isset(self::$computedInstances[$this->factoryName])
                ? self::$computedInstances[$this->factoryName]
                : null;

            if (!$instance) {
                $instance = is_callable($factory)
                    ? self::$computedInstances[$this->factoryName] = call_user_func($factory)
                    : $factory;
            }

            if (!method_exists($instance, $this->getName())) {
                throw new OptionException(
                    "Computed method " . $this->name . " not found in "
                    . get_class($instance). "!"
                );
            }

            return $instance->{$this->getName()}($entity);
        }
    }

    public static function create(
        Reflection\Property $property,
        $value = '',
        array $parameters = []
    ) {
        $value = $value ? explode("|", $value) : [];
        return new self(
            $property,
            isset($value[0]) ? $value[0] : null,
            isset($value[1]) ? $value[1] : null,
            $parameters
        );
    }

    public static function afterCreate(Reflection\Property $property, $option)
    {
        if ($property->hasOption(Map::KEY)
            || $property->hasOption(Enum::KEY)
            || $property->hasOption(Primary::KEY)
        ) {
            throw new OptionException(
                "Computed option can not be combined with mapping, enumeration "
                . "or primary options!"
            );
        }
    }

}
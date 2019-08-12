<?php

namespace UniMapper\Entity;

use UniMapper\Entity;
use UniMapper\Exception;
use UniMapper\Convention;

class Reflection
{

    /** @var string */
    private $adapterName;

    /** @var string */
    private $adapterResource;

    /** @var array */
    private $properties = [];

    /** @var string */
    private $className;

    /** @var string */
    private $fileName;

    /** @var array $publicProperties List of public property names */
    private $publicProperties = [];

    /** @var array $computedProperties List of computed property names */
    private $computedProperties = [];

    /** @var array $registered Registered reflections */
    private static $registered = [];

    /** @var string Entity values iterator */
    public static $entityIterator = '\UniMapper\Entity\Iterator';
    
    public static $defaultEntityIterationOptions = [];

    /**
     * @param string $class Entity class name
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\ReflectionException
     */
    public function __construct($class)
    {
        $this->className = (string) $class;

        if (!is_subclass_of($this->className, "UniMapper\Entity")) {
            throw new Exception\InvalidArgumentException(
                "Class must be subclass of UniMapper\Entity but "
                . $this->className . " given!",
                $this->className
            );
        }

        // Register reflection
        self::load($this);

        $reflectionClass = new \ReflectionClass($this->className);

        $this->fileName = $reflectionClass->getFileName();

        $docComment = $reflectionClass->getDocComment();
        $this->_parseAdapter($docComment);
        $this->_parseProperties($docComment, $reflectionClass);
        $this->_parsePublicProperties($reflectionClass);
    }

    private function _parsePublicProperties(\ReflectionClass $reflectionClass)
    {
        foreach ($reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC)
                 as $property
        ) {
            if ($property->isStatic()) {
                continue;
            }
            $this->publicProperties[] =  $property->getName();
        }
    }

    private function _parseAdapter($docComment)
    {
        try {

            $adapter = Reflection\Annotation::parseAdapter($docComment);
        } catch (Exception\AnnotationException $e) {
            throw new Exception\ReflectionException(
                $e->getMessage(),
                $this->className,
                $e->getDefinition()
            );
        }

        if ($adapter) {
            list($this->adapterName, $this->adapterResource) = $adapter;
        }

        if (empty($this->adapterResource)) {

            $this->adapterResource = $this->getName();
            if (Convention::hasAdapterConvention($this->adapterName)) {

                $this->adapterResource = Convention::getAdapterConvention(
                    $this->adapterName
                )->mapResource($this->adapterResource);
            }
        }
    }

    /**
     * Load and register reflection
     *
     * @param string|Entity|Collection|Reflection $arg
     *
     * @throws Exception\InvalidArgumentException
     *
     * @return Reflection
     */
    public static function load($arg)
    {
        if (is_object($arg) && $arg instanceof Entity) {
            $class = get_class($arg);
        } elseif (is_object($arg) && $arg instanceof Collection) {
            $class = $arg->getEntityClass();
        } elseif (is_object($arg) && $arg instanceof Reflection) {

            $class = $arg->getClassName();
            if (!isset(self::$registered[$class])) {
                return self::$registered[$class] = $arg;
            }
            return $arg;
        } elseif (is_string($arg)) {
            $class = $arg;
        } else {
            throw new Exception\InvalidArgumentException(
                "Entity identifier must be object, collection, class or name!",
                $arg
            );
        }

        if (!is_subclass_of($class, "UniMapper\Entity")) {
            $class = Convention::nameToClass($arg, Convention::ENTITY_MASK);
        }

        if (!class_exists($class)) {
            throw new Exception\InvalidArgumentException(
                "Entity class " . $class . " not found!"
            );
        }

        if (isset(self::$registered[$class])) {
            return self::$registered[$class];
        }

        return self::$registered[$class] = new Reflection($class);
    }

    public function createEntity($values = null)
    {
        $entityClass = $this->className;
        return new $entityClass($values);
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getFileName()
    {
        return $this->fileName;
    }

    public function getName()
    {
        return Convention::classToName($this->className, Convention::ENTITY_MASK);
    }

    /**
     * Parse properties from annotations
     *
     * @param string           $docComment
     * @param \ReflectionClass $reflectionClass
     *
     * @throws Exception\ReflectionException
     */
    private function _parseProperties($docComment, \ReflectionClass $reflectionClass)
    {
        $properties = [];
        foreach (Reflection\Annotation::parseProperties($docComment) as $definition) {

            try {
                $property = new Reflection\Property(
                    $definition[2],
                    $definition[3],
                    $this,
                    !$definition[1],
                    $definition[4]
                );
            } catch (Exception\PropertyException $e) {
                throw new Exception\ReflectionException(
                    $e->getMessage(),
                    $this->className,
                    $definition[0]
                );
            }

            // Prevent duplications
            if (isset($properties[$property->getName()])) {
                throw new Exception\ReflectionException(
                    "Duplicate property with name '" . $property->getName() . "'!",
                    $this->className,
                    $definition[0]
                );
            }

            // Prevent class property duplications
            if ($reflectionClass->hasProperty($property->getName())) {
                throw new Exception\ReflectionException(
                    "Property '" . $property->getName() . "' already defined as"
                    . " public property!",
                    $this->className,
                    $definition[0]
                );
            }

            $this->properties[$property->getName()] = $property;

            if ($property->hasOption(Entity\Reflection\Property\Option\Computed::KEY)) {
                $this->computedProperties[] = $property->getName();
            }
        }
    }

    public function getAdapterName()
    {
        return $this->adapterName;
    }

    public function getAdapterResource()
    {
        return $this->adapterResource;
    }

    public function hasAdapter()
    {
        return !empty($this->adapterName);
    }

    public function hasProperty($name)
    {
        return isset($this->properties[$name]);
    }
    
    public function hasPublicProperty($name) {
        return isset($name, $this->publicProperties);
    }
    
    public function hasComputedProperty($name) {
        return in_array($name, $this->computedProperties);
    }
    
    public function hasDefinedProperty($name) {
        return
            $this->hasProperty($name)
            || $this->hasPublicProperty($name)
            || $this->hasComputedProperty($name);
    }

    /**
     * Get property reflection object
     *
     * @param string $name
     *
     * @return Reflection\Property
     *
     * @throws Exception\InvalidArgumentException
     */
    public function getProperty($name)
    {
        if (!$this->hasProperty($name)) {
            throw new Exception\InvalidArgumentException(
                "Unknown property " . $name . "!"
            );
        }
        return $this->properties[$name];
    }

    /**
     * @return array|Entity\Reflection\Property[]
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Get arg's related files
     *
     * @param array  $files
     *
     * @return array
     */
    public function getRelatedFiles(array $files = [])
    {
        if (in_array($this->fileName, $files)) {
            return $files;
        }

        $files[] = $this->fileName;
        foreach ($this->properties as $property) {
            if (in_array($property->getType(), [Reflection\Property::TYPE_COLLECTION, Reflection\Property::TYPE_ENTITY])) {
                $files += self::load($property->getTypeOption())->getRelatedFiles($files);
            }
        }
        return $files;
    }

    public function hasPrimary()
    {
        foreach ($this->properties as $property) {

            if ($property->hasOption(Entity\Reflection\Property\Option\Primary::KEY)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get primary property reflection
     *
     * @return Reflection\Property
     *
     * @throws \Exception
     */
    public function getPrimaryProperty()
    {
        foreach ($this->properties as $property) {

            if ($property->hasOption(Entity\Reflection\Property\Option\Primary::KEY)) {
                return $property;
            }
        }
        throw new \Exception(
            "Primary property not defined in " . $this->className . "!"
        );
    }

    public function getPublicProperties()
    {
        return $this->publicProperties;
    }

    /**
     * @return array
     */
    public function getComputedProperties()
    {
        return $this->computedProperties;
    }

    /**
     * Create's entity values iterator
     *
     * @param \UniMapper\Entity $entity Entity instance
     * @param array             $data   Entity data
     *
     * @return \UniMapper\Entity\Iterator
     */
    public function createIterator(Entity $entity, array $options = []) {
        $class = $this::$entityIterator;
        return new $class($entity, $options ? $options : self::$defaultEntityIterationOptions);
    }
}
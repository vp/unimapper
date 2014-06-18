<?php

namespace UniMapper\Reflection\Entity;

use UniMapper\EntityCollection,
    UniMapper\Validator,
    UniMapper\Reflection,
    UniMapper\NamingConvention as NC,
    UniMapper\Exceptions\InvalidArgumentException,
    UniMapper\Exceptions\PropertyException,
    UniMapper\Exceptions\PropertyTypeException;

/**
 * Entity property reflection
 */
class Property
{

    /** @var string */
    protected $type;

    /** @var string */
    protected $name;

    /** @var string */
    protected $mapping;

    /** @var array $basicTypes */
    protected $basicTypes = ["boolean", "integer", "double", "string", "array"];

    /** @var \UniMapper\Reflection\Entity */
    protected $entityReflection;

    /** @var \UniMapper\Reflection\Entity\Property\Enumeration $enumeration */
    protected $enumeration;

    /** @var string $definition Raw property docblok definition */
    protected $rawDefinition;

    /** @var boolean $primary Is property defined as primary? */
    protected $primary = false;

    /** @var \UniMapper\Reflection\Entity\Property\Validators */
    protected $validators;

    /** @var boolean $computed Is property computed? */
    protected $computed = false;

    /** @var \UniMapper\Reflection\Entity\Property\Association $association */
    protected $association;

    /** @var array */
    private $supportedAssociations = [
        Property\Association\HasOne::TYPE => "HasOne",
        Property\Association\HasMany::TYPE => "HasMany",
        Property\Association\BelongsToOne::TYPE => "BelongsToOne",
        Property\Association\BelongsToMany::TYPE => "BelongsToMany"
    ];

    /** @var boolean */
    protected $writable = true;

    public function __construct($definition, Reflection\Entity $entityReflection)
    {
        $this->rawDefinition = $definition;
        $this->entityReflection = $entityReflection;

        $arguments = preg_split('/\s+/', $definition, null, PREG_SPLIT_NO_EMPTY);

        // read only property
        if ($arguments[0] === "-read") {
            $this->writable = false;
            array_shift($arguments);
        }

        $this->readType($arguments[0]);
        next($arguments);

        $this->readName($arguments[1]);
        next($arguments);

        foreach ($arguments as $argument) {
            $this->readFilters($argument);
        }
    }

    public function isWritable()
    {
        return $this->writable;
    }

    /**
     * Get list of supported basic types
     *
     * @return array
     */
    public function getBasicTypes()
    {
        return $this->basicTypes;
    }

    /**
     * Get property name
     *
     * @return string
     *
     * @throws \UniMapper\Exceptions\PropertyException
     */
    public function getName()
    {
        if ($this->name === null) {
            throw new PropertyException(
                "Property name is not set!",
                $this->entityReflection,
                $this->rawDefinition
            );
        }
        return $this->name;
    }

    /**
     * Get entity reflection
     *
     * @return \UniMapper\Reflection\Entity
     */
    public function getEntityReflection()
    {
        return $this->entityReflection;
    }

    /**
     * Read and set name from docblock definiton
     *
     * @param string $definition Docblok definition
     *
     * @return void
     *
     * @throws \UniMapper\Exceptions\PropertyException
     */
    protected function readName($definition)
    {
        $length = strlen($definition);
        if ($length === 1 || substr($definition, 0, 1) !== "$") {
            throw new PropertyException(
                "Invalid property name definition!",
                $this->entityReflection,
                $this->rawDefinition
            );
        }
        $this->name = substr($definition, 1, $length);
    }

    /**
     * Get property name
     *
     * @return string
     *
     * @throws \UniMapper\Exceptions\PropertyException
     */
    public function getEnumeration()
    {
        return $this->enumeration;
    }

    public function getMappedName()
    {
        if ($this->mapping !== null) {
            return $this->mapping;
        }
        return $this->name;
    }

    /**
     * Get property type
     *
     * @return string
     *
     * @throws \UniMapper\Exceptions\PropertyTypeException
     */
    public function getType()
    {
        if ($this->type === null) {
            throw new PropertyTypeException(
                "Property type is not set!",
                $this->entityReflection,
                $this->rawDefinition
            );
        }
        return $this->type;
    }

    /**
     * Read and set property type from docblock definiton
     *
     * @param string $definition Docblok definition
     *
     * @throws \UniMapper\Exceptions\PropertyTypeException
     */
    protected function readType($definition)
    {
        $basic = implode("|", $this->basicTypes);
        if (preg_match("#^(" . $basic . ")$#", $definition)) {
            // Basic type

            return $this->type = $definition;
        } elseif ($definition === "DateTime") {
            // DateTime

            return $this->type = $definition;
        } elseif (class_exists(NC::nameToClass($definition, NC::$entityMask))) {
            // Entity

            return $this->type = NC::nameToClass($definition, NC::$entityMask);
        } elseif (preg_match("#(.*?)\[\]#s", $definition)) {
            // Collection

            try {
                return $this->type = new EntityCollection(NC::nameToClass(rtrim($definition, "[]"), NC::$entityMask));
            } catch (InvalidArgumentException $expection) {

            }
        }

        throw new PropertyTypeException("Unsupported type '" . $definition . "'!", $this->entityReflection, $this->rawDefinition);
    }

    /**
     * Read and set filters from docblock definiton
     *
     * @param string $definition Property definition from docblok
     *
     * @return void
     */
    protected function readFilters($definition)
    {
        if (preg_match("#m:computed#s", $definition, $matches)) {
            // m:computed

            $computedMethodName = $this->getComputedMethodName();
            if (!method_exists($this->entityReflection->getClassName(), $computedMethodName)) {
                throw new PropertyException("Can not find computed method with name " . $computedMethodName . "!", $this->entityReflection, $definition);
            }
            $this->computed = true;
        } elseif (preg_match("#m:map\((.*?)\)#s", $definition, $matches)) {
            // m:map(column)

            if ($this->computed) {
                throw new PropertyException("Can not combine m:computed with m:map!", $this->entityReflection, $definition);
            }
            if ($matches[1]) {
                $this->mapping = $matches[1];
            }
        } elseif (preg_match("#m:enum\(([a-zA-Z0-9]+|self|parent)::([a-zA-Z0-9_]+)\*\)#", $definition, $matches)) {
            // m:enum(self::CUSTOM_*)
            // m:enum(parent::CUSTOM_*)
            // m:enum(MY_CLASS::CUSTOM_*)

            if ($this->computed) {
                throw new PropertyException("Can not combine m:computed with m:enum!", $this->entityReflection, $definition);
            }
            $this->enumeration = new Property\Enumeration($matches, $definition, $this->entityReflection);
        } elseif (preg_match("#m:primary#s", $definition, $matches)) {
            // m:primary

            if ($this->computed) {
                throw new PropertyException("Can not combine m:computed with m:primary!", $this->entityReflection, $definition);
            }
            $this->primary = true;
        } elseif (preg_match("#m:validate\((.*?)\)#s", $definition, $matches)) {
            // m:validate(url)
            // m:validate(ipv4|ipv6)

            if ($this->computed) {
                throw new PropertyException("Can not combine m:computed with m:validate!", $this->entityReflection, $definition);
            }
            $this->validators = new Property\Validators($matches[1], $definition, $this->entityReflection);
        } elseif (preg_match("#m:assoc\((.*?)\)#s", $definition, $matches)) {
            // m:assoc(1:1=key|targetKey)

            if ($this->computed || $this->mapping || $this->enumeration) {
                throw new PropertyException("Association can not be combined with mapping, computed or enumeration!", $this->entityReflection, $definition);
            }

            // Get target entity class
            if ($this->type instanceof EntityCollection) {
                $targetEntityClass = $this->type->getEntityClass();
            } elseif (is_subclass_of($this->type, "UniMapper\Entity")) {
                $targetEntityClass = $this->type;
            } else {
                throw new PropertyException("Property type must be collection or entity if association defined!", $this->entityReflection, $definition);
            }

            if (!strpos($matches[1], "=")) {
                throw new PropertyException("Bad association definition!", $this->entityReflection, $definition);
            }
            list($assocType, $parameters) = explode("=", $matches[1]);
            if (!isset($this->supportedAssociations[$assocType])) {
                throw new PropertyException("Association type '" . $assocType . "' not supported!", $this->entityReflection, $definition);
            }
            $assocClass = "UniMapper\Reflection\Entity\Property\Association\\" . $this->supportedAssociations[$assocType];

            $this->association = new $assocClass($this->entityReflection, new Reflection\Entity($targetEntityClass), $parameters);
        }
    }

    /**
     * Validate property value type
     *
     * @param mixed $value Given value
     *
     * @throws \UniMapper\Exceptions\PropertyException
     * @throws \UniMapper\Exceptions\PropertyTypeException
     * @throws \Exception
     */
    public function validateValue($value)
    {
        $expectedType = $this->type;

        if ($expectedType === null) { // @todo check entity validity first => move out
            throw new PropertyException("Property type not defined on property " . $this->name . "!", $this->entityReflection, $this->rawDefinition);
        }

        // Validators
        if ($this->validators) {

            foreach ($this->validators->getCallbacks() as $callback) {
                if (!call_user_func_array($callback, [$value])) {
                    throw new PropertyTypeException("Value " . $value . " is not valid for " . $callback[0] . "::" . $callback[1] . " on property " . $this->name . "!", $this->entityReflection, $this->rawDefinition);
                }
            }
        }

        // Enumeration
        if ($this->enumeration !== null && !$this->enumeration->isValueFromEnum($value)) {
            throw new PropertyTypeException("Value " . $value . " is not from defined entity enumeration range on property " . $this->name . "!", $this->entityReflection, $this->rawDefinition);
        }

        // Basic type
        if ($this->isBasicType()) {

            if (gettype($value) === $expectedType) {
                return;
            }
            throw new PropertyTypeException("Expected " . $expectedType . " but " . gettype($value) . " given on property " . $this->name . "!", $this->entityReflection, $this->rawDefinition);
        }

        // Object
        if (is_object($expectedType)) {
            $expectedType = get_class($expectedType);
        }

        if (class_exists($expectedType)) {

            if ($value instanceof $expectedType) {
                return;
            }

            $givenType = gettype($value);
            if ($givenType === "object") {
                $givenType = get_class($value);
            }
            throw new PropertyTypeException("Expected " . $expectedType . " but " . $givenType . " given on property " . $this->name . "!", $this->entityReflection, $this->rawDefinition);
        }

        $givenType = gettype($value);
        if ($givenType === "object") {
            $givenType = get_class($value);
        }
        throw new \Exception("Expected " . $expectedType . " but " . $givenType . " given on property " . $this->name . ". It could be an internal ORM error!");
    }

    /**
     * Try to convert value on required type automatically
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function convertValue($value)
    {
        if ($this->isBasicType()) {
            // Basic

            if ($this->type === "boolean" && strtolower($value) === "false") {
                return false;
            }

            if (settype($value, $this->type)) {
                return $value;
            }
        } elseif ($this->type === "DateTime") {
            // DateTime

            $date = $value;
            if (Validator::validateTraversable($value)) {
                if (isset($value["date"])) {
                    $date = $value["date"];
                }
            }
            try {
                $date = new \DateTime($date);
            } catch (\Exception $e) {

            }
            if ($date instanceof \DateTime) {
                return $date;
            }
        } elseif ($this->type instanceof EntityCollection && Validator::validateTraversable($value)) {
            // Collection

            $entityClass = $this->type->getEntityClass();
            $collection = new EntityCollection($entityClass);
            foreach ($value as $index => $data) {
                $collection[$index] = new $entityClass; // @todo better reflection giving
                $collection[$index]->import($data);
            }
            return $collection;
        }

        throw new \Exception("Can not convert value on property '" . $this->name . "' automatically!");
    }

    public function isBasicType()
    {
        return in_array($this->type, $this->getBasicTypes());
    }

    public function isComputed()
    {
        return $this->computed;
    }

    public function isPrimary()
    {
        return $this->primary;
    }

    public function isAssociation()
    {
        return $this->association !== null;
    }

    public function getAssociation()
    {
        return $this->association;
    }

    public function getComputedMethodName()
    {
       return "compute" . ucfirst($this->name);
    }

}

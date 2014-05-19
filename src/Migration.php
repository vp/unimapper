<?php

namespace UniMapper;

use UniMapper\Mapper,
    UniMapper\Reflection,
    UniMapper\NamingConvention as NC,
    UniMapper\Exceptions\MigrationException;

class Migration
{

    /** @var array */
    private $mappers = [];

    /** @var string */
    private $dataDir;

    /** @var array $schema List of reflections from registered entities */
    private $schema = [];

    public function __construct($dataDir)
    {
        if (!is_dir($dataDir)) {
            throw new MigrationException("Directory '" . $dataDir . "' not found!");
        }
        $this->dataDir = $dataDir;
    }

    public function registerEntity($name)
    {
        if (isset($this->schema[$name])) {
            throw new MigrationException("Entity with name '" . $name . "' already registered!");
        }
        $this->schema[$name] = new Reflection\Entity(NC::nameToClass($name, NC::$entityMask));
    }

    public function registerMapper(Mapper $mapper)
    {
        if (isset($this->mappers[$mapper->getName()])) {
            throw new MigrationException("Mapper with name '" . $mapper->getName() . "' already registered!");
        }
        $this->mappers[$mapper->getName()] = $mapper;
    }

    public function generate()
    {
        $schemaFile = $this->dataDir . "/schema.json";

        // Read original schema
        $originalSchema = [];
        if (is_file($schemaFile)) {

            foreach ($this->readFile($schemaFile) as $name => $serializedReflection) {
                $originalSchema[$name] = unserialize($serializedReflection);
            }
        }

        // Find changes
        $changes = [];
        if (!empty($originalSchema)) {
            $changes = $this->generateChanges($originalSchema, $this->schema);
        }

        // Write new schema
        $newSchema = [];
        foreach ($this->schema as $name => $reflection) {
            $newSchema[$name] = serialize($reflection);
        }
        if (!empty($newSchema)) {
            $this->saveFile($schemaFile, $newSchema);
        }
    }

    private function generateChanges(array $originalSchema, Reflection\Entity $newReflection)
    {
        foreach ($originalSchema as $originalEntityName => $originalEntityReflection) {

            if (isset($new[$originalEntityName])) {
                // Find properties changes
                //dump($new[$originalEntityName]->);

            }
        }
    }

    public function getDiff()
    {

    }

    public function down()
    {

    }

    public function up()
    {
//        while ($filename = readdir($this->dataDir)) {
//            $filePath = $this->dataDir . "/" .  $filename;
//            $migration = $this->readFile(filePath);
//            $migration
//        }
    }

    /**
     * Read migration file
     *
     * @param string $path
     *
     * @return array
     */
    private function readFile($path)
    {
        return json_decode(file_get_contents($path));
    }

    /**
     * Save migration file
     *
     * @param string $path
     * @param array  $data
     */
    private function saveFile($path, array $data)
    {
        file_put_contents($path, json_encode($data));
    }

}
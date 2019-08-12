<?php

namespace UniMapper\Query;

use UniMapper\Entity\Reflection;
use UniMapper\Convention;
use UniMapper\Cache\ICache;
use UniMapper\Association;

class Select extends \UniMapper\Query
{

    use Filterable;
    use Limit;
    use Selectable;
    use Sortable;

    const ASC = "asc",
          DESC = "desc";

    protected $cached = false;
    protected $cachedOptions = [];

    public function __construct(Reflection $reflection)
    {
        parent::__construct($reflection);
        $this->select(array_slice(func_get_args(), 1));
    }

    public function cached($enable = true, array $options = [])
    {
        $this->cached = (bool) $enable;
        $this->cachedOptions = $options;
        return $this;
    }

    protected function onExecute(\UniMapper\Connection $connection)
    {
        $adapter = $connection->getAdapter($this->reflection->getAdapterName());
        $mapper = $connection->getMapper();
        $cache = null;

        if ($this->cached) {
            $cache = $connection->getCache();
        }

        if ($cache) {

            $cachedResult = $cache->load($this->_getQueryChecksum());
            if ($cachedResult) {
                return $mapper->mapCollection(
                    $this->reflection->getName(),
                    $cachedResult
                );
            }
        }

        $selection = $this->createQuerySelection();
        $finalSelection = $this->addAssocSelections($selection);
        $remoteAssociations = $this->createRemoteAssociations();

        $query = $adapter->createSelect(
            $this->reflection->getAdapterResource(),
            $this->createAdapterSelection($mapper, $this->reflection, $finalSelection, $this->assocDefinitions, $remoteAssociations),
            $this->orderBy,
            $this->limit,
            $this->offset
        );

        $this->setQueryFilters($this->filter, $query, $mapper);

        // Set's query adapter local associations
        list($adapterAssociations, $adapterAssociationsFilters) = $this->getAdapterAssociations($mapper);
        if ($adapterAssociations) {
            $query->setAssociations($adapterAssociations, $adapterAssociationsFilters);
        }

        // Execute adapter query
        $result = $adapter->execute($query);

        if (!empty($result)) {


            // Get remote associations
            if ($remoteAssociations) {

                settype($result, "array");

                /** @var \UniMapper\Association $association */
                foreach ($remoteAssociations as $colName => $association) {

                    $assocKey = $association->getKey();

                    $assocValues = [];
                    foreach ($result as $item) {

                        if (is_array($item)) {
                            $assocValues[] = $item[$assocKey];
                        } else {
                            $assocValues[] = $item->{$assocKey};
                        }
                    }

                    $definition = $this->assocDefinitions[$colName];
                    $associationSelection = $this->createAdapterSelection(
                        $mapper,
                        $definition->getTargetReflection(),
                        $selection[$colName]
                    );

                    $associationFilter = $mapper->unmapFilter(
                        $definition->getTargetReflection(),
                        $this->assocFilters[$colName]
                    );

                    $associated = $association->load(
                        $connection,
                        $assocValues,
                        $associationSelection,
                        $associationFilter
                    );

                    // Merge returned associations
                    if (!empty($associated)) {

                        $result = $this->_mergeAssociated(
                            $result,
                            $associated,
                            $assocKey,
                            $colName
                        );
                    }
                }
            }
        }

        if ($cache) {

            $cachedOptions = $this->cachedOptions;

            // Cache invalidation should depend on entity changes
            if (isset($cachedOptions[ICache::FILES])) {
                $cachedOptions[ICache::FILES] += $this->reflection->getRelatedFiles();
            } else {
                $cachedOptions[ICache::FILES] = $this->reflection->getRelatedFiles();
            }

            $cache->save(
                $this->_getQueryChecksum(),
                $result,
                $cachedOptions
            );
        }

        $collection = $mapper->mapCollection(
            $this->reflection->getName(),
            empty($result) ? [] : $result
        );

        $collection->setSelection($selection);

        return $collection;
    }

    /**
     * Merge associated data with result
     *
     * @param array  $result
     * @param array  $associated
     * @param string $refKey
     * @param string $colName
     *
     * @return array
     */
    private function _mergeAssociated(
        array $result,
        array $associated,
        $refKey,
        $colName
    ) {
        foreach ($result as $index => $item) {

            if (is_array($item)) {
                $refValue = $item[$refKey];
            } else {
                $refValue = $item->{$refKey};
            }

            if (isset($associated[$refValue])) {

                if (is_array($result[$index])) {
                    $result[$index][$colName] = $associated[$refValue];
                } else {
                    $result[$index]->{$colName} = $associated[$refValue];
                }
            }
        }
        return $result;
    }

    /**
     * Get a unique query checksum
     *
     * @return integer
     */
    private function _getQueryChecksum()
    {
        // TODO: include filters and association selections and filters to be really unique
        return md5(
            serialize(
                [
                    "name" => $this->getName(),
                    "entity" => Convention::classToName(
                        $this->reflection->getClassName(), Convention::ENTITY_MASK
                    ),
                    "limit" => $this->limit,
                    "offset" => $this->offset,
                    "selection" => $this->selection,
                    "orderBy" => $this->orderBy,
                    "associations" => array_keys($this->assocDefinitions),
                    "conditions" => $this->filter
                ]
            )
        );
    }

}
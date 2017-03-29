<?php

namespace UniMapper\Query;
use UniMapper\Convention as UNC;
use UniMapper\Cache\ICache;

class Count extends \UniMapper\Query implements ICachableQuery
{

    use Filterable;

    protected $cached = false;
    protected $cachedOptions = [];

    public function cached($enable = true, array $options = [])
    {
        $this->cached = (bool) $enable;
        $this->cachedOptions = $options;
        return $this;
    }

    protected function onExecute(\UniMapper\Connection $connection)
    {
        $adapter = $connection->getAdapter($this->reflection->getAdapterName());

        $cache = null;

        if ($this->cached) {
            $cache = $connection->getCache();
        }

        if ($cache) {

            $cachedResult = $cache->load($this);
            if ($cachedResult) {
                return $cachedResult;
            }
        }

        $query = $adapter->createCount(
            $this->reflection->getAdapterResource()
        );

        $this->setQueryFilters($this->filter, $query, $connection->getMapper());

        $result = (int) $adapter->execute($query);

        if ($cache) {

            $cachedOptions = $this->cachedOptions;

            // Add default cache tag
            if (isset($cachedOptions[ICache::TAGS])) {
                $cachedOptions[ICache::TAGS][] = ICache::TAG_QUERY; // @todo is it really array?
            } else {
                $cachedOptions[ICache::TAGS] = [ICache::TAG_QUERY];
            }

            // Cache invalidation should depend on entity changes
            if (isset($cachedOptions[ICache::FILES])) {
                $cachedOptions[ICache::FILES] += $this->entityReflection->getRelatedFiles();
            } else {
                $cachedOptions[ICache::FILES] = $this->entityReflection->getRelatedFiles();
            }

            $cache->save(
                $this,
                $result,
                $cachedOptions
            );
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
        return md5(
            serialize(
                [
                    "name" => $this->getName(),
                    "entity" => UNC::classToName(
                        $this->reflection->getClassName(), UNC::ENTITY_MASK
                    ),
                    "conditions" => $this->filter
                ]
            )
        );
    }


    public function getCacheKey()
    {
        return $this->_getQueryChecksum();
    }

    public function getEntityReflection()
    {
        return $this->reflection;
    }

    public function getCachedOptions()
    {
        return $this->cachedOptions;
    }


}
<?php

namespace UniMapper\Query;


interface ICachableQuery
{
    public function cached($enable = true, array $options = []);

    /**
     * Return's unique cache key
     *
     * @return string
     */
    public function getCacheKey();

    /**
     * @return \UniMapper\Entity\Reflection
     */
    public function getEntityReflection();

    /**
     * @return array
     */
    public function getCachedOptions();
}
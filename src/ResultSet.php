<?php

namespace JoliCode\Elastically;

use Elastica\ResultSet as ElasticaResultSet;

class ResultSet extends ElasticaResultSet
{
    /*
     * To remove when Elastica is compatible with Elasticsearch >= 7
     * @see https://github.com/ruflin/Elastica/pull/1563
     */
    public function getTotalHits()
    {
        $data = $this->getResponse()->getData();

        if (is_array($data['hits']['total'])) {
            return (int) ($data['hits']['total']['value'] ?? 0);
        }

        return (int) ($data['hits']['total'] ?? 0);
    }
}

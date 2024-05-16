<?php

namespace fostercommerce\meilisearch\services;

use fostercommerce\meilisearch\models\Index;
use fostercommerce\meilisearch\models\Settings;
use fostercommerce\meilisearch\Plugin;
use yii\base\Component;

class Sync extends Component
{
    use Meili;

    public const DEFAULT_PRIMARY_KEY = 'id';

    private ?Settings $_settings = null;

    public function init(): void
    {
        parent::init();

        $this->initMeiliClient();
        $this->_settings = Plugin::getInstance()->settings;
    }

    public function syncSettings(?string $indexName = null): void
    {
        foreach ($this->getIndices($indexName) as $indexHandle => $indexConfig) {
            $createIndexRes = $this->meiliClient->createIndex($indexHandle);
            $this->meiliClient->waitForTask($createIndexRes['taskUid']);

            $index = $this->meiliClient->index($indexHandle);
            $indexSettings = $indexConfig->settings;

            $index->updateRankingRules($indexSettings->ranking);
            $index->updateSearchableAttributes($indexSettings->searchableAttributes);
            $index->updateFilterableAttributes($indexSettings->filterableAttributes);
            $index->updateFaceting($indexSettings->faceting);
        }
    }

    public function syncIndices(?string $indexName = null, ?string $identifier = null): void
    {
        foreach ($this->getIndices($indexName) as $indexHandle => $indexConfig) {
            $index = $this->meiliClient->index($indexHandle);
            $fetch = $indexConfig->fetch;
            $index->addDocuments(
                $fetch($identifier),
                $indexConfig->settings->primaryKey ?? self::DEFAULT_PRIMARY_KEY
            );
        }
    }

    public function refreshIndices(?string $indexName = null): void
    {
        foreach ($this->getIndices($indexName) as $indexHandle => $indexConfig) {
            $index = $this->meiliClient->index($indexHandle);
            $index->deleteAllDocuments();
            $fetch = $indexConfig->fetch;
            $index->addDocuments(
                $fetch(null),
                $indexConfig->settings->primaryKey ?? self::DEFAULT_PRIMARY_KEY
            );
        }
    }

    public function delete(string $identifier, ?string $indexName = null): void
    {
        foreach (array_keys($this->getIndices($indexName)) as $indexHandle) {
            $this->meiliClient->index($indexHandle)->deleteDocument($identifier);
        }
    }

    /**
     * @return Index[]
     */
    private function getIndices(?string $indexName = null): array
    {
        if ($indexName !== null) {
            $index = $this->_settings->indices[$indexName];
            if ($index !== null) {
                return [
                    $indexName => $index,
                ];
            }

            return [];
        }

        return $this->_settings->indices;
    }
}
<?php

namespace ScoutElastic\Indexers;

use Illuminate\Database\Eloquent\Collection;
use ScoutElastic\Facades\ElasticClient;
use ScoutElastic\Migratable;
use ScoutElastic\Payloads\RawPayload;
use ScoutElastic\Payloads\TypePayload;

class BulkIndexer implements IndexerInterface
{
    public function update(Collection $models)
    {
        $model = $models->first();
        $indexConfigurator = $model->getIndexConfigurator();

        $bulkPayload = new TypePayload($model);

        if (in_array(Migratable::class, class_uses_recursive($indexConfigurator))) {
            $bulkPayload->useAlias('write');
        }

        $models->each(function ($model) use ($bulkPayload) {
            $modelData = $model->toSearchableArray();

            if (empty($modelData)) {
                return true;
            }

            $actionPayload = (new RawPayload())
                ->set('index._id', $model->getKey());

            $bulkPayload
                ->add('body', $actionPayload->get())
                ->add('body', $modelData);
        });

        ElasticClient::bulk($bulkPayload->get());
    }

    public function delete(Collection $models)
    {
        $model = $models->first();

        $bulkPayload = new TypePayload($model);

        $models->each(function ($model) use ($bulkPayload) {
            $actionPayload = (new RawPayload())
                ->set('delete._id', $model->getKey());

            $bulkPayload->add('body', $actionPayload->get());
        });

        ElasticClient::bulk($bulkPayload->get());
    }
}
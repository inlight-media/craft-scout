<?php

namespace rias\scout\controllers;

use Craft;
use craft\helpers\Json;
use craft\web\Controller;
use rias\scout\controllers\BaseController;
use rias\scout\models\AlgoliaIndex;
use rias\scout\Scout;
use yii\base\InvalidConfigException;
use yii\web\Response;

class IndexController extends BaseController
{
    /**
     * Flush one or all Algolia indexes.
     *
     * @return Response
     */
    public function actionFlush()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $index = $request->getBodyParam('flushIndex');

        try {
            $mappings = $this->getMappings($index);
            $indexCount = count($mappings);

            /* @var AlgoliaIndex $mapping */
            foreach ($mappings as $mapping) {
                $index = Scout::$plugin->scoutService->getClient()->initIndex($mapping->indexName);
                $index->clearObjects();
            }

            Craft::$app->getSession()->setNotice(
                Craft::t('scout', 'Flushed {indexCount} index{plural}.', [
                    'indexCount' => $indexCount,
                    'plural' => $indexCount === 1 ? '' : 'es',
                ])
            );
        } catch (\Throwable $e) {
            Craft::$app->getSession()->setError($e->getMessage());
        }

        return $this->redirect(Craft::$app->getRequest()->getReferrer());
    }

    /**
     * @return Response
     */
    public function actionImport(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $index = $request->getBodyParam('importIndex');

        try {
            $mappings = $this->getMappings($index);
            $indexCount = count($mappings);

            /* @var AlgoliaIndex $mapping */
            foreach ($mappings as $mapping) {
                // Get all elements to index
                $elements = $mapping->getElementQuery()->all();

                $algoliaIndex = new AlgoliaIndex($mapping);
                $algoliaIndex->indexElements($elements);
            }

            // Run the queue after adding all elements
            Craft::$app->queue->run();

            Craft::$app->getSession()->setNotice(
                Craft::t('scout', 'Imported {indexCount} index{plural}.', [
                    'indexCount' => $indexCount,
                    'plural' => $indexCount === 1 ? '' : 'es',
                ])
            );
        } catch (\Throwable $e) {
            Craft::$app->getSession()->setError($e->getMessage());
        }

        return $this->redirect(Craft::$app->getRequest()->getReferrer());
    }
}

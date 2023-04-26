<?php

namespace Utopia\Transfer;

abstract class Source extends Target
{
    /**
     * Transfer Groups into destination
     *
     * @param array $groups
     * @param callable $callback
     */
    public function run(array $groups, callable $callback): void
    {
        //TODO: Check we have no unsupported groups.

        if (in_array(Transfer::GROUP_AUTH, $groups)) {
            $this->exportAuth(100, function (array $users) use ($callback) {
                $this->resourceCache->addAll($users);
                $callback(Transfer::GROUP_AUTH, $users);
            });
        }

        if (in_array(Transfer::GROUP_DATABASES, $groups)) {
            $this->exportDatabases(100, function (array $databases) use ($callback) {
                $this->resourceCache->addAll($databases);
                $callback(Transfer::GROUP_DATABASES, $databases);
            });
        }

        if (in_array(Transfer::GROUP_DOCUMENTS, $groups)) {
            $this->exportDocuments(100, function (array $documents) use ($callback) {
                $this->resourceCache->addAll($documents);
                $callback(Transfer::GROUP_DOCUMENTS, $documents);
            });
        }

        if (in_array(Transfer::GROUP_STORAGE, $groups)) {
            $this->exportFiles(100, function (array $files) use ($callback) {
                $this->resourceCache->addAll($files);
                $callback(Transfer::GROUP_STORAGE, $files);
            });
        }

        if (in_array(Transfer::GROUP_FUNCTIONS, $groups)) {
            $this->exportFunctions(100, function (array $functions) use ($callback) {
                $this->resourceCache->addAll($functions);
                $callback(Transfer::GROUP_FUNCTIONS, $functions);
            });
        }
    }

    /**
     * Export Users
     *
     * @param int $batchSize
     * @param callable $callback Callback function to be called after each batch, $callback(user[] $batch);
     *
     * @return void
     */
    abstract public function exportAuth(int $batchSize, callable $callback): void;

    /**
     * Export Databases
     *
     * @param int $batchSize Max 100
     * @param callable $callback Callback function to be called after each database, $callback(database[] $batch);
     *
     * @return void
     */
    abstract public function exportDatabases(int $batchSize, callable $callback): void;

    /**
     * Export Documents
     *
     * @param int $batchSize Max 100
     * @param callable $callback Callback function to be called after each document, $callback(document[] $batch);
     *
     * @return void
     */
    abstract public function exportDocuments(int $batchSize, callable $callback): void;

    /**
     * Export Files
     *
     * @param int $batchSize Max 5
     * @param callable $callback Callback function to be called after each batch, $callback(File[]|Bucket[] $batch);
     *
     * @return void
     */
    abstract public function exportFiles(int $batchSize, callable $callback): void;

    /**
     * Export Functions
     *
     * @param int $batchSize Max 100
     * @param callable $callback Callback function to be called after each function, $callback(function[] $batch);
     *
     * @return void
     */
    abstract public function exportFunctions(int $batchSize, callable $callback): void;
}

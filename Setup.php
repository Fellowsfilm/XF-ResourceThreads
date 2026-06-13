<?php

namespace VersoBit\ResourceThreads;

use XF\AddOn\AbstractSetup;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
    public function install(array $stepParams = [])
    {
        $this->createUpdatePostMapTable();
    }

    public function upgrade(array $stepParams = [])
    {
        $this->createUpdatePostMapTable();
    }

    public function uninstall(array $stepParams = [])
    {
        $schemaManager = $this->schemaManager();
        if ($schemaManager->tableExists('xf_versobit_resource_threads_update_post'))
        {
            $schemaManager->dropTable('xf_versobit_resource_threads_update_post');
        }
    }

    protected function createUpdatePostMapTable(): void
    {
        $schemaManager = $this->schemaManager();
        if ($schemaManager->tableExists('xf_versobit_resource_threads_update_post'))
        {
            return;
        }

        $schemaManager->createTable('xf_versobit_resource_threads_update_post', function (Create $table)
        {
            $table->addColumn('resource_update_id', 'int');
            $table->addColumn('post_id', 'int');
            $table->addColumn('map_source', 'varchar', 30)->setDefault('');
            $table->addColumn('map_date', 'int')->setDefault(0);
            $table->addPrimaryKey('resource_update_id');
            $table->addKey('post_id');
        });
    }
}

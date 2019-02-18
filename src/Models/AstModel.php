<?php

namespace Klepak\AstModel\Models;

use Exception;
use MicrosoftAzure\Storage\Table\TableRestProxy;
use MicrosoftAzure\Storage\Table\Models\Filters\Filter;

class AstModel
{
    protected $connection = null;
    protected $table = null;

    protected $connectionConfig = null;

    private $tableClient;
    private $azureEntity;
    private $azureEntityFilter = null;

    public function __construct($azureEntity = null)
    {
        if($this->connection == null)
            throw new Exception('Connection not defined');

        if($this->table == null)
            throw new Exception('Table not defined');

        $this->connectionConfig = config()->get("ast_model.connections.{$this->connection}");

        if($this->connectionConfig == null)
            throw new Exception('Connection not found: ' . $this->connection);

        if($azureEntity != null)
        {
            $this->azureEntity = $azureEntity;

            foreach($this->azureEntity->getProperties() as $propertyKey => $propertyData)
            {
                $this->{$propertyKey} = $propertyData->getValue();
            }
        }

        $this->tableClient = TableRestProxy::createTableService($this->connectionConfig['connectionString']);
    }

    public static function where($key, $value)
    {
        if(!isset($this))
            $instance = new static();
        else
            $instance = $this;

        $filter = Filter::applyQueryString("$key eq '$value'");

        if($instance->azureEntityFilter == null)
            $instance->azureEntityFilter = $filter;
        else
            $instance->azureEntityFilter = Filter::applyAnd($instance->azureEntityFilter, $filter);

        return $instance;
    }

    public static function all()
    {
        if(!isset($this))
            $instance = new static();
        else
            $instance = $this;

        $instance->azureEntityFilter = null;

        return $instance->get();
    }

    /**
     * catch (ServiceException $e)
     * Handle exception based on error codes and messages.
     * Error codes and messages are here:
     * http://msdn.microsoft.com/library/azure/dd179438.aspx
     */
    public function get()
    {
        $result = $this->tableClient->queryEntities($this->table, $this->azureEntityFilter);
        $entities = $result->getEntities();

        $models = [];
        foreach($entities as $entity)
        {
            $models[] = new static($entity);
        }

        return collect($models);
    }
}

<?php

namespace Respect\Relational;

use \PDO as PDO;

class Db
{

    protected $connection;
    protected $sql;
    protected $callback = null;

    public function __call($methodName, $arguments)
    {
        $this->sql->__call($methodName, $arguments);
        return $this;
    }

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->sql = new Sql();
    }

    public function fetch($object = '\stdClass', $extra = null)
    {
        $result = $this->performFetch(__FUNCTION__, $object, $extra);
        return is_callable($object) ? $object($result) : $result;
    }

    public function fetchAll($object = '\stdClass', $extra = null)
    {
        $result = $this->performFetch(__FUNCTION__, $object, $extra);
        return is_callable($object) ? array_map($object, $result) : $result;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function getSql()
    {
        return $this->sql;
    }

    public function prepare($queryString, $object = '\stdClass', $constructorArgs = null)
    {
        $statement = $this->connection->prepare($queryString);

        if (is_callable($object))
            $statement->setFetchMode(PDO::FETCH_OBJ);
        elseif (is_object($object))
            $statement->setFetchMode(PDO::FETCH_INTO, $object);
        elseif (!is_string($object))
            $statement->setFetchMode(PDO::FETCH_NAMED);
        elseif (is_null($constructorArgs))
            $statement->setFetchMode(PDO::FETCH_CLASS, $object);
        else
            $statement->setFetchMode(PDO::FETCH_CLASS, $object, $constructorArgs);

        return $statement;
    }

    public function query($rawSql)
    {
        $this->sql = new Sql($rawSql);
        return $this;
    }

    protected function performFetch($method, $object = '\stdClass', $extra = null)
    {
        $statement = $this->prepare((string) $this->sql, $object, $extra);
        $statement->execute($this->sql->getParams());
        $result = $statement->{$method}();
        $this->sql = new Sql();
        return $result;
    }

}

/**
 * LICENSE
 *
 * Copyright (c) 2009-2011, Alexandre Gomes Gaigalas.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *     * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *
 *     * Neither the name of Alexandre Gomes Gaigalas nor the names of its
 *       contributors may be used to endorse or promote products derived from this
 *       software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */
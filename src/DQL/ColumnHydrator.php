<?php

namespace App\DQL;

use Doctrine\DBAL\Exception;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;


class ColumnHydrator extends AbstractHydrator
{
    /**
     * @return array
     * @throws Exception
     */
    protected function hydrateAllData()
    {
        return $this->_stmt->fetchFirstColumn();
    }
}

<?php

namespace App\Trait;

use Doctrine\ORM\QueryBuilder;


Trait GenerateQueryBuilderTrait
{
    /**
     * @param QueryBuilder $qb
     * @param array|null $arrayWhere
     * @return QueryBuilder
     */
    public function generateWhereQueryBuilder(QueryBuilder &$qb, array $arrayWhere = null)
    {
        if($arrayWhere) {
            $countElement = 0;
            foreach ($arrayWhere as $arrayWhereOR) {
                $orStatement = $qb->expr()->orX();

                foreach (array_filter($arrayWhereOR) as $elementAND) {
                    if(is_array($elementAND)) { // [[X AND Y] OR [W AND Z]]
                        $andStatement = $qb->expr()->andX();
                        foreach ($elementAND as $itemAND) {
                            $this->getConditionByItem($qb, $andStatement, $itemAND);
                        }
                        $orStatement->add($andStatement);

                    } else { // [X OR Y]
                        $this->getConditionByItem($qb, $orStatement, $elementAND);
                    }

                    $countElement++;
                }

                $qb->andWhere($orStatement);
            }
        }

        return $qb;
    }

    /* *****************************************************************
    ********************************************************************
                            PRIVATE FUNCTION
    ********************************************************************
    *******************************************************************/
    /**
     * @param QueryBuilder $qb
     * @param $andOrStatement
     * @param $contition
     * @return mixed
     */
    private function getConditionByItem(QueryBuilder &$qb, &$andOrStatement, $contition)
    {
        $countElement = count($qb->getParameters());
        $arrayOperators = [
            'neq'  => '!=',
            'lte'  => '<=',
            'gte'  => '>=',
            'lt'   => '<',
            'eq'   => '=',
            'gt'   => '>',
            'like' => 'LIKE'
        ];

        foreach ($arrayOperators as $functionOperator => $itemOperator) {

            if (mb_strpos($contition, $itemOperator) !== FALSE) {
                $arrayItem = array_map('trim', explode($itemOperator, trim($contition)));

                if($arrayItem[1] == "NULL" && $itemOperator == "=") { // IF VALUE NULL
                    $andOrStatement->add($qb->expr()->isNull($arrayItem[0]));
                } else if($arrayItem[1] == "NULL" && $itemOperator == "!=") {  // IF VALUE NOT NULL
                    $andOrStatement->add($qb->expr()->isNotNull($arrayItem[0]));
                } else { // IF ELSE OPERATOR
                    $andOrStatement->add($qb->expr()->{$functionOperator}($arrayItem[0], ':__' . $countElement . '__'));
                    $qb->setParameter('__' . $countElement . '__', $arrayItem[1]);
                }


                $countElement++;
                break;
            }
        }

        if (mb_strpos($contition, 'BETWEEN') !== FALSE) {
            $arrayItem = array_map('trim', explode('BETWEEN', trim($contition)));
            $arrayFromTo = array_map('trim', explode('AND', $arrayItem[1]));
            $dateBegin = \DateTime::createFromFormat('Y-m-d H:i:s', $arrayFromTo[0] . ' 00:00:00');
            $dateEnd = \DateTime::createFromFormat('Y-m-d H:i:s', $arrayFromTo[1] . ' 23:59:59');

            $andOrStatement->add(
                $qb->expr()->between($arrayItem[0], ':__' . $countElement . 'from', ':__' . $countElement . 'to')
            );

            $qb->setParameter('__' . $countElement . 'from', $dateBegin)
                ->setParameter('__' . $countElement . 'to', $dateEnd);
        }

        return $andOrStatement;
    }
}

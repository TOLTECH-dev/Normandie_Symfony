<?php

namespace App\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;


class IfFunction extends FunctionNode
{
    private $expr = [];


    /**
     * @param SqlWalker $sqlWalker
     * @return string
     */
    public function getSql(SqlWalker $sqlWalker)
    {
        return sprintf(
            'IF(%s, %s, %s)',
            $sqlWalker->walkConditionalExpression($this->expr[0]),
            $this->expr[1] !== null ? $sqlWalker->walkArithmeticPrimary($this->expr[1]) : 'NULL',
            $this->expr[2] !== null ? $sqlWalker->walkArithmeticPrimary($this->expr[2]) : 'NULL'
        );
    }

    /**
     * @param Parser $parser
     * @throws QueryException
     */
    public function parse(Parser $parser)
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->expr[] = $parser->ConditionalExpression();

        $parser->match(TokenType::T_COMMA);
        if ($parser->getLexer()->isNextToken(TokenType::T_NULL)) {
            $parser->match(TokenType::T_NULL);
            $this->expr[] = null;
        } else {
            $this->expr[] = $parser->ArithmeticExpression();
        }
        $parser->match(TokenType::T_COMMA);
        if ($parser->getLexer()->isNextToken(TokenType::T_NULL)) {
            $parser->match(TokenType::T_NULL);
            $this->expr[] = null;
        } else {
            $this->expr[] = $parser->ArithmeticExpression();
        }

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}

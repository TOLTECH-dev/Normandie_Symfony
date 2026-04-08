<?php

namespace App\DQL;


use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\TokenType;

/**
 * Class DateFormatFunction
 *
 * Adds the hability to use the MySQL DATE_FORMAT function inside Doctrine
 *
 */
class DateFormatFunction extends FunctionNode
{

    /**
     * Holds the timestamp of the DATE_FORMAT DQL statement
     * @var $dateExpression
     */
    protected $dateExpression;

    /**
     * Holds the '% format' parameter of the DATE_FORMAT DQL statement
     * var String
     */
    protected $formatChar;

    public function getSql(SqlWalker $sqlWalker)
    {
        return 'DATE_FORMAT (' . $sqlWalker->walkArithmeticExpression($this->dateExpression) . ',' . $sqlWalker->walkStringPrimary($this->formatChar) . ')';
    }

    public function parse(Parser $parser)
    {
        $parser->Match(TokenType::T_IDENTIFIER);
        $parser->Match(TokenType::T_OPEN_PARENTHESIS);

        $this->dateExpression = $parser->ArithmeticExpression();
        $parser->Match(TokenType::T_COMMA);

        $this->formatChar = $parser->ArithmeticExpression();

        $parser->Match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
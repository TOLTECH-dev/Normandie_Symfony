<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [new TwigFunction('static_function', [$this, 'staticFunction'])];
    }

    /**
     * @param string $class
     * @param string $property
     *
     * @return object|null
     */
    public function staticFunction($class, $property)
    {
        if (property_exists($class, $property)) {
            return $class::$$property;
        }
        return null;
    }

}
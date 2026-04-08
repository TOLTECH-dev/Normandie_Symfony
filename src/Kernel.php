<?php

namespace App;

use App\Legacy\EntityAliases;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();
        // Register legacy entity aliases for backward compatibility
        EntityAliases::registerAliases();
    }
}
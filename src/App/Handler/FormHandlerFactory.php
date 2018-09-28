<?php

declare(strict_types=1);

namespace App\Handler;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class FormHandlerFactory
{
    public function __invoke(ContainerInterface $container) : FormHandler
    {
        return new FormHandler($container->get(TemplateRendererInterface::class));
    }
}

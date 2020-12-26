<?php
declare(strict_types=1);

namespace Sortable;

use Cake\Core\BasePlugin;

class Plugin extends BasePlugin
{
    /**
     * Plugin name
     *
     * @var string
     */
    protected $name = 'Sortable';

    /**
     * Do bootstrapping or not
     *
     * @var bool
     */
    protected $bootstrapEnabled = false;

    /**
     * Load routes or not
     *
     * @var bool
     */
    protected $routesEnabled = false;
}

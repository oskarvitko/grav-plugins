<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Page\Page;
use Grav\Common\Plugin;

/**
 * Class DebugAdminModalPlugin
 * @package Grav\Plugin
 */
class DebugAdminModalPlugin extends Plugin
{
    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAssetsInitialized' => ['onAssetsInitialized', 0],
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     * Composer autoload
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }


    public function onAssetsInitialized(): void
    {
        $this->grav['assets']->addJs('plugin://debug-admin-modal/js/admin.js');
        $this->grav['assets']->addCss('plugin://debug-admin-modal/css/admin.css');
    }

    public function onPluginsInitialized(): void
    {
        $grav = $this->grav;
        if (!method_exists($grav, 'showDebugModal')) {
            $grav->showDebugModal = function ($obj) use ($grav) {
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
                $caller = $trace[1];

                $file = isset($caller['file']) ? basename($caller['file']) : 'unknown file';
                $line = $caller['line'] ?? 'unknown line';
                $source = $file . ':' . $line;
                $data = $obj;
                $script = 'if (window.showDebugModal) {' .
                    'window.showDebugModal(' . json_encode($data) . ', "' . $source . '")' .
                    '} else {' .
                    'document.addEventListener("debug-admin-modal", function() { window.showDebugModal(' . json_encode($data) . ', "' . $source . '")})' .
                    '}';

                $grav['assets']->addInlineJs($script);
            };
        }
    }
}

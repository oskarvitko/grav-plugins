<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;

/**
 * Class SiteDataPlugin
 * @package Grav\Plugin
 */
class SiteDataPlugin extends Plugin
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
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onTwigInitialized' => ['onTwigInitialized', 0],
            'onAdminMenu' => ['onAdminMenu', 0],
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

    public function onAdminMenu(): void
    {
        $this->grav['twig']->plugins_hooked_nav['Данные Сайта'] = [
            'location' => '/plugins/site-data',
            'route' => '/plugins/site-data',
            'index' => 2,
            'icon' => 'fa-gear',
        ];
    }

    public function onTwigInitialized(): void
    {
        $this->grav['twig']->twig()->addFilter(
            new \Twig\TwigFilter('mask', function ($string, $mask) {
                $result = '';
                $strIndex = 0;

                for ($i = 0; $i < strlen($mask); $i++) {
                    if ($mask[$i] === '#') {
                        $result .= $strIndex < strlen($string) ? $string[$strIndex++] : '#';
                    } else {
                        $result .= $mask[$i];
                    }
                }

                return $result;
            })
        );
    }


    public function onTwigSiteVariables(): void
    {
        $data = $this->config->get('plugins.site-data.data');
        $this->grav['twig']->twig_vars['site_data'] = $data;
    }
}

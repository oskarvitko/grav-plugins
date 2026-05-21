<?php
namespace Grav\Theme;

use Grav\Common\Theme;
use RocketTheme\Toolbox\Event\Event;
use Twig\TwigFunction;

class Site extends Theme
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onTwigExtensions' => ['onTwigExtensions', 0],
        ];
    }

    public function onTwigExtensions(Event $event): void
    {
        $grav = $this->grav;
        $twig = $grav['twig']->twig();

        $twig->addFunction(new TwigFunction('canonical', static function (string $path) use ($grav): string {
            $base = rtrim($grav['base_url_relative'], '/');
            $baseAbsolute = rtrim($grav['base_url_absolute'], '/');
            $path = '/' . ltrim(str_replace($base, "", $path), '/');

            return $baseAbsolute . rtrim($path, '/') . '/';
        }));

        $twig->addFunction(new TwigFunction('page_url', static function (string $path) use ($grav): string {
            $path = '/' . ltrim($path, '/');
            $page = $grav['pages']->find($path);

            if (!$page) {
                $base = rtrim($grav['base_url_relative'], '/');

                return $base . '/404' . '/';
            }

            return $page->url();
        }));

        $twig->addFunction(new TwigFunction('asset_path', static function (string $path) use ($grav): string {
            $base = rtrim($grav['base_url_relative'], '/');
            $path = '/' . ltrim($path, '/');

            return $base . $path;
        }));
    }
}

<?php
namespace Grav\Theme;

use Grav\Common\Theme;
use RocketTheme\Toolbox\Event\Event;
use Twig\TwigFunction;

class Site extends Theme
{
    public static function getPartials(): array
    {
        $partialsDir = __DIR__ . '/templates/partials';
        $result = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($partialsDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $filename = $file->getFilename();
            if (!str_ends_with($filename, '.html.twig')) {
                continue;
            }

            $relativePath = 'partials/' . str_replace('\\', '/', substr($file->getPathname(), strlen($partialsDir) + 1));

            $inner = substr($relativePath, strlen('partials/'));
            $parts = explode('/', $inner);

            $nameParts = [];
            foreach ($parts as $index => $part) {
                if ($index === count($parts) - 1) {
                    $part = substr($part, 0, -strlen('.html.twig'));
                }
                $nameParts[] = ucwords(str_replace(['-', '_'], ' ', $part));
            }

            $result[$relativePath] = implode(' ', $nameParts);
        }

        return $result;
    }

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

<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use Monolog\Logger;

/**
 * Class TgFormPlugin
 * @package Grav\Plugin
 */
class TgFormPlugin extends Plugin
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
            'onPluginsInitialized' => [
                ['onPluginsInitialized', 0]
            ]
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

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0],
        ]);
    }

    protected function setRef($ref)
    {
        $days = 30;
        setcookie(
            'source_ref',
            $ref,
            [
                'expires' => time() + 86400 * $days,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'None',
            ]
        );
    }

    protected function getRef()
    {
        return $_COOKIE['source_ref'] ?? null;
    }

    public function onPageInitialized(Event $e)
    {
        $method = $this->grav['uri']->method();

        if ($method === 'GET') {
            $ref = $this->grav['uri']->query('ref');
            if ($ref && !$this->getRef()) {
                $this->setRef($ref);
            }
        }

        if ($method !== 'POST') {
            return;
        }

        $post = $this->grav['uri']->post();

        if (!isset($post['tg_form'])) {
            return;
        }

        $cache = $this->grav['cache'];
        $sessionId = $this->grav['session']->getId();
        $cacheKey = "tg-form-$sessionId";

        if ($cache->fetch($cacheKey)) {
            $text = $this->config->get('plugins.tg-form.cache_text');
            $this->grav['assets']->addInlineJs("alert('$text');");
            return;
        }

        $ref = $this->getRef();
        $title = $this->config->get('plugins.tg-form.title');
        $chatId = $this->config->get('plugins.tg-form.chat_id');
        $token = $this->config->get('plugins.tg-form.token');
        $aliases = $this->config->get('plugins.tg-form.aliases');
        $redirectPath = $this->config->get('plugins.tg-form.redirect');

        $ignore = ['tg_form', 'nonce'];
        $fields = [];

        foreach ($post as $key => $value) {
            if (in_array($key, $ignore))
                continue;


            $valueKey = isset($aliases[$key]) ? $aliases[$key] : $key;
            $valueKey = urldecode($valueKey);

            if (is_array($value)) {
                $value = join(', ', $value);
            }

            $value = urldecode($value);

            array_push($fields, "<b>$valueKey:</b> $value");
        }

        if (empty($fields)) {
            return;
        }

        if ($title) {
            array_unshift($fields, "<b>$title</b>");
        }

        if ($ref) {
            array_push($fields, "<b>Источник</b>: $ref");
        }

        $message = implode("\n", $fields);

        $url = "https://api.telegram.org/bot$token/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        $cacheTime = $this->config->get('plugins.tg-form.cache_time');
        $cache->save($cacheKey, true, $cacheTime * 60);

        $this->grav->redirect($redirectPath ?? $this->grav['uri']->path());
    }

    protected function debug($message)
    {
        /** @var Logger $logger */
        $logger = $this->grav['log'];
        $logger->addDebug($message, ['TgFormPlugin']);
    }
}

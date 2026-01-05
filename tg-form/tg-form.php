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

    public function onPageInitialized(Event $e)
    {
        $post = $this->grav['uri']->post();

        if (!isset($post['tg_form'])) {
            return;
        }


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
            array_push($fields, "<b>$valueKey:</b> $value");
        }

        if (empty($fields)) {
            return;
        }

        if ($title) {
            array_unshift($fields, "<b>$title</b>");
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

        $this->grav->redirect($redirectPath ?? $this->grav['uri']->path());
    }

    protected function debug($message)
    {
        /** @var Logger $logger */
        $logger = $this->grav['log'];
        $logger->addDebug($message, ['TgFormPlugin']);
    }
}

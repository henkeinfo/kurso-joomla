<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  plg_content_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Plugin\Content\Kurso\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Kurso\Component\Kurso\Administrator\Extension\KursoComponent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Replaces {kurso query="alias"} tags in content with the rendered KURSO query.
 *
 *     {kurso query="current-courses"}
 *     {kurso query="current-courses" class="my-class"}
 *
 * Works in articles, custom modules (with "Prepare Content" enabled) and everywhere else content plugins run.
 *
 * @since  1.0.0
 */
final class Kurso extends CMSPlugin implements SubscriberInterface
{
    /**
     * Matches a {kurso …} tag. Attributes may use straight, typographic or HTML-encoded quotes, as WYSIWYG
     * editors and copy & paste from office documents produce all of them.
     */
    private const TAG_PATTERN = '/\{kurso\s+([^{}]*?)\}/i';

    /**
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }

    /**
     * @param   ContentPrepareEvent  $event  The event
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        $item = $event->getItem();

        if (!\is_object($item) || !isset($item->text) || !\is_string($item->text) || stripos($item->text, '{kurso') === false) {
            return;
        }

        // Smart Search indexes the text without rendering external data.
        if ($event->getContext() === 'com_finder.indexer') {
            $item->text = preg_replace(self::TAG_PATTERN, '', $item->text) ?? $item->text;

            return;
        }

        if (!ComponentHelper::isEnabled('com_kurso')) {
            return;
        }

        /** @var KursoComponent $kurso */
        $kurso    = $this->getApplication()->bootComponent('com_kurso');
        $renderer = $kurso->getRenderer();

        $item->text = preg_replace_callback(
            self::TAG_PATTERN,
            static function (array $match) use ($renderer): string {
                $attributes = self::parseAttributes($match[1]);

                return $renderer->renderByAlias($attributes['query'] ?? '', $attributes['class'] ?? '');
            },
            $item->text
        ) ?? $item->text;
    }

    /**
     * Parses the attributes of a tag: query="alias" class="a b", also query=alias or a bare alias.
     *
     * @param   string  $source  The attribute part of the tag
     *
     * @return  array<string, string>
     *
     * @since   1.0.0
     */
    public static function parseAttributes(string $source): array
    {
        $source = html_entity_decode(strip_tags($source), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $source = str_replace(['“', '”', '„', '‟', '″', '«', '»', '‘', '’', '‚'], '"', $source);
        $source = str_replace("\u{00A0}", ' ', $source);

        $attributes = [];

        preg_match_all('/([a-z_]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+))/i', $source, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $attributes[strtolower($match[1])] = trim($match[2] !== '' ? $match[2] : (($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? '')));
        }

        // Short form {kurso current-courses}
        if (!isset($attributes['query']) && preg_match('/^\s*([a-z0-9_-]+)\s*$/i', $source, $bare)) {
            $attributes['query'] = $bare[1];
        }

        return $attributes;
    }
}

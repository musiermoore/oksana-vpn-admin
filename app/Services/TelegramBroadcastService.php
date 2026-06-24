<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramBroadcastService
{
    public function sanitizeMessage(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body) {
            return trim(strip_tags($html));
        }

        $message = $this->renderNodes($body->childNodes);
        $message = preg_replace("/\n{3,}/", "\n\n", $message) ?? $message;

        return trim($message);
    }

    public function send(Collection $users, string $messageHtml, ?UploadedFile $image = null, array $extra = []): array
    {
        $sent = 0;
        $skipped = 0;
        $failedUsers = [];
        $photo = $image
            ? InputFile::create($image->getRealPath(), $image->getClientOriginalName() ?: 'notification')
            : null;

        foreach ($users as $user) {
            if (empty($user->telegram_id)) {
                $skipped++;
                continue;
            }

            try {
                $this->sendToUser((string) $user->telegram_id, $messageHtml, $photo, $extra);
                $sent++;
            } catch (\Throwable $exception) {
                report($exception);
                $failedUsers[] = $user->telegram ?: "#{$user->id}";
            }
        }

        return [
            'total' => $users->count(),
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => count($failedUsers),
            'failed_users' => array_slice($failedUsers, 0, 5),
        ];
    }

    private function sendToUser(string $chatId, string $messageHtml, ?InputFile $photo, array $extra = []): void
    {
        if ($photo) {
            if ($messageHtml !== '' && $this->visibleLength($messageHtml) <= 1024) {
                Telegram::sendPhoto([
                    'chat_id' => $chatId,
                    'photo' => $photo,
                    'caption' => $messageHtml,
                    'parse_mode' => 'HTML',
                    ...$extra,
                ]);

                return;
            }

            Telegram::sendPhoto([
                'chat_id' => $chatId,
                'photo' => $photo,
                ...$extra,
            ]);
        }

        if ($messageHtml !== '') {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $messageHtml,
                'parse_mode' => 'HTML',
                ...$extra,
            ]);
        }
    }

    private function renderNodes(\DOMNodeList $nodes): string
    {
        $html = '';

        foreach ($nodes as $node) {
            $html .= $this->renderNode($node);
        }

        return $html;
    }

    private function renderNode(\DOMNode $node): string
    {
        if (in_array($node->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true)) {
            return e(str_replace("\xc2\xa0", ' ', $node->textContent));
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $name = strtolower($node->nodeName);

        if ($name === 'br') {
            return "\n";
        }

        if (in_array($name, ['p', 'div'], true)) {
            return trim($this->renderNodes($node->childNodes))."\n";
        }

        if ($name === 'code') {
            return '<code>'.e($node->textContent).'</code>';
        }

        if ($name === 'pre') {
            return '<pre>'.e($node->textContent).'</pre>';
        }

        $content = $this->renderNodes($node->childNodes);

        return match ($name) {
            'b', 'strong' => $this->wrapTag('b', $content),
            'i', 'em' => $this->wrapTag('i', $content),
            'u', 'ins' => $this->wrapTag('u', $content),
            's', 'strike', 'del' => $this->wrapTag('s', $content),
            'a' => $this->renderLink($node, $content),
            default => $content,
        };
    }

    private function renderLink(\DOMNode $node, string $content): string
    {
        if ($content === '' || ! $node instanceof \DOMElement) {
            return $content;
        }

        $href = $this->sanitizeHref($node->getAttribute('href'));

        if ($href === null) {
            return $content;
        }

        return '<a href="'.e($href).'">'.$content.'</a>';
    }

    private function sanitizeHref(string $href): ?string
    {
        $href = trim($href);

        if ($href === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https', 'mailto', 'tg'], true)) {
            return $href;
        }

        return null;
    }

    private function wrapTag(string $tag, string $content): string
    {
        if ($content === '') {
            return '';
        }

        return "<{$tag}>{$content}</{$tag}>";
    }

    private function visibleLength(string $messageHtml): int
    {
        return mb_strlen(html_entity_decode(strip_tags($messageHtml)));
    }
}

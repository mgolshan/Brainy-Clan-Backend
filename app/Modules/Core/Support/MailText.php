<?php

namespace App\Modules\Core\Support;

/**
 * Text that people typed (a support message, a staff reply, a sender's name), placed in a
 * Markdown mail (README "Emails") so that it can never become Markdown or HTML: every character is
 * HTML-escaped and line breaks become `<br>`, all on **one line**, so the result is one raw HTML
 * block for CommonMark (an HTML block ends at a blank line — a multi-line value would let the rest
 * be parsed as Markdown). Used by the `user-text` and `details` mail components
 * (resources/views/vendor/mail/{html,text}); the text versions print the plain text.
 */
final class MailText
{
    /**
     * Escaped HTML on one line, line breaks as `<br>`.
     */
    public static function html(?string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);

        return str_replace("\n", '<br>', e($text, false));
    }
}

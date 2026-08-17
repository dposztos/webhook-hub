<?php

namespace App\Services\Actions;

use App\Models\RuleAction;
use App\Services\Templating\TemplateException;
use App\Services\Templating\TemplateRenderer;
use Illuminate\Mail\Message as MailMessage;
use Illuminate\Support\Facades\Mail;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * E-mail küldése a beérkezett üzenet adataiból, sablonozott tárggyal és HTML-testtel.
 */
class EmailAction implements ActionContract
{
    public function __construct(private readonly TemplateRenderer $renderer) {}

    public function execute(RuleAction $action, array $context, bool $dryRun = false): ActionResult
    {
        $config = $action->config ?? [];

        try {
            $to = $this->addresses((string) ($config['to'] ?? ''), $context);
            $cc = $this->addresses((string) ($config['cc'] ?? ''), $context);
            $bcc = $this->addresses((string) ($config['bcc'] ?? ''), $context);
            $replyTo = $this->addresses((string) ($config['reply_to'] ?? ''), $context);
            $subject = trim($this->renderer->renderText((string) ($config['subject'] ?? ''), $context));
            $html = $this->renderer->renderHtml((string) ($config['body_html'] ?? ''), $context);
        } catch (TemplateException $e) {
            return ActionResult::failed($e->getMessage());
        }

        if (! $to) {
            return ActionResult::failed('Nincs érvényes címzett (a "to" sablon üresre vagy hibás címre értékelődött).');
        }

        if ($subject === '') {
            $subject = 'Webhook értesítés';
        }

        if (($config['inline_css'] ?? true) && str_contains($html, '<style')) {
            $html = (new CssToInlineStyles)->convert($html);
        }

        $blocked = $this->blockedRecipients(array_merge($to, $cc, $bcc));

        if ($blocked) {
            return ActionResult::failed(
                'A címzett nincs engedélyezve (WEBHOOK_ALLOWED_RECIPIENTS): '.implode(', ', $blocked)
            );
        }

        $detail = [
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
            'subject' => $subject,
            'html' => $html,
        ];

        if ($dryRun) {
            return ActionResult::skipped('Próbafuttatás – nem ment ki levél', $detail);
        }

        $attachJson = (bool) ($config['attach_json'] ?? false);
        $fromName = trim((string) ($config['from_name'] ?? '')) ?: null;

        Mail::html($html, function (MailMessage $mail) use ($to, $cc, $bcc, $replyTo, $subject, $fromName, $attachJson, $context) {
            $mail->to($to)->subject($subject);

            if ($cc) {
                $mail->cc($cc);
            }

            if ($bcc) {
                $mail->bcc($bcc);
            }

            if ($replyTo) {
                $mail->replyTo($replyTo);
            }

            if ($fromName) {
                $mail->from(config('mail.from.address'), $fromName);
            }

            if ($attachJson) {
                $payload = json_encode(
                    $context['json'] ?? $context['body'] ?? [],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );

                $mail->attachData((string) $payload, 'payload.json', ['mime' => 'application/json']);
            }
        });

        return ActionResult::success('Elküldve: '.implode(', ', $to), $detail);
    }

    /**
     * Sablonozott címzett-lista feloldása és szűrése érvényes e-mail címekre.
     *
     * @param array<string, mixed> $context
     * @return array<int, string>
     */
    private function addresses(string $template, array $context): array
    {
        $rendered = $this->renderer->renderText($template, $context);

        $candidates = preg_split('/[,;\s]+/', $rendered, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            array_map('trim', $candidates),
            fn (string $address) => (bool) filter_var($address, FILTER_VALIDATE_EMAIL)
        )));
    }

    /**
     * @param array<int, string> $recipients
     * @return array<int, string>
     */
    private function blockedRecipients(array $recipients): array
    {
        $patterns = config('webhookhub.mail.allowed_recipients', []);

        if (! $patterns) {
            return [];
        }

        $blocked = [];

        foreach ($recipients as $recipient) {
            $allowed = false;

            foreach ($patterns as $pattern) {
                if (fnmatch(trim($pattern), $recipient, FNM_CASEFOLD)) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                $blocked[] = $recipient;
            }
        }

        return $blocked;
    }
}

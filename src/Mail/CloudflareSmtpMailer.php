<?php

declare(strict_types=1);

namespace App\Mail;

use CurlHandle;

final class CloudflareSmtpMailer implements Mailer
{
    /** @var (callable(string,string,string):string)|null */
    private $submit;

    /** @param (callable(string,string,string):string)|null $submit */
    public function __construct(
        private string $apiToken,
        private string $fromEmail,
        private string $fromName = '',
        private string $host = 'smtp.mx.cloudflare.net',
        private int $port = 465,
        private int $timeoutSeconds = 30,
        ?callable $submit = null,
    ) {
        $this->submit = $submit;
    }

    public function isConfigured(): bool
    {
        return trim($this->apiToken) !== ''
            && filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): string
    {
        if (!$this->isConfigured()) {
            throw new MailException('Cloudflare SMTP is not configured.');
        }
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $to)) {
            throw new MailException('Recipient email address is invalid.');
        }
        if (preg_match('/[\r\n]/', $subject)) {
            throw new MailException('Email subject contains an invalid line break.');
        }

        $messageId = '<' . bin2hex(random_bytes(12)) . '@' . $this->domain() . '>';
        $message = $this->message($to, $subject, $textBody, $htmlBody, $messageId);
        $response = $this->submit !== null
            ? ($this->submit)($this->fromEmail, $to, $message)
            : $this->submitWithCurl($to, $message);

        if (preg_match_all('/<[^<>\r\n]+@[^<>\r\n]+>/', $response, $matches) > 0) {
            $providerId = end($matches[0]);
            if (is_string($providerId) && $providerId !== '') {
                return $providerId;
            }
        }
        return $messageId;
    }

    private function submitWithCurl(string $to, string $message): string
    {
        $curl = curl_init();
        if (!$curl instanceof CurlHandle) {
            throw new MailException('Unable to initialize the SMTP transport.');
        }

        $offset = 0;
        $serverReplies = '';
        $options = [
            CURLOPT_URL => sprintf('smtps://%s:%d', $this->host, $this->port),
            CURLOPT_USERNAME => 'api_token',
            CURLOPT_PASSWORD => $this->apiToken,
            CURLOPT_MAIL_FROM => $this->fromEmail,
            CURLOPT_MAIL_RCPT => [$to],
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILESIZE => strlen($message),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => max(1, min(30, $this->timeoutSeconds)),
            CURLOPT_TIMEOUT => max(1, $this->timeoutSeconds),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_SMTPS,
            CURLOPT_VERBOSE => true,
            CURLOPT_READFUNCTION => static function (
                CurlHandle $handle,
                mixed $stream,
                int $length,
            ) use (&$offset, $message): string {
                $chunk = substr($message, $offset, $length);
                $offset += strlen($chunk);
                return $chunk;
            },
            CURLOPT_DEBUGFUNCTION => static function (
                CurlHandle $handle,
                int $type,
                string $data,
            ) use (&$serverReplies): int {
                if ($type === CURLINFO_HEADER_IN) {
                    $serverReplies .= $data;
                }
                return 0;
            },
        ];

        if (!curl_setopt_array($curl, $options)) {
            curl_close($curl);
            throw new MailException('Unable to configure the SMTP transport.');
        }

        $result = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($result === false) {
            throw new MailException('Cloudflare SMTP delivery failed: ' . ($error !== '' ? $error : 'unknown transport error'));
        }
        return $serverReplies;
    }

    private function message(
        string $to,
        string $subject,
        string $textBody,
        ?string $htmlBody,
        string $messageId,
    ): string {
        $boundary = 'rb-' . bin2hex(random_bytes(8));
        $fromName = $this->encodeName($this->fromName);
        $from = $fromName !== '' ? sprintf('%s <%s>', $fromName, $this->fromEmail) : $this->fromEmail;
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s \G\M\T'),
            'From: ' . $from,
            'To: ' . $to,
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Message-ID: ' . $messageId,
            'MIME-Version: 1.0',
        ];

        if ($htmlBody !== null && $htmlBody !== '') {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body = "--$boundary\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $this->normalizeBody($textBody) . "\r\n"
                . "--$boundary\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $this->normalizeBody($htmlBody) . "\r\n"
                . "--$boundary--\r\n";
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $body = $this->normalizeBody($textBody) . "\r\n";
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function normalizeBody(string $body): string
    {
        return str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $body));
    }

    private function encodeName(string $name): string
    {
        $name = trim((string) preg_replace('/[\r\n]+/', ' ', $name));
        if ($name === '') {
            return '';
        }
        if (preg_match('/[^\x20-\x7e]/', $name) || preg_match('/[(),:;<>@\\\\".\[\]]/', $name)) {
            return '=?UTF-8?B?' . base64_encode($name) . '?=';
        }
        return $name;
    }

    private function domain(): string
    {
        $at = strrpos($this->fromEmail, '@');
        return $at === false ? 'localhost' : substr($this->fromEmail, $at + 1);
    }
}

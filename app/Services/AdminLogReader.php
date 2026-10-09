<?php

namespace App\Services;

class AdminLogReader
{
    public function files(): array
    {
        $files = [];
        foreach (glob(storage_path('logs/*.log')) ?: [] as $path) {
            if (!is_link($path) && is_file($path) && preg_match('/^[A-Za-z0-9._-]+\.log$/', basename($path))) $files[] = basename($path);
        }
        rsort($files);
        return array_slice($files, 0, 60);
    }

    public function read(string $file, string $level = 'all'): string
    {
        abort_unless(in_array($file, $this->files(), true), 404);
        $path = storage_path('logs/'.$file);
        abort_unless(!is_link($path) && dirname(realpath($path) ?: '') === realpath(storage_path('logs')), 404);
        $handle = fopen($path, 'rb');
        if (!$handle) return 'Journal momentanément indisponible.';
        try {
            $size = fstat($handle)['size'];
            $offset = max(0, $size - 262144);
            fseek($handle, $offset);
            if ($offset > 0) fgets($handle); // Drop a potentially truncated first line.
            $text = stream_get_contents($handle, 262144);
        } finally { fclose($handle); }
        $entries = preg_split('/(?=^\[\d{4}-\d{2}-\d{2}[^\n]*\] [^\n]*\.(?:DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):)/m', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($level !== 'all') $entries = array_values(array_filter($entries, fn ($entry) => preg_match('/^\[[^\n]*\] [^\n]*\.'.strtoupper($level).':/', $entry)));
        $text = implode('', array_slice($entries, -100));
        $text = implode("\n", array_slice(explode("\n", $text), -500));
        foreach (['app.key', 'services.tmdb.key', 'services.streaming_availability.key', 'mail.mailers.smtp.password'] as $key) {
            $secret = config($key);
            if (is_string($secret) && strlen($secret) >= 8) $text = str_replace($secret, '[masqué]', $text);
        }
        $text = preg_replace('~-----BEGIN [^-]*PRIVATE KEY-----.*?-----END [^-]*PRIVATE KEY-----~s', '[clé privée masquée]', $text);
        $text = preg_replace('~(Bearer\s+)[A-Za-z0-9._\~+/-]+=*~i', '$1[masqué]', $text);
        $text = preg_replace('~((?:api[-_]?key|password|passwd|secret|access_token|refresh_token|authorization|cookie|set-cookie|remember_token|contact_token|vapid_private_key)\s*["\']?\s*[:=]\s*)(["\'])(.*?)\2~i', '$1"[masqué]"', $text);
        $text = preg_replace('~((?:Authorization|Cookie|Set-Cookie)\s*:\s*)[^\r\n]+~i', '$1[masqué]', $text);
        $text = preg_replace('~((?:api[-_]?key|password|passwd|secret|access_token|refresh_token|authorization|cookie|set-cookie|remember_token|contact_token|vapid_private_key)\s*["\']?\s*[:=]\s*["\']?)[^\s,"\'&}]+~i', '$1[masqué]', $text);
        return $text === '' ? 'Aucune entrée dans cette sélection.' : $text;
    }
}

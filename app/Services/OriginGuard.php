<?php

namespace App\Services;

use RuntimeException;

/** Private, host-side configuration, deliberately outside the release archive. */
class OriginGuard
{
    private const MARKER = '# VOD Finder origin guard v1';
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? base_path(), '/');
    }

    public function initialize(): void
    {
        $this->locked(function () {
            if (is_file($this->statePath())) { $this->read(); return; }
            if (file_exists($this->apachePath()) || is_link($this->apachePath())) {
                throw new RuntimeException('Un .htaccess privé existe déjà : vérifier sa configuration avant installation.');
            }
            $this->write($this->statePath(), json_encode([
                'token' => bin2hex(random_bytes(32)), 'mode' => 'off', 'apache_hash' => null,
                'allow_acme_files' => false, 'created_at' => gmdate(DATE_ATOM),
            ], JSON_THROW_ON_ERROR), 0600);
        });
    }

    public function token(): string
    {
        return $this->read()['token'];
    }

    public function status(): array
    {
        $state = $this->read();
        return [
            'mode' => $state['mode'], 'allow_acme_files' => $state['allow_acme_files'],
            'apache_file' => $this->apachePath(),
            'apache_consistent' => $this->consistent($state),
        ];
    }

    public function setMode(string $mode, bool $allowAcmeFiles = false): void
    {
        if (!in_array($mode, ['off', 'observe', 'enforce'], true)) throw new RuntimeException('Mode invalide.');
        $this->locked(function () use ($mode, $allowAcmeFiles) {
            $state = $this->read();
            $alreadyRemoved = $mode === 'off' && !file_exists($this->apachePath()) && !is_link($this->apachePath());
            if (!$alreadyRemoved && !$this->consistent($state)) throw new RuntimeException('Le .htaccess privé a été modifié ou remplacé. Aucun fichier écrasé.');
            if ($mode === 'enforce' && $state['mode'] === 'off') throw new RuntimeException('Installer et vérifier le mode observe avant enforce.');
            if ($mode === 'off') {
                if (is_file($this->apachePath()) && !unlink($this->apachePath())) throw new RuntimeException('Impossible de retirer le .htaccess privé.');
                $state['apache_hash'] = null;
            } else {
                $content = $this->render($state['token'], $mode, $allowAcmeFiles);
                // This file contains only a lookup digest, never the bearer secret.
                $this->write($this->apachePath(), $content, 0644);
                $state['apache_hash'] = hash('sha256', $content);
            }
            $state['mode'] = $mode;
            $state['allow_acme_files'] = $mode !== 'off' && $allowAcmeFiles;
            $this->write($this->statePath(), json_encode($state, JSON_THROW_ON_ERROR), 0600);
        });
    }

    public function statePath(): string { return $this->root.'/storage/app/private/origin-guard.json'; }
    public function apachePath(): string { return $this->root.'/.htaccess'; }

    private function read(): array
    {
        if (is_link($this->statePath()) || !is_file($this->statePath())) throw new RuntimeException('Configuration privée absente : exécuter origin:guard init.');
        $state = json_decode(file_get_contents($this->statePath()), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($state) || !is_string($state['token'] ?? null) || !preg_match('/\A[a-f0-9]{64}\z/', $state['token'])
            || !in_array($state['mode'] ?? null, ['off', 'observe', 'enforce'], true)
            || !is_bool($state['allow_acme_files'] ?? null) || !array_key_exists('apache_hash', $state)) {
            throw new RuntimeException('Configuration privée invalide. Restaurer sa sauvegarde ; ne pas générer une autre clé.');
        }
        return $state;
    }

    private function consistent(array $state): bool
    {
        if (is_link($this->apachePath())) return false;
        if ($state['mode'] === 'off') return !file_exists($this->apachePath());
        return is_file($this->apachePath()) && is_string($state['apache_hash'])
            && hash_equals($state['apache_hash'], hash_file('sha256', $this->apachePath()))
            && str_starts_with(file_get_contents($this->apachePath()), self::MARKER."\n");
    }

    private function locked(callable $work): void
    {
        $dir = dirname($this->statePath());
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Stockage privé indisponible.');
        foreach ([$this->root, $this->root.'/storage', $this->root.'/storage/app', $dir] as $path) {
            if (is_link($path)) throw new RuntimeException('Chemin privé symbolique refusé.');
        }
        $path = $dir.'/origin-guard.lock';
        if (is_link($path)) throw new RuntimeException('Verrou symbolique refusé.');
        $lock = fopen($path, 'c');
        if (!$lock) throw new RuntimeException('Verrou privé indisponible.');
        chmod($path, 0600);
        try {
            if (!flock($lock, LOCK_EX)) throw new RuntimeException('Impossible de verrouiller la configuration.');
            $work();
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function write(string $path, string $content, int $permissions): void
    {
        if (is_link($path)) throw new RuntimeException('Destination symbolique refusée.');
        $temporary = tempnam(dirname($path), '.origin-guard-');
        if ($temporary === false) throw new RuntimeException('Fichier temporaire indisponible.');
        try {
            if (!chmod($temporary, $permissions) || file_put_contents($temporary, $content) !== strlen($content)
                || !rename($temporary, $path)) throw new RuntimeException('Écriture privée impossible.');
        } finally { if (is_file($temporary)) unlink($temporary); }
    }

    private function render(string $token, string $mode, bool $allowAcmeFiles): string
    {
        // ap_expr offers sha1 but not sha256. This is a lookup digest of a random
        // 256-bit bearer key (160-bit preimage resistance), not password hashing
        // or a signature. Known SHA-1 collision attacks do not reveal this key.
        $digest = sha1($token);
        $match = "sha1(req('X-VOD-Origin-Key')) == '$digest'";
        $miss = "sha1(req('X-VOD-Origin-Key')) != '$digest'";
        $rules = self::MARKER."\n# Generated outside public and Git; do not edit manually.\nRewriteEngine On\nRewriteOptions InheritDownBefore\n";
        if ($mode === 'enforce') {
            if ($allowAcmeFiles) {
                $rules .= "RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$\nRewriteCond %{REQUEST_URI} ^/\\.well-known/acme-challenge/[A-Za-z0-9_-]{20,256}$\nRewriteCond %{REQUEST_FILENAME} -f\nRewriteRule ^ - [E=VOD_ORIGIN_ACME:1]\n";
            }
            $rules .= "RewriteCond expr \"$miss\"\n";
            if ($allowAcmeFiles) $rules .= "RewriteCond %{ENV:VOD_ORIGIN_ACME} !=1\n";
            $rules .= "RewriteRule ^ - [F,END]\n";
        }
        $rules .= "<IfModule mod_headers.c>\nHeader always unset X-VOD-Origin-Key\nHeader unset X-VOD-Origin-Key\nHeader always set X-VOD-Origin-Guard \"$mode\"\nHeader always set X-VOD-Origin-Check \"match\" \"expr=$match\"\nHeader always set X-VOD-Origin-Check \"missing\" \"expr=$miss\"\n</IfModule>\n";
        return $rules;
    }
}

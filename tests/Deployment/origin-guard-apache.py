#!/usr/bin/env python3
"""Exercise the real private Apache rules against a disposable localhost server."""
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.request
import urllib.error

source = Path(__file__).resolve().parents[2]
apache = os.environ.get('VOD_APACHE_PATH') or shutil.which('apache2')
if not apache:
    raise SystemExit('Apache is required for this test (apache2-bin).')
modules = Path(os.environ.get('VOD_APACHE_MODULES', str(Path(apache).resolve().parents[1] / 'lib/apache2/modules')))
php = os.environ.get('VOD_PHP_PATH', 'php')
checks = 0
with tempfile.TemporaryDirectory(prefix='vod-origin-apache-') as directory:
    root = Path(directory)
    root.chmod(0o755)
    public = root / 'public'
    public.mkdir()
    (root / 'storage/app/private').mkdir(parents=True)
    (root / 'run').mkdir()
    (public / '.well-known/acme-challenge').mkdir(parents=True)
    challenge = 'test-challenge-' + 'a' * 24
    (public / '.well-known/acme-challenge' / challenge).write_text('acme-proof')
    (public / 'index.php').write_text('fixture-application')
    (public / 'favicon.ico').write_text('fixture-static')
    (public / '.htaccess').write_text((source / 'public/.htaccess').read_text())
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    configuration = f'''ServerRoot "{root}"
DefaultRuntimeDir "{root / 'run'}"
PidFile "{root / 'run/pid'}"
Listen 127.0.0.1:{port}
ServerName app-vod.venoix.fr
User #{os.getuid() if os.getuid() else 65534}
Group #{os.getgid() if os.getuid() else 65534}
ErrorLog "{root / 'error.log'}"
LogLevel warn
'''
    for module in ['mpm_event', 'authz_core', 'rewrite', 'headers']:
        configuration += f'LoadModule {module}_module "{modules / ("mod_" + module + ".so")}"\n'
    configuration += f'''DocumentRoot "{public}"
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "{root}">
    AllowOverride All
    Options FollowSymLinks
    Require all granted
</Directory>
# A more specific public directory grant must NOT bypass the private rule.
<Directory "{public}">
    AllowOverride All
    Require all granted
</Directory>
'''
    config = root / 'apache.conf'
    config.write_text(configuration)
    subprocess.run([apache, '-t', '-f', str(config)], check=True, capture_output=True)

    def mode(value, acme=False):
        subprocess.run([php, str(source / 'tests/Deployment/origin-guard-fixture.php'), str(root), value, 'acme' if acme else ''], check=True, capture_output=True)

    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *args, **kwargs):
            return None
    client = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect())
    def probe(path, status=200, key=None, method='GET', **headers):
        global checks
        headers.update({'Host': 'app-vod.venoix.fr', 'User-Agent': 'Mozilla/5.0'})
        if key is not None:
            headers['X-VOD-Origin-Key'] = key
        request = urllib.request.Request(f'http://127.0.0.1:{port}{path}', headers=headers, method=method)
        try:
            response = client.open(request, timeout=5)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            body = response.read()
            assert response.status == status, (path, response.status, status)
            assert response.headers.get('X-VOD-Origin-Key') is None
            if key is not None:
                assert key.encode() not in body
            checks += 1
            return response.headers

    mode('observe')
    key = json.loads((root / 'storage/app/private/origin-guard.json').read_text())['token']
    process = subprocess.Popen([apache, '-X', '-f', str(config)], stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
    try:
        for _ in range(100):
            if process.poll() is not None:
                raise RuntimeError(process.stderr.read().decode())
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1):
                    break
            except OSError:
                time.sleep(.05)
        else:
            raise RuntimeError('Apache did not start')
        for path in ['/about', '/favicon.ico']:
            assert probe(path).get('X-VOD-Origin-Check') == 'missing'
            assert probe(path, key=key).get('X-VOD-Origin-Check') == 'match'
        mode('enforce')
        for path in ['/about', '/favicon.ico', '/service-worker.js', '/api/internal/notifications/status', '/login']:
            probe(path, status=403)
            probe(path, status=403, key='b' * 64)
            assert probe(path, key=key).get('X-VOD-Origin-Check') == 'match'
        probe('/about', status=403, **{'CF-Ray': 'forged', 'CF-Connecting-IP': '173.245.48.1', 'X-Forwarded-For': '173.245.48.1'})
        probe('/.well-known/acme-challenge/' + challenge, status=403)
        mode('enforce', acme=True)
        probe('/.well-known/acme-challenge/' + challenge)
        probe('/.well-known/acme-challenge/' + challenge, method='HEAD')
        probe('/.well-known/acme-challenge/' + challenge, status=403, method='POST')
        probe('/.well-known/acme-challenge/' + 'b' * 30, status=403)
        probe('/.well-known/other', status=403)
        probe('/favicon.ico', status=403)
        mode('off')
        probe('/about')
        probe('/favicon.ico')
        print(f'PASS: {checks} Apache requests; observation, inherited static/dynamic protection, forged headers, narrow ACME and rollback.')
    finally:
        process.terminate()
        process.wait(timeout=10)

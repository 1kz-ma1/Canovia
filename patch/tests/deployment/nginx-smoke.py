"""Linux routing smoke test with real Nginx and a mock FastCGI responder.

NGINX_BIN and NGINX_CONFIG_DIR may point to an unpacked distribution package.
No root, production database, or system configuration changes are needed.
"""
import http.client
import json
import os
from pathlib import Path
import socket
import socketserver
import struct
import subprocess
import tempfile
import threading
import time


def record(kind, request_id, content):
    return struct.pack('!BBHHBB', 1, kind, request_id, len(content), 0, 0) + content


def read_exact(stream, length):
    result = stream.read(length)
    if len(result) != length:
        raise EOFError()
    return result


class FastCGI(socketserver.StreamRequestHandler):
    def handle(self):
        params = b''
        while True:
            _, kind, request_id, size, padding, _ = struct.unpack('!BBHHBB', read_exact(self.rfile, 8))
            content = read_exact(self.rfile, size)
            read_exact(self.rfile, padding)
            if kind == 4:
                params += content
            if kind == 5 and not content:
                break
        values = {}
        offset = 0

        def length():
            nonlocal offset
            value = params[offset]
            if value & 128:
                value = struct.unpack('!I', params[offset:offset + 4])[0] & 0x7fffffff
                offset += 4
            else:
                offset += 1
            return value

        while offset < len(params):
            key_size, value_size = length(), length()
            key = params[offset:offset + key_size].decode()
            offset += key_size
            values[key] = params[offset:offset + value_size].decode()
            offset += value_size
        response = b'Content-Type: application/json\r\nCache-Control: private, no-store\r\n\r\n' + json.dumps(values).encode()
        self.wfile.write(record(6, request_id, response) + record(6, request_id, b'') + record(3, request_id, b'\0' * 8))


root = Path(__file__).resolve().parents[2]
binary = os.environ.get('NGINX_BIN', 'nginx')
include = Path(os.environ.get('NGINX_CONFIG_DIR', '/etc/nginx')).resolve()
with tempfile.TemporaryDirectory(prefix='canovia-nginx-') as directory, socketserver.TCPServer(('127.0.0.1', 0), FastCGI) as backend:
    tmp = Path(directory)
    public = tmp / 'public'
    (public / 'build').mkdir(parents=True)
    (public / 'build' / 'app-123.js').write_text('console.log("fixture");')
    (public / 'sw.js').write_text('// fixture')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    config = (root / 'docker/nginx.conf').read_text()
    config = config.replace('user www-data;', '')
    config = config.replace('/tmp/nginx.pid', str(tmp / 'nginx.pid'))
    config = config.replace('/dev/stderr', str(tmp / 'error.log')).replace('/dev/stdout', str(tmp / 'access.log'))
    config = config.replace('/etc/nginx/', str(include) + '/')
    config = config.replace('/var/www/html/public', str(public))
    config = config.replace('listen 10000 ', f'listen 127.0.0.1:{port} ')
    config = config.replace('listen [::]:10000 default_server;', '')
    config = config.replace('127.0.0.1:9000', f'127.0.0.1:{backend.server_address[1]}')
    config = config.replace('http {', 'http {\n' + '\n'.join(
        f'{name}_temp_path "{tmp / name}";' for name in ['client_body', 'proxy', 'fastcgi', 'uwsgi', 'scgi']))
    config_file = tmp / 'nginx.conf'
    config_file.write_text(config)
    subprocess.run([binary, '-t', '-p', str(tmp), '-c', str(config_file)], check=True)
    threading.Thread(target=backend.serve_forever, daemon=True).start()
    process = subprocess.Popen([binary, '-p', str(tmp), '-c', str(config_file), '-g', 'daemon off;'], stdout=subprocess.DEVNULL)
    try:
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1):
                    break
            except OSError:
                time.sleep(.05)
        def get(path):
            conn = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
            conn.request('GET', path)
            response = conn.getresponse()
            result = response.status, dict(response.getheaders()), response.read()
            conn.close()
            return result

        status, headers, _ = get('/health')
        assert status == 204 and headers['X-Canovia-Ready'] == headers['X-PaceKeeper-Ready'] == '1'
        status, headers, body = get('/app.webmanifest?installation=1')
        params = json.loads(body)
        assert status == 200 and 'no-store' in headers['Cache-Control']
        assert params['REQUEST_URI'] == '/app.webmanifest?installation=1'
        assert params['SCRIPT_FILENAME'] == str(public / 'index.php')
        assert get('/build/app-123.js')[0] == 200
        assert 'immutable' in get('/build/app-123.js')[1]['Cache-Control']
        status, headers, _ = get('/build/missing.js')
        assert status == 404 and 'immutable' not in headers.get('Cache-Control', '')
        assert 'no-store' in get('/sw.js')[1]['Cache-Control']
        assert get('/unexpected.php')[0] == 404
        assert get('/.env')[0] == 403
        print('PASS: readiness, dynamic manifest FastCGI routing, asset caching, missing assets, SW freshness, PHP and dotfile protection')
    finally:
        process.terminate()
        process.wait(timeout=10)
        backend.shutdown()

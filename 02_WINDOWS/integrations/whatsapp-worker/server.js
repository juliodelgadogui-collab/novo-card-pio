import http from 'node:http';
import path from 'node:path';
import { lookup } from 'node:dns/promises';
import { isIP } from 'node:net';
import fs from 'node:fs/promises';
import pino from 'pino';
import makeWASocket, { Browsers, DisconnectReason, useMultiFileAuthState } from '@whiskeysockets/baileys';

const HOST = process.env.HOST || '127.0.0.1';
const PORT = Number(process.env.PORT || 21467);
const SECRET = String(process.env.EVENTMENU_WHATSAPP_BRIDGE_SECRET || '');
const SESSION_ROOT = process.env.WHATSAPP_SESSION_ROOT || path.join(process.cwd(), 'sessions');
const logger = pino({ level: process.env.LOG_LEVEL || 'warn' });
const sessions = new Map();

if (!SECRET || SECRET.length < 16) {
  console.error('EVENTMENU_WHATSAPP_BRIDGE_SECRET ausente ou inseguro.');
  process.exit(2);
}

function now() { return new Date().toISOString(); }
function safeKey(value) {
  const key = String(value || '').trim();
  if (!/^[A-Za-z0-9._-]{8,160}$/.test(key)) throw new Error('Sessão inválida.');
  return key;
}
function normalizePhone(value) {
  const digits = String(value || '').replace(/\D+/g, '');
  if (digits.length < 10 || digits.length > 15) throw new Error('Telefone inválido.');
  return digits;
}
function publicState(s) {
  return {
    ok: true,
    status: s?.status || 'disconnected',
    qr: s?.qr || null,
    pairing_code: s?.pairingCode || null,
    pairing_phone: s?.pairingPhone || '',
    phone: s?.phone || '',
    error: s?.error || null,
    updated_at: s?.updatedAt || now()
  };
}
async function readJson(req) {
  let raw = '';
  for await (const chunk of req) {
    raw += chunk;
    if (raw.length > 65536) throw new Error('Corpo da requisição muito grande.');
  }
  if (!raw) return {};
  try { return JSON.parse(raw); } catch { throw new Error('JSON inválido.'); }
}
function send(res, status, data) {
  const body = JSON.stringify(data);
  res.writeHead(status, {
    'content-type': 'application/json; charset=utf-8',
    'content-length': Buffer.byteLength(body),
    'cache-control': 'no-store'
  });
  res.end(body);
}
function authenticated(req) {
  const auth = String(req.headers.authorization || '');
  return auth.startsWith('Bearer ') && auth.slice(7) === SECRET;
}

function isPublicAddress(address) {
  const version = isIP(address);
  if (version === 4) {
    const p = address.split('.').map(Number);
    const [a, b, c] = p;
    if (a === 0 || a === 10 || a === 127 || a >= 224) return false;
    if (a === 100 && b >= 64 && b <= 127) return false;
    if (a === 169 && b === 254) return false;
    if (a === 172 && b >= 16 && b <= 31) return false;
    if (a === 192 && (b === 168 || (b === 0 && c === 0) || (b === 0 && c === 2))) return false;
    if (a === 198 && (b === 18 || b === 19 || b === 51 && c === 100)) return false;
    if (a === 203 && b === 0 && c === 113) return false;
    if (a === 255) return false;
    return true;
  }
  if (version === 6) {
    const v = address.toLowerCase();
    return /^[23]/.test(v) && !v.startsWith('2001:db8:');
  }
  return false;
}

async function validateMediaUrl(raw) {
  let url;
  try { url = new URL(String(raw || '')); } catch { throw new Error('URL de mídia inválida.'); }
  if (url.protocol !== 'https:' || url.username || url.password) throw new Error('A mídia precisa usar uma URL HTTPS pública.');
  const host = url.hostname.toLowerCase();
  if (!host || host === 'localhost' || host.endsWith('.localhost') || host.endsWith('.local') || host.endsWith('.internal')) throw new Error('Host de mídia não permitido.');
  if (isIP(host)) throw new Error('A URL de mídia precisa usar um domínio público.');
  let addresses;
  try { addresses = await lookup(host, { all: true, verbatim: true }); } catch { throw new Error('Não foi possível localizar o servidor da mídia.'); }
  if (!addresses.length || addresses.some(x => !isPublicAddress(x.address))) throw new Error('O servidor da mídia não é público.');
  return url;
}

async function downloadMedia(rawUrl, declaredMime, mediaType) {
  const maxBytes = mediaType === 'document' ? 16 * 1024 * 1024 : 8 * 1024 * 1024;
  let url = await validateMediaUrl(rawUrl);
  let response;
  for (let redirects = 0; redirects <= 3; redirects++) {
    response = await fetch(url, { redirect: 'manual', signal: AbortSignal.timeout(15000), headers: { 'User-Agent': 'EventMenu-WhatsApp-Connect/1.0' } });
    if (![301, 302, 303, 307, 308].includes(response.status)) break;
    const location = response.headers.get('location');
    if (!location || redirects === 3) throw new Error('Redirecionamento da mídia inválido.');
    url = await validateMediaUrl(new URL(location, url).toString());
  }
  if (!response || !response.ok) throw new Error(`Falha ao baixar a mídia (HTTP ${response?.status || 0}).`);
  const length = Number(response.headers.get('content-length') || 0);
  if (length > maxBytes) throw new Error('O arquivo de mídia excede o limite permitido.');
  const reader = response.body?.getReader();
  if (!reader) throw new Error('O servidor não retornou o arquivo de mídia.');
  const chunks = []; let total = 0;
  try {
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      total += value.byteLength;
      if (total > maxBytes) { await reader.cancel(); throw new Error('O arquivo de mídia excede o limite permitido.'); }
      chunks.push(Buffer.from(value));
    }
  } finally { try { reader.releaseLock(); } catch {} }
  const buffer = Buffer.concat(chunks, total);
  const responseMime = String(response.headers.get('content-type') || '').split(';')[0].trim().toLowerCase();
  const suppliedMime = String(declaredMime || '').split(';')[0].trim().toLowerCase();
  if (mediaType === 'image') {
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    const mime = allowed.includes(responseMime) ? responseMime : suppliedMime;
    if (!allowed.includes(mime)) throw new Error('O arquivo recebido não é uma imagem JPG, PNG ou WebP válida.');
    return { buffer, mime };
  }
  const mime = responseMime === 'application/pdf' ? responseMime : suppliedMime;
  if (mime !== 'application/pdf') throw new Error('O documento enviado precisa ser um PDF válido.');
  return { buffer, mime };
}

async function ensureSocket(sessionKey) {
  sessionKey = safeKey(sessionKey);
  const current = sessions.get(sessionKey);
  if (current?.socket && current.status !== 'logged_out') return current;

  const dir = path.join(SESSION_ROOT, sessionKey);
  await fs.mkdir(dir, { recursive: true });
  const { state, saveCreds } = await useMultiFileAuthState(dir);
  const record = current || {
    key: sessionKey,
    status: 'connecting',
    qr: null,
    pairingCode: null,
    pairingPhone: '',
    phone: '',
    error: null,
    updatedAt: now(),
    socket: null
  };
  record.status = 'connecting';
  record.error = null;
  record.updatedAt = now();

  const socket = makeWASocket({
    auth: state,
    logger,
    browser: Browsers.windows('EventMenu Connect'),
    printQRInTerminal: false,
    markOnlineOnConnect: false,
    syncFullHistory: false,
    generateHighQualityLinkPreview: false
  });
  record.socket = socket;
  sessions.set(sessionKey, record);

  socket.ev.on('creds.update', saveCreds);
  socket.ev.on('connection.update', async update => {
    record.updatedAt = now();
    if (update.qr) {
      record.qr = update.qr;
      record.status = 'qr';
      record.error = null;
    }
    if (update.connection === 'connecting') record.status = record.qr ? 'qr' : 'connecting';
    if (update.connection === 'open') {
      record.status = 'connected';
      record.qr = null;
      record.pairingCode = null;
      record.error = null;
      record.phone = String(socket.user?.id || '').split(':')[0].split('@')[0];
    }
    if (update.connection === 'close') {
      const code = Number(update.lastDisconnect?.error?.output?.statusCode || update.lastDisconnect?.error?.statusCode || 0);
      const loggedOut = code === DisconnectReason.loggedOut;
      record.socket = null;
      record.status = loggedOut ? 'logged_out' : 'disconnected';
      record.qr = null;
      record.error = loggedOut ? 'Sessão desconectada do WhatsApp.' : 'Conexão interrompida; reconexão será tentada.';
      if (!loggedOut) {
        setTimeout(() => ensureSocket(sessionKey).catch(err => {
          record.error = String(err?.message || err);
          record.updatedAt = now();
        }), 1500).unref();
      }
    }
  });

  return record;
}

async function handle(req, res) {
  try {
    const url = new URL(req.url || '/', `http://${HOST}:${PORT}`);
    if (url.pathname === '/health' && req.method === 'GET') return send(res, 200, { ok: true });
    if (!authenticated(req)) return send(res, 401, { error: 'Não autorizado.' });

    const m = url.pathname.match(/^\/v1\/sessions\/([^/]+)(?:\/(start|pairing-code|logout|send))?$/);
    if (!m) return send(res, 404, { error: 'Rota não encontrada.' });
    const key = safeKey(decodeURIComponent(m[1]));
    const action = m[2] || '';

    if (req.method === 'GET' && !action) {
      const state = sessions.get(key);
      if (!state) {
        const dir = path.join(SESSION_ROOT, key);
        try { await fs.access(dir); return send(res, 200, publicState(await ensureSocket(key))); }
        catch { return send(res, 200, publicState(null)); }
      }
      return send(res, 200, publicState(state));
    }

    if (req.method === 'POST' && action === 'start') {
      return send(res, 200, publicState(await ensureSocket(key)));
    }

    if (req.method === 'POST' && action === 'pairing-code') {
      const body = await readJson(req);
      const phone = normalizePhone(body.phone);
      const state = await ensureSocket(key);
      if (!state.socket) throw new Error('Mecanismo do WhatsApp ainda não iniciou.');
      const code = await state.socket.requestPairingCode(phone);
      state.pairingCode = String(code || '');
      state.pairingPhone = phone;
      state.status = 'pairing_code';
      state.updatedAt = now();
      return send(res, 200, publicState(state));
    }

    if (req.method === 'POST' && action === 'logout') {
      const state = sessions.get(key) || await ensureSocket(key);
      try { if (state.socket) await state.socket.logout(); } catch {}
      state.socket = null;
      state.status = 'logged_out';
      state.qr = null;
      state.pairingCode = null;
      state.phone = '';
      state.updatedAt = now();
      await fs.rm(path.join(SESSION_ROOT, key), { recursive: true, force: true });
      return send(res, 200, publicState(state));
    }

    if (req.method === 'POST' && action === 'send') {
      const body = await readJson(req);
      const phone = normalizePhone(body.phone);
      const message = String(body.message || '').trim();
      if (!message || message.length > 4096) throw new Error('Mensagem inválida.');
      const state = await ensureSocket(key);
      if (!state.socket || state.status !== 'connected') throw new Error('WhatsApp não está conectado.');
      const mediaType = String(body.media_type || '').trim().toLowerCase();
      let content;
      if (mediaType === 'image' || mediaType === 'document') {
        const mediaUrl = String(body.media_url || '').trim();
        if (!mediaUrl) throw new Error('A mensagem de mídia não contém URL.');
        try {
          const media = await downloadMedia(mediaUrl, body.media_mime, mediaType);
          if (mediaType === 'image') {
            content = { image: media.buffer, mimetype: media.mime, caption: message };
          } else {
            const filename = path.basename(String(body.media_filename || 'documento.pdf')).replace(/[^A-Za-z0-9._ -]/g, '').slice(0, 180) || 'documento.pdf';
            content = { document: media.buffer, mimetype: media.mime, fileName: filename, caption: message };
          }
        } catch (mediaError) {
          const fallback = await state.socket.sendMessage(`${phone}@s.whatsapp.net`, { text: message });
          const reason = String(mediaError?.message || 'Falha ao baixar o anexo.').slice(0, 220);
          logger.warn({ mediaType, reason }, 'WhatsApp media fallback sent as text');
          return send(res, 200, { ok: true, message_id: String(fallback?.key?.id || ''), media_type: mediaType, media_fallback: true, media_error: reason });
        }
      } else {
        content = { text: message };
      }
      const result = await state.socket.sendMessage(`${phone}@s.whatsapp.net`, content);
      return send(res, 200, { ok: true, message_id: String(result?.key?.id || ''), media_type: mediaType || 'text', media_fallback: false });
    }

    return send(res, 405, { error: 'Método não permitido.' });
  } catch (err) {
    logger.warn({ err }, 'local whatsapp worker request failed');
    return send(res, 400, { error: String(err?.message || 'Falha no mecanismo local do WhatsApp.') });
  }
}

const server = http.createServer(handle);
server.listen(PORT, HOST, () => console.log(`EventMenu WhatsApp worker em http://${HOST}:${PORT}`));

for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => server.close(() => process.exit(0)));
}

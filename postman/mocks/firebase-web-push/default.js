// Contract subset: https://firebase.google.com/docs/cloud-messaging/send/v1-api
const http = require('node:http');
http.createServer(async (req, res) => {
    res.setHeader('Content-Type', 'application/json');
    const reply = (status, body) => { res.statusCode = status; res.end(JSON.stringify(body)); };
    if (req.method !== 'POST') return reply(501, { error: 'Endpoint not modelled' });
    if (req.url === '/token') {
        const form = new URLSearchParams(typeof req.body === 'string' ? req.body : '');
        // The simulation parses form bodies into objects when interceptRequests is enabled.
        const assertion = req.body?.assertion || form.get('assertion');
        if (typeof assertion !== 'string' || assertion.split('.').length !== 3) return reply(400, { error: 'invalid_grant' });
        return reply(200, { access_token: 'simulation-oauth', token_type: 'Bearer', expires_in: 3600 });
    }
    if (req.url !== '/v1/projects/tenantism-test/messages:send') return reply(501, { error: 'Endpoint not modelled' });
    if (req.headers.authorization !== 'Bearer simulation-oauth') return reply(401, { error: { status: 'UNAUTHENTICATED' } });
    let body = req.body;
    if (typeof body === 'string') { try { body = JSON.parse(body); } catch { return reply(400, { error: { status: 'INVALID_ARGUMENT' } }); } }
    const message = body?.message;
    if (!message?.token || !message.data || Object.values(message.data).some(value => typeof value !== 'string') || message.notification || !message.webpush?.headers?.TTL) return reply(400, { error: { status: 'INVALID_ARGUMENT' } });
    const code = message.token.startsWith('unregistered') ? 'UNREGISTERED' : message.token.startsWith('invalid') ? 'INVALID_ARGUMENT' : null;
    if (code) return reply(code === 'UNREGISTERED' ? 404 : 400, { error: { status: code === 'UNREGISTERED' ? 'NOT_FOUND' : 'INVALID_ARGUMENT', message: 'Invalid registration token', details: [{ '@type': 'type.googleapis.com/google.firebase.fcm.v1.FcmError', errorCode: code }] } });
    if (message.token.startsWith('temporary')) return reply(503, { error: { status: 'UNAVAILABLE' } });
    if (message.token.startsWith('quota')) { res.setHeader('Retry-After', '60'); return reply(429, { error: { status: 'RESOURCE_EXHAUSTED' } }); }
    return reply(200, { name: 'projects/tenantism-test/messages/simulation-message' });
}).listen(process.env.PORT || 4901);

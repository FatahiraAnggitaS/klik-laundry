import http from 'k6/http';
import ws from 'k6/ws';
import { check, fail, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const baseUrl = (__ENV.M9_BASE_URL || '').replace(/\/$/, '');
const smoke = __ENV.M9_SMOKE === '1';
const validationOnly = __ENV.M9_VALIDATE_ONLY === '1';
const readDuration = new Trend('m9_read_duration', true);
const mutationDuration = new Trend('m9_mutation_duration', true);
const realtimeDuration = new Trend('m9_realtime_duration', true);
const errors = new Rate('m9_error_rate');

export const options = {
    scenarios: validationOnly ? {
        discovery: {
            executor: 'shared-iterations',
            exec: 'discovery',
            vus: 1,
            iterations: 1,
        },
    } : {
        discovery: {
            executor: 'constant-vus',
            exec: 'discovery',
            vus: smoke ? 1 : 20,
            duration: smoke ? '2s' : '5m',
        },
        authenticated_read: {
            executor: 'constant-vus',
            exec: 'authenticatedRead',
            vus: smoke ? 1 : 20,
            duration: smoke ? '2s' : '5m',
        },
        safe_mutation: {
            executor: 'constant-arrival-rate',
            exec: 'safeMutation',
            rate: smoke ? 1 : 10,
            timeUnit: '1s',
            duration: smoke ? '2s' : '5m',
            preAllocatedVUs: smoke ? 1 : 20,
        },
        private_realtime: {
            executor: 'constant-vus',
            exec: 'privateRealtime',
            vus: smoke ? 1 : 100,
            duration: smoke ? '2s' : '5m',
        },
    },
    thresholds: validationOnly ? {
        m9_read_duration: ['p(95)<500'],
        m9_error_rate: ['rate<0.01'],
    } : {
        m9_read_duration: ['p(95)<500'],
        m9_mutation_duration: ['p(95)<1000'],
        m9_realtime_duration: ['p(95)<3000'],
        m9_error_rate: ['rate<0.01'],
    },
};

export function setup() {
    if (!baseUrl) fail('M9_BASE_URL is required.');

    const target = baseUrl.match(/^https?:\/\/(\[[^\]]+\]|[^/:?#]+)(?::\d+)?(?:[/?#]|$)/i);
    if (!target) fail('M9_BASE_URL must be an absolute HTTP(S) URL.');

    const hostname = target[1].toLowerCase();
    const productionLike = !['localhost', '127.0.0.1', '[::1]'].includes(hostname)
        && !hostname.includes('staging')
        && !hostname.endsWith('.test');

    if (productionLike || __ENV.M9_ALLOW_LOAD_TEST !== 'true') {
        fail('Load test refuses this host unless it is an approved non-production target.');
    }
}

function headers() {
    return {
        Accept: 'text/html,application/xhtml+xml',
        Cookie: __ENV.M9_SESSION_COOKIE || '',
        'X-CSRF-TOKEN': __ENV.M9_CSRF_TOKEN || '',
        'X-M9-FIXTURE-RUN': __ENV.M9_FIXTURE_RUN_ID || '',
    };
}

function record(response, trend) {
    trend.add(response.timings.duration);
    const ok = check(response, { 'status is successful': (result) => result.status >= 200 && result.status < 400 });
    errors.add(!ok);
}

export function discovery() {
    record(http.get(`${baseUrl}/outlets?page=1`, { headers: headers(), redirects: 0 }), readDuration);
    sleep(0.2);
}

export function authenticatedRead() {
    record(http.get(`${baseUrl}${__ENV.M9_AUTHENTICATED_READ_PATH || '/workspace'}`, { headers: headers(), redirects: 0 }), readDuration);
    sleep(0.2);
}

export function safeMutation() {
    const path = __ENV.M9_MUTATION_PATH;
    if (!path) fail('M9_MUTATION_PATH is required for the staging fixture mutation.');

    const response = http.request(
        __ENV.M9_MUTATION_METHOD || 'POST',
        `${baseUrl}${path}`,
        __ENV.M9_MUTATION_PAYLOAD || '{}',
        { headers: { ...headers(), 'Content-Type': 'application/json' }, redirects: 0 },
    );
    record(response, mutationDuration);
}

export function privateRealtime() {
    const socketUrl = __ENV.M9_REVERB_WS_URL;
    const subscribePayload = __ENV.M9_REVERB_SUBSCRIBE_PAYLOAD;
    if (!socketUrl || !subscribePayload) fail('Private Reverb URL and subscription payload are required.');

    const startedAt = Date.now();
    const result = ws.connect(socketUrl, { headers: headers() }, (socket) => {
        socket.on('open', () => socket.send(subscribePayload));
        socket.on('message', (message) => {
            if (String(message).includes('subscription_succeeded')) {
                realtimeDuration.add(Date.now() - startedAt);
                socket.close();
            }
        });
        socket.setTimeout(() => socket.close(), smoke ? 1000 : 10000);
    });

    errors.add(result?.status !== 101);
}

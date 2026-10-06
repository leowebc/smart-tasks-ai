import { writeFile } from 'node:fs/promises';

const configuredUrl = process.env.API_URL?.trim();

if (!configuredUrl) {
  console.log('API_URL não definida; mantendo /api no bundle de produção.');
  process.exit(0);
}

const apiUrl = new URL(configuredUrl);
if (!['http:', 'https:'].includes(apiUrl.protocol)) {
  throw new Error('API_URL deve usar o protocolo HTTP ou HTTPS.');
}
if (apiUrl.username || apiUrl.password) {
  throw new Error('API_URL não pode conter credenciais.');
}

const normalizedUrl = configuredUrl.replace(/\/+$/, '');
const target = new URL('../dist/frontend/browser/assets/runtime-config.js', import.meta.url);
const contents = `globalThis.__SMART_TASKS_CONFIG__ = Object.freeze({
  apiUrl: ${JSON.stringify(normalizedUrl)},
});
`;

await writeFile(target, contents, 'utf8');
console.log('Configuração de produção gerada a partir de API_URL.');

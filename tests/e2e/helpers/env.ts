import fs from 'fs';
import path from 'path';

/** Read a single key from Laravel `.env` without adding a dotenv dependency. */
export function readLaravelEnv(key: string, fallback = ''): string {
  if (process.env[key] && process.env[key]!.trim() !== '') {
    return process.env[key]!.trim();
  }

  const envPath = path.join(__dirname, '..', '..', '..', '.env');
  if (!fs.existsSync(envPath)) {
    return fallback;
  }

  const raw = fs.readFileSync(envPath, 'utf8');
  const match = raw.match(new RegExp(`^${key}=(.+)$`, 'm'));
  if (!match?.[1]) {
    return fallback;
  }

  return match[1].trim().replace(/^["']|["']$/g, '');
}

export function adminCredentials() {
  return {
    email: readLaravelEnv('E2E_ADMIN_EMAIL', 'admin@admin.com'),
    password: readLaravelEnv('E2E_ADMIN_PASSWORD', '12345678'),
  };
}

/**
 * Normalize static asset paths for hosts whose docroot is the project root
 * (files live under /public/...). Mirrors App\Helpers\Help::normalizePublicPath.
 */
export function resolvePublicAsset(path) {
  if (!path) return "";
  if (
    /^(https?:)?\/\//i.test(path) ||
    path.startsWith("blob:") ||
    path.startsWith("data:")
  ) {
    if (/^(https?:)?\/\//i.test(path)) {
      try {
        const u = new URL(path, window.location.origin);
        const fixed = resolvePublicAsset(u.pathname);
        if (fixed && fixed !== u.pathname) {
          u.pathname = fixed;
          return u.toString();
        }
      } catch (_) {
        /* keep original */
      }
    }
    return path;
  }

  let p = path.startsWith("/") ? path : `/${path}`;
  while (p.startsWith("/public/public/")) {
    p = p.slice(7);
  }
  if (p.startsWith("/img/") || p.startsWith("/storage/")) {
    p = `/public${p}`;
  }
  return p;
}

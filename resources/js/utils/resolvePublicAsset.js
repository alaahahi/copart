/**
 * Normalize static asset paths for both deploy styles:
 * - docroot = public/        → /img/branding/x.jpg
 * - docroot = project root   → /public/img/branding/x.jpg
 *
 * Prefix comes from window.__PUBLIC_ASSET_PREFIX__ (set in app.blade.php)
 * or Inertia page.props.publicAssetPrefix.
 */

const STATIC_DIR = /^(img|storage|css)\//;

export function getPublicAssetPrefix() {
  if (typeof window !== "undefined") {
    if (window.__PUBLIC_ASSET_PREFIX__ === "") {
      return "";
    }
    if (window.__PUBLIC_ASSET_PREFIX__ === "/public") {
      return "/public";
    }
  }
  return "/public";
}

export function setPublicAssetPrefix(prefix) {
  if (typeof window === "undefined") return;
  window.__PUBLIC_ASSET_PREFIX__ =
    prefix === "" || prefix === "/" ? "" : "/public";
}

function canonicalizeStaticPath(path) {
  let p = path.startsWith("/") ? path : `/${path}`;
  while (p.startsWith("/public/public/")) {
    p = p.slice(7);
  }
  if (p.startsWith("/public/") && STATIC_DIR.test(p.slice(8))) {
    p = p.slice(7);
  }
  return p;
}

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

  let p = canonicalizeStaticPath(path);
  if (STATIC_DIR.test(p.slice(1))) {
    const prefix = getPublicAssetPrefix();
    if (prefix) {
      p = `${prefix}${p}`;
    }
  }
  return p;
}

/** Opposite prefix — used when the first URL 404s. */
export function alternatePublicAsset(path) {
  if (
    !path ||
    path.startsWith("blob:") ||
    path.startsWith("data:") ||
    /^(https?:)?\/\//i.test(path)
  ) {
    return "";
  }
  const p = canonicalizeStaticPath(path);
  if (!STATIC_DIR.test(p.slice(1))) {
    return "";
  }
  const current = resolvePublicAsset(p);
  const other = current.startsWith("/public/") ? p : `/public${p}`;
  return other !== current ? other : "";
}

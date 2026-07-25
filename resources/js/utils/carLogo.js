/**
 * Map car make / car_type text to /car-logos/{slug}.svg (public/).
 * Files: audi, bmw, chevrolet, chrysler, ford, gmc, honda, humme, hyundai,
 * infiniti, jeep, kia, land-rover, mazda, mercedes-benz, mitsubishi, nissan,
 * opel, suzuki, tesla, toyota, volkswagen.
 */

/** brand key (lowercase) → filename without extension */
const BRAND_TO_FILE = {
  audi: "audi",
  bmw: "bmw",
  chevrolet: "chevrolet",
  chevy: "chevrolet",
  chrysler: "chrysler",
  ford: "ford",
  gmc: "gmc",
  honda: "honda",
  hummer: "humme",
  humme: "humme",
  hyundai: "hyundai",
  infiniti: "infiniti",
  jeep: "jeep",
  kia: "kia",
  "land rover": "land-rover",
  landrover: "land-rover",
  "land-rover": "land-rover",
  "range rover": "land-rover",
  mazda: "mazda",
  mercedes: "mercedes-benz",
  "mercedes benz": "mercedes-benz",
  "mercedes-benz": "mercedes-benz",
  benz: "mercedes-benz",
  mitsubishi: "mitsubishi",
  nissan: "nissan",
  opel: "opel",
  suzuki: "suzuki",
  tesla: "tesla",
  toyota: "toyota",
  volkswagen: "volkswagen",
  vw: "volkswagen",
};

const BRAND_KEYS = Object.keys(BRAND_TO_FILE).sort((a, b) => b.length - a.length);

const normalizeBrandText = (v) =>
  String(v || "")
    .toLowerCase()
    .replace(/[_/]+/g, " ")
    .replace(/\s+/g, " ")
    .trim();

/**
 * @param {string|null|undefined} carType e.g. "TOYOTA Camry"
 * @returns {string|null} public URL or null
 */
export function resolveCarLogoUrl(carType) {
  const text = normalizeBrandText(carType);
  if (!text) return null;

  for (const key of BRAND_KEYS) {
    if (
      text === key ||
      text.startsWith(`${key} `) ||
      text.startsWith(`${key}-`) ||
      text.includes(` ${key} `) ||
      text.endsWith(` ${key}`)
    ) {
      return `/car-logos/${BRAND_TO_FILE[key]}.svg`;
    }
  }

  return null;
}

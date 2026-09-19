import { computed } from "vue";
import { usePage } from "@inertiajs/inertia-vue3";

/**
 * Branding values shared by HandleInertiaRequests (config/app.php + system_config).
 * Single source for the brand name / tagline shown across layouts and components.
 */
export function useBranding() {
  const page = usePage();

  const productName = computed(() => page.props.value?.productName || "");
  const appName = computed(() => page.props.value?.appName || productName.value);
  const tagline = computed(() => page.props.value?.productTagline || "");
  const logo = computed(() => page.props.value?.branding?.logo || "");
  const cover = computed(() => page.props.value?.branding?.cover || "");

  return { appName, productName, tagline, logo, cover };
}

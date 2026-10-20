// Services that draw initials or a stock placeholder instead of a photo (the admin panel shows ui-avatars for a
// product without one): such a URL is no picture of the dish.
const GENERATED_IMAGE_HOSTS = ['ui-avatars.com', 'placehold.co', 'placehold.it', 'via.placeholder.com', 'dummyimage.com', 'api.dicebear.com'];

// The URL of a real photo, or null for an empty value or a generated avatar/placeholder.
export function realImageUrl(src: string | null | undefined): string | null {
  const value = src?.trim();
  if (!value) return null;
  try {
    const { hostname } = new URL(value, 'https://rizqim.local');
    if (GENERATED_IMAGE_HOSTS.some((host) => hostname === host || hostname.endsWith(`.${host}`))) return null;
  } catch {
    return null;
  }
  return value;
}

// Saves a downloaded file under its name: an object URL on a temporary link. Works for a file fetched with the session's
// token, which a plain <a href> to the API could not be.
export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.rel = 'noopener';
  document.body.append(link);
  link.click();
  link.remove();
  // Give the browser a moment to start the save before the URL goes away
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

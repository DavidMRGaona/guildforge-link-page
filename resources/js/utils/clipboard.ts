/**
 * Puts text on the clipboard. The clipboard API where the browser allows it; the older
 * select-and-copy way when it is denied or the page is not a secure context.
 *
 * @returns whether the text was copied
 */
export async function writeToClipboard(text: string): Promise<boolean> {
    if (typeof navigator.clipboard?.writeText === 'function') {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // Denied or not in a secure context: the older way below may still work
        }
    }

    // execCommand is deprecated but is all an insecure origin or an old browser has
    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();

    try {
        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        field.remove();
    }
}

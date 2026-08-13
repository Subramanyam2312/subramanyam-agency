# Screenshots to capture

`README.md` references `docs/images/dashboard.png`, which does not exist yet. Until it does, the image at the top of the README renders as a broken-image icon on GitHub.

## What to capture

**One screenshot, `dashboard.png`.** The admin portal — this is the thing that makes the repo interesting, and a visitor should see it before reading a word.

Best single frame: the **portal dashboard**, or **Pages → block editing** with the draft/publish controls visible. Either shows it's a real CMS rather than a settings form.

Run the site locally first (see the README quick start), sign in at `/admin`, and capture at a desktop window width — around 1440px. A narrow window collapses the sidebar and the screenshot stops showing the structure.

## Optional second capture

If you want a short GIF instead, record **8–12 seconds** of one flow:

1. Open a page in the portal
2. Edit a block
3. Save as draft
4. Publish
5. The public page updates

One flow, no cursor wandering. Longer than about 15 seconds and nobody watches to the end.

## Phone-friendly capture

You record on your phone, so if you're capturing the screen rather than screenshotting:

- Put the browser in full screen first (`⌃⌘F`) to remove the tab bar and dock
- Landscape orientation, phone braced against something solid
- Avoid catching window reflections — angle slightly off-axis from any light source
- Trim to just the flow before saving

A direct screenshot (`⇧⌘4` then space, then click the window) will always look sharper than a phone recording. Use the phone only for the GIF.

## Before you commit it

**Check the frame for:**
- Real client names in any content list — the seed data is generic, but your local database may not be
- Your email address in the account menu
- Browser bookmarks bar and any other open tabs
- Any real contact form submissions in the inbox view

Save as `docs/images/dashboard.png`, target under 500 KB. Then delete this file.

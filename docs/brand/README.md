# Brand concepts (owner, 21.11.2026)

- `logo-concept.png`: logo concept. R mark with a food cloche, word "Rizqim", slogan "Rizq har kuni."
- `landing-concept-hero.png`, `landing-concept-full.png`: rizqim.uz landing concept (wave P).

Notes for wave P (to confirm with the owner before building):
- Colours match the apps (brand #C2410C, accent #F59E0B, see packages/dart/rizqim_ui/lib/src/theme/rz_colors.dart).
- The concept shows App Store / Google Play buttons; the customer app is a Telegram Mini App, so the button should open the bot.
- The figures (1 200+ restaurants, 10+ cities, 100 000+ users) and the testimonials are placeholders: no invented numbers or reviews go live.
- "Dasturchilar uchun / API" section: no public partner API is planned yet.
- A vector (SVG) version of the logo is needed for the site, apps and receipts.

## Partner page ("Hamkor bo'lish"), owner concept 21.11.2026
- `partner-page-concept.png` (full page), `partner-form-concept.png` (the form).
- The form already has a backend: `POST /api/v1/store-applications` (P4-22; throttled, honeypot `website`), fields:
  name (place), format (restaurant, cafe, fast_food, canteen, coffee_shop, bakery, teahouse, catering, other),
  region_id or city, owner_name, phone, tin (optional), comment (optional), source=website. Applications land in the
  control centre. The concept's extra fields (e-mail, partner types "Do'kon/Market", "Texnologik hamkor",
  "Yetkazib berish hamkori") have no backend yet: decide with the owner (wave P).
- FAQ answers (is it free, commission, launch time, POS integration) must come from the owner's real tariffs.

## Second round (21.11.2026), drawn from LANDING_CONTENT.md
- `partner-v2-desktop.png`, `partner-v2-mobile.png`: partner page with the real form fields and the four form states. This is the version to build.
- `landing-v2-mobile.png`: landing, mobile. The desktop landing of this round is still to come; `landing-concept-*.png` above are the first round only.
- Supersedes `partner-page-concept.png` / `partner-form-concept.png` (first round: partner types, e-mail, not built).
- Built (wave P): `infra/landing/` is the static rizqim.uz site (landing + `/hamkor` form); logo SVGs there are placeholders recreated from `mark-concept.png` / `logo-concept.png`, owner values live in `infra/landing/site/site.config.js`.

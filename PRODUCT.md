# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Reviewers at Star Media Group** (hiring team for a Web Developer role in Petaling Jaya). They run the project locally from the README and judge it against the practical-test brief: requirements met, code quality, and front-end craft. They are the audience the whole project must convince.
- **Readers** of the demo publication, on desktop and mobile, who finish or skim a story and share it with friends and family.
- **Newsroom / audience-team admins** who sign in to see which stories get shared, where, and when, and filter by date range, platform and page.

## Product Purpose

A practical-test submission: a demo news site whose social share buttons record every click, plus a secured admin dashboard that graphs those clicks. Success = a reviewer can set it up in minutes, every brief requirement is visibly met, and the craft stands out from other applicants.

## Positioning

Sharing is the product, not a footer widget: the demo publication is built around passing stories on (Malay "ajak" = to invite), and the admin turns those invitations into newsroom insight. Platforms are data, so a new network is a form entry, not a code change.

## Operating Context

- Run locally via `php bin/install.php` + `php -S`, or XAMPP htdocs. Reviewers use desktop browsers; readers' realistic device is a phone, where WhatsApp/Telegram dominate sharing in Malaysia.
- Share links open the networks' own share pages; Open Graph tags provide previews (only visible when deployed publicly).

## Capabilities and Constraints

- **Brief requirements (must remain intact):** 5 share buttons (Facebook, X, WhatsApp, Telegram, Email) sharing the current page URL + metadata; each click sent to the back-end (page URL + platform) and stored in MySQL; admin page with graphs of share clicks filterable by date range, platform, and more.
- **Bonus features already built (must remain):** admin login, server-side input validation, platforms table driving buttons and graphs (add/hide platforms from admin).
- **Stack:** plain PHP 8.1+, MySQL/MariaDB, **Bootstrap 5 stays the base** (the job ad requires Bootstrap experience), Bootstrap Icons, Chart.js 4. Front-end libraries load from jsDelivr; CSP allows only self + jsDelivr. share.js stays ES5 for legacy browsers; buttons must work without JavaScript.
- Demo articles are static placeholder content in `src/Content/ArticleRepository.php`.

## Brand Commitments

- Demo publication name: **Ajaklah News** (user-chosen). Fictional; must never imitate a real outlet (no Star Media branding).
- Malaysian context (Petaling Jaya, kopitiam, hawker, local platforms usage).

## Evidence on Hand

- Demo articles (4) and cover photos in `public/assets/img/covers/` (credits in the README).
- Demo click data comes from `bin/seed-demo.php` (synthetic; must be labelled as demo, never presented as real traffic).
- No real readership numbers, testimonials or press exist; none may be invented.

## Product Principles

1. The brief's requirements are always visible and obvious; design amplifies them, never hides them.
2. Sharing should feel like the most natural action on the page, on phone first.
3. The dashboard answers "what got shared, where, when" at a glance, then lets admins drill down.
4. Everything a reviewer touches works on first run: no broken states, clear errors, graceful empty states.

## Accessibility & Inclusion

Keyboard-operable controls, visible focus, sufficient contrast, reduced-motion support, and labelled icon-only share buttons on mobile.

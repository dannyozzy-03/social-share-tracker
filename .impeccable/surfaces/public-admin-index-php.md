---
version: 1
slug: "public-admin-index-php"
primary_target: "public/admin/index.php"
related_targets: ["public/admin","templates/admin"]
---

## Scope

Admin area: login, share analytics dashboard, platforms list and platform form (`public/admin/*`, `templates/admin/*`). Mode: **Operate**. Inherits the Cyclorama Dawn world of the public site (see the public site brief); expression never obscures the task.

## Job

Admins see what got shared, where and when, at a glance, then filter by date range, platform, page and grouping, drill down from graphs, export CSV, and manage platforms. All brief graphs and filters stay obvious within seconds; validation errors name the problem and the fix.

## Direction contract

THESIS: The dashboard is the lighting booth. Filters are the cue rail, platforms are channels on a fader bank, the timeline is the light over the day. It refuses the KPI-tile-plus-card-grid analytics template.

OWN-WORLD: Booth black #07070B ground with matte panels separated by hairlines, cobalt #0A33FF for focus and selection, rose #FF6AAE for the one live readout, day wash text. Bootstrap 5.3 dark colour mode, re-themed. Stencil caps for panel labels; Albert Sans with tabular numerals for all data. Platform brand colours as gels in charts and chips.

STORY: Admin reads one sentence readout of the period (total, change, leading platform), sees each platform's share as a fader level, scans the timeline and top pages, then narrows with the cue rail.

FIRST VIEWPORT: Top bar with wordmark and nav. Cue rail of filters across the width. Below it a horizon band: the period readout sentence at display scale on the left, fader bank of platform levels on the right. Timeline graph begins at the fold.

FORM: Cyclorama Dawn (stagecraft-theater-lighting-cyclorama-dawn); seed key c25325e1.

SIGNATURE: Faders: each platform is a vertical fader whose level is its share of clicks; clicking a fader solos that channel (filters the dashboard). Values snap to new levels when filters change.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

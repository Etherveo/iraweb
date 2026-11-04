# DESIGN.md — UI/UX Agent Rules

Operating rules for AI design and frontend agents (Figma Make, Figma Design Agent, Google Stitch, Claude Code, Cursor, v0, Lovable, or any AGENTS.md-aware tool) that build a product interface from a **system spec** — entities, flows, roles, APIs — usually with little or no visual direction.

> **Core principle:** Build the interface a real person would be glad to open. It does exactly what the system spec says, it is easy to use, and it looks polished and comfortable. Visual quality is part of the requirement, not decoration added afterwards. A familiar pattern done well beats an original pattern done awkwardly.

Apply every rule to every screen and state in the task, not only the first one.

**Rule types** — `[HARD]` must hold (kept few on purpose). `[DEFAULT]` is the standard approach; deviate only when the brief, an existing design system or Appendix G says otherwise, and say so in one line. `[CHECK]` is verified before a screen is called done.

---

# PART 1 — CORE RULES

*Part 1 stands on its own. If the appendices aren't available to you, treat references to them as optional: use the fallbacks given in the text (for example the Modern product preset in §4) and your own judgment.*

## 1. Priorities `[HARD]`

Resolve conflicts top-down — a higher level always beats a lower one:

1. **Spec fidelity** — the system design is the source of truth (§2). `[HARD]`
2. **Usability and accessibility floor** (§8, §10). `[HARD]`
3. **Visual quality** — hierarchy, polish, richness, comfort, consistency (§5–§7). Treat as near-mandatory: a screen that is correct but barren, cramped or text-only is unfinished.
4. **Personality** — a recognizable character is welcome, never at the cost of 1–3.
5. **Minimalism** — a style option, not a goal. Plain is not the same as clean; restraint only works when every detail is executed with care.

**Aesthetics are a sufficient reason.** Any visual treatment — gradient, shadow, glass, illustration, icon, rounded card, background texture, motion — is allowed when it makes the design look better or feel more comfortable and does not break levels 1–2. "It improves the look and fits the chosen style" is a complete justification; beauty does not need a functional excuse. Don't strip a design down to make it look "intentional".

**What "AI slop" means here.** Slop is carelessness, not a pattern: the wrong product type (a landing page for a dashboard), lorem ipsum, colors that don't fit the domain, missing states, inconsistent radius and spacing, decoration unrelated to the content. Looking like other AI output is not a defect. Fix slop with specificity and consistency — real domain content, tokens, a deliberate palette, every state designed — never by deleting visual richness. Banning one default look tends to push a model into a different fixed look, so choose a look on purpose (§4).

## 2. Spec fidelity `[HARD]`

The backend or system design defines what the product does. The visual layer can be as rich as it likes, but it must not break or bend that.

- Every entity, field, action, role, state and rule in the spec is reachable and visible in the UI. Required fields are marked; validation limits (min, max, pattern, uniqueness) show as inline messages; every enum value has a designed appearance; each role sees and can do only what the spec allows (hide or disable — and say which).
- Use the spec's domain vocabulary verbatim. Don't rename concepts.
- Don't invent entities, fields, endpoints, permissions or business rules. If the UI needs something the spec doesn't define (search, pagination, bulk actions, export), design it, mark it *assumed*, and list it under **Backend needs** in the Design Notes so the engineer can decide.
- Add the standard screens real systems need but specs usually omit — sign-in, profile, settings, 403/404/500, loading/empty/error states, confirmations for destructive actions, toasts — and flag them *added (not in spec)*.
- Presentation is yours to decide: labels, grouping, icons, layout, microcopy, sample data, charts, polish. Adding presentation is never scope creep; adding functionality is.

## 3. Understand before you draw `[DEFAULT]`

Before the first frame, settle these. Write the answers as 5–8 short lines; they become the Design Notes (§13).

1. **Product and users** — what it is, who uses it, on which device, in what situation (office desk, phone in bright light, shared kiosk), and how expert they are.
2. **Register** — *Product* (apps, dashboards, admin, forms, tools: design serves the task) or *Brand* (landing, marketing, portfolio: design is the message). A system spec almost always means Product. A landing page requested inside a product project is Brand for that page only.
3. **Primary task per screen** and the information priority: what the user must see first, second, third.
4. **Screen inventory and navigation model**, derived from the spec (§2, Appendix B).
5. **Style** — given by the brief or an existing system? Follow it exactly. Not given? Decide one on purpose (§4).
6. **What can be reused** — existing components and tokens before new ones.

If the brief doesn't answer these, assume, state the assumption in one line, and proceed. Don't block on questions; don't guess silently.

## 4. No style given? Decide one on purpose `[DEFAULT]`

Most briefs describe the system and say nothing about looks. That is permission to design, not to fall back on a default. Without a decision, a model drifts toward whichever look it has seen most; with one, the result is cohesive and easy to re-skin.

**Register defaults**

| | Product (apps, tools, dashboards) | Brand (landing, campaign, portfolio) |
|---|---|---|
| Role of design | Serves the task: speed, clarity, trust | Is the message: first impression, emotion |
| Structure | Conventional skeletons (sidebar or top bar, tables, forms) — users prefer what they already know | Free to be editorial or expressive |
| Density | Medium to high; no hero banners inside the app | Medium to airy |
| Type | One versatile sans, strong numerals | A display face is allowed for character |
| Color | Calm canvas + one brand accent + semantic colors | Bolder palette, gradients, imagery |
| Motion | Feedback (hover, press, expand, toast) | Feedback plus one entrance moment |

**Steps**

1. Read the spec for tone cues: domain words, audience, region and language, seriousness (payments vs. a school club).
2. Pick the closest **archetype** in Appendix A (without the appendix: the conventions of similar well-known products) and name a runner-up. Blend at most two. If nothing fits, use the **Modern product** preset below.
3. Set three dials from the archetype, 1–10 each: **Density** (airy → dense), **Expressiveness** (conventional → bold) and **Motion** (feedback only → rich; rich is for Brand surfaces). Appendix A gives the scale and defaults. Adjust by at most ±2, with a stated reason.
4. Derive the token set (§7): one brand hue, tinted neutrals, semantic colors, one type pairing, a radius and shadow personality.
5. Write the **Design Notes** (format in §13) and keep them with the project.
6. **Persist and reuse.** Store the decision as real tokens — Figma variables and styles, CSS variables or a Tailwind theme, or a token `DESIGN.md` — and use them on every later screen. Don't re-decide per screen; style drift over a long session is the most common failure.
7. If the tool supports a quick choice and it costs little, you may offer three directions (background / accent / typeface plus a one-line rationale). If nobody answers, go with your best pick. Never block.

**Modern product (fallback preset).** Density 5 · Expressiveness 5 · Motion 3. Light theme on an off-white canvas; white cards with a 1px border and a soft shadow; one saturated brand color (blue or teal unless the domain suggests otherwise) with tinted surfaces for emphasis; 10–12px radius; a clean sans (Inter, DM Sans or Plus Jakarta Sans); an icon on every nav item, list row and stat; a subtle brand gradient on at most one hero element per screen; tinted section bands to separate groups. This is the familiar "modern SaaS" look — and that is fine when it is executed with care and fits the product.

**When the person reacts** ("too plain", "too busy", "feels weird"), translate it with Appendix F and keep the same tokens.

## 5. Layout, density and whitespace `[DEFAULT]`

Space exists to group things, not to fill the canvas. Voids, stiffness and walls of text are the most common ways a "restrained" design fails.

**Spacing**

- **Proximity:** gap inside a group < gap between groups < gap between sections. Typical values: 8–16 within a group, 24–32 between groups, 48–80 between page sections (Brand surfaces up to 96–120). Related things sit closer than unrelated things; that alone creates structure.
- Start generous and trim until the layout feels comfortable — then stop. Past the point where groups still read as groups, extra space is a void.
- Page padding 16 (mobile) / 24 (tablet) / 32 (desktop). Card padding 16–24. Nothing touches a container edge.

**No voids**

- No region larger than about a quarter of the viewport sits empty without a reason. (A deliberate focal element with room around it is a reason.)
- To fix a void, in this order: tighten spacing one step → rebalance into columns (for example 2/3 + 1/3) → enlarge the content that matters → add relevant content the spec supports (summary stats, recent activity, shortcuts, related items) → only then leave it empty.
- Product screens use the full window: sidebar + fluid grid, content width up to ~1440–1600, with tables and card grids adding columns as the window grows. Don't float a narrow column in the middle of a wide empty window. Narrow measures are for reading text (60–75 characters) and for forms (480–640px) — place those inside a card or a split layout, never alone in white space.

**No text walls**

- UI screens are not documents. Turn prose into structure: stat tiles, cards, icon lists, tables, chips, tabs, accordions, timelines. Keep paragraphs in UI chrome to three lines.
- Give every screen at least one non-text visual anchor suited to its content: an icon, avatar, thumbnail, chart, progress indicator, status color, illustration or image.

**Rhythm and variety**

- Vary the size and emphasis of blocks: a primary card larger than its supporting cards, a full-width or tinted band between groups, one clearly dominant element per screen. Avoid page after page of identical grids.
- Layer surfaces — canvas → card → raised (popover, modal) — using tinted backgrounds, borders and shadows from tokens, so grouping comes from surface as well as space. White-on-white with only gaps reads as flat.
- Alignment: left-align content; center only short, isolated content (auth, empty states, a hero). Snap to a grid — 12 columns on desktop, 8 on tablet, 4 on mobile.

**Density by archetype** (Appendix A): dense apps use 36–44px rows and 14px text; everyday apps 48–56px rows and 14–16px text; reading and marketing surfaces 16–18px text with larger gaps.

## 6. Visual richness `[DEFAULT]`

Use these levers freely. They turn a correct layout into a finished-looking product.

| Lever | How to use it well |
|---|---|
| **Color** | Brand color on primary actions, active navigation, key numbers, chart series and selected states. Tinted surfaces (brand-50) mark emphasis zones. Semantic colors for status. Roughly 60% neutral, 30% secondary surfaces and text, 10% accent. |
| **Gradients** | Subtle and same-hue or analogous (brand-500 → brand-700) on hero panels, the primary stat card, CTAs, chart fills, avatars and cover placeholders. A purple-to-blue wash is fine when it is the brand hue rather than an unexamined default. |
| **Depth** | Three to five named levels (flat, sm, md, lg) used consistently: cards = 1px border + sm; hover or raised = md; modals and menus = lg. The same recipe everywhere. |
| **Icons** | One family (Lucide, Phosphor, Material Symbols or Heroicons) with consistent stroke and size (16 / 20 / 24), on nav items, buttons, list rows, stat tiles and empty states. A tinted circle or rounded square behind an icon is fine for scanability. Use SVG icons rather than emoji in product UI (emoji suit playful or children's products). |
| **Imagery and illustration** | Avatars with initials fallback, thumbnails, cover images, and simple geometric SVG illustrations for empty states, onboarding and auth screens. With no assets, use a tinted placeholder with an icon, or a brand-hue gradient or pattern — never a blank gray box. |
| **Data visualization** | Match chart to data (trend → line or area; comparison → bar; share → donut up to 5 slices; progress → bar or ring). Axis labels and units, tooltips, palette from tokens, an empty-chart state. |
| **Status and feedback** | Status chips (color + icon + label), progress bars and rings, toasts, skeleton loaders, inline validation. |
| **Typography as texture** | Strong contrast in size and weight between title, section heading, body and caption; tabular numerals for numbers; tighter tracking on large headings; muted secondary text. |
| **Backgrounds** | Soft gradients, blurred color blobs, dot or grid patterns and subtle noise on auth screens, hero areas and empty states. Keep working canvases (tables, forms) calm. |
| **Glass and blur** | Fine on sticky headers, overlays and floating panels when text contrast holds. |
| **Cards and bento grids** | Good for dashboards and overviews. Vary card size by importance; use identical cards only for genuinely equal items. |
| **Motion** | Micro-interactions on every interactive element (hover, press, focus; 120–200ms), skeleton shimmer, and one page-load or reveal moment on Brand surfaces. Ambient looping motion only on Brand surfaces, and subtle. Honor reduced motion (§10). |

**Patterns that carry meaning — use them for what they mean**

- Numbered markers (01 / 02 / 03) say "this is an order": use them for real sequences such as steps, timelines and rankings.
- Badges that assert something ("Live", "New", "Beta", "Verified", counts) must reflect real data or state.
- Nested corners follow inner radius = outer radius − padding. Radius scales with element size and hierarchy.

**Keep product UIs out of editorial territory** — hairline rules everywhere, zero radius, mono-caps kicker labels above every heading, newspaper columns — unless the brief is editorial. It photographs as "designed" and feels strange to use.

**Spend personality in a few repeatable places** — brand color, shape language (radius), illustration style, one hero moment — and apply them consistently. Consistency is what makes a design feel owned.

## 7. Tokens `[HARD — values come from tokens]`

Define tokens once (starter values in Appendix C) and pull every value from them. Need something new? Add a token; don't hard-code a one-off. Components reference tokens, never raw values.

- **Color** — brand (50–900 scale), neutrals tinted toward the brand hue rather than pure gray, surface / background / border, text primary / secondary / tertiary, and semantic success / warning / error / info (each with a tinted background and a text shade). Build scales by lightness steps and check every text-on-background pair for contrast.
- **Typography** — display, h1–h3, body-lg, body, body-sm, caption, label. One family for UI, an optional display family on Brand surfaces, and mono for code and IDs. Choose for legibility and fit with the archetype. Familiar, well-supported families (Inter, Geist, DM Sans, Plus Jakarta Sans, Manrope, Figtree, Nunito Sans, IBM Plex Sans, Public Sans, Source Sans 3) are good choices and are not wrong for being common. Body 16px by default and ≥ 14px in dense apps; line height 1.5–1.6 for body and 1.2–1.3 for headings; 45–75 characters per line; no thin weights for body text.
- **Spacing** — 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 / 96.
- **Radius** — choose a personality (sharp 4 · balanced 8–12 · soft 16–24) and apply it by hierarchy: small for inputs and chips, medium for buttons and cards, large for modals and hero panels, full for avatars and pills.
- **Elevation** — flat / sm / md / lg, as in §6.

## 8. Responsive design `[HARD requirement — behaviors below are DEFAULT]`

Build **one adaptive layout system**, not two designs that happen to look similar. Resizing the frame or viewport should reveal the right layout on its own.

| Breakpoint | Width | Typical layout |
|---|---|---|
| Mobile | 360–767 | 1 column; bottom tab bar or drawer; cards instead of wide tables |
| Tablet | 768–1023 | 2 columns; collapsible or icon-only sidebar |
| Desktop | 1024+ | Sidebar + fluid content grid; 3–4 columns |

Define what changes at each breakpoint, not just "gets smaller":

- **Reflow** — 4 → 2 → 1 columns; secondary panels move below the main content or into tabs or sheets.
- **Navigation transforms** — desktop sidebar → tablet icon rail → mobile bottom tabs (max 5) or drawer. Decide it and document it.
- **Tables become cards or key-value rows** on mobile (title, status, one or two key fields, an actions menu); filters move into a bottom sheet.
- **Hide or show, not just shrink** — secondary content collapses behind a control instead of surviving at illegible sizes.
- **Type and spacing scale on tokens**, never arbitrary per-breakpoint values.
- **Touch targets** ≥ 44×44 on mobile and tablet (48 preferred).
- **Width** — Product screens fill the window up to ~1440–1600 and add columns as they grow. Brand and reading surfaces cap at ~1200–1280.
- Use auto-layout or constraints (flex and grid in code) on every frame and component. If the tool can only produce fixed-size mocks, deliver explicit mobile and desktop frames built from the same tokens and components, and say so.

`[CHECK]` Resize across the full range, not only the endpoints. Confirm nothing overlaps, truncates unexpectedly, or leaves orphaned gaps.

## 9. Components and states `[DEFAULT]`

- Reuse an existing component before creating a new one. Express differences as variants and properties — `Button(variant: primary, size: lg, state: loading)` — not as new components.
- Design the states a user will actually meet:
  - **Buttons:** default, hover, pressed, focus-visible, disabled, loading.
  - **Inputs:** default, focus, filled, error, disabled (plus success when validation is asynchronous).
  - **Lists and tables:** loading (skeleton), empty, no results, error with retry, populated, selected.
  - **Navigation:** default, hover, active, collapsed or mobile.
  - **Custom multi-value controls** (tabs, segmented control, role switch): one explicit state per value.
- Show states in context on the real screens — an error on the form, skeleton rows on the list. For handoff to a coding agent, also provide a compact **state sheet**: one frame per component family, not a re-capture of whole pages, each state named `component_state` (`button_loading`, `input_error`, `role-toggle_admin-selected`). Natively in Figma, build each state as a frame and use Combine as Variants.
- Transitions get a written spec, not enumerated frames (Appendix E).
- Keep the component set as small as the spec allows. Don't add modals, variants or states without a user or technical reason.

## 10. Accessibility `[HARD]`

- Contrast: 4.5:1 for body text; 3:1 for large text and for any boundary or icon that carries meaning (an input's border counts).
- Color is never the only carrier of meaning — pair it with an icon, label or pattern.
- Visible keyboard focus on every interactive element.
- Semantic structure and real accessible names. Labels are not placeholders; errors are tied to their fields.
- Touch targets ≥ 44×44 px.
- Respect reduced-motion preferences (replace movement with a fade, or nothing).
- Text stays usable at 200% zoom and at larger system font sizes.

## 11. Content, language and UX writing `[DEFAULT]`

- **Realistic, domain-specific content.** Fill every table, card and chart with sample data that matches the schema: plausible names, amounts, dates, statuses and avatars; long and short values; zero and large numbers. Empty or lorem-ipsum screens look broken and hide layout problems. What to avoid is fabricated *claims* presented as fact (customer logos, testimonials, awards, "10,000+ users") on marketing surfaces — use clearly marked placeholders or omit the block.
- **Language and locale.** Write UI copy in the language of the brief or spec, and format numbers, currency, dates, phone numbers and names for that locale (for Indonesian: "Rp 1.250.000", "12 Okt 2026", "+62 812-3456-7890").
- **Labels name the exact action** ("Save changes", "Create invoice") in active voice and sentence case. An action keeps its name through the whole flow: "Publish" leads to "Published".
- **Errors and empty states** say what happened and what to do next, in the product's voice — no blame, no filler like "Oops!". An empty state is an invitation with one clear action.
- **Plain words, specific copy.** Name things the way users do, not the way the system is built. Skip hype ("seamless", "unlock the power") and keep chat or prompt language out of the UI.

## 12. Scope, conflicts and incomplete briefs `[DEFAULT]`

- **Complete means** every screen and state the spec needs, at full visual quality — not a skeleton, and not a wireframe in color.
- **Match the register.** Don't produce a landing page when the spec describes an app, or a dashboard when it describes a landing page. Don't add pricing tables, testimonials, onboarding tours or sign-in to a tool that doesn't need them.
- **Fewer features, not plainer visuals.** If a "simple landing page" is ballooning into 14 components and 6 modals, cut the features and states you can't justify — and keep the visual polish.
- **Authority order for visual decisions:** (1) an existing design system or codebase tokens → (2) explicit instructions in this task → (3) the Style Override in Appendix G → (4) this file's defaults. The brief's own words win even when they ask for a look this file wouldn't choose. Note any deviation in one line.
- **Ambiguity:** state a one-line assumption and continue.
- **Consistency over time.** Before each new screen or revision, re-read the Design Notes and tokens and reuse them. Don't drift back to generic defaults as the session grows.

## 13. Review and delivery `[CHECK]`

Review every screen at three widths (≈390, 768, 1280) with realistic data before calling it done.

- [ ] **Spec** — every entity, field, action, role and state in the spec appears somewhere; terms match the spec; nothing invented without being flagged.
- [ ] **Focal point** — one clear primary element per screen; hierarchy reads in a squint test (first, second, third).
- [ ] **No voids** — no large, purposeless empty area (§5).
- [ ] **No text walls** — every screen has a visual anchor; prose is structured.
- [ ] **Consistency** — one radius personality, one shadow recipe, one icon family; spacing and colors only from tokens.
- [ ] **Comfort** — body ≥ 14px, line length ≤ 75 characters, contrast passes, alignment on the grid, nothing touching container edges, no odd or hard-to-read fonts.
- [ ] **Responsive** — resized across the whole range; navigation and tables transform as documented.
- [ ] **States** — interactive components and data views show their real states, not only the default.
- [ ] **Accessibility floor** (§10) is met.
- [ ] **Content** — realistic, in the right language and locale; no lorem ipsum.
- [ ] **Would you trust it?** — compare it with a mature product in the same category. If it looks like a wireframe, add polish (§6). If it looks cluttered, remove the least useful decoration first.

**Deliver Design Notes with the design** (10 lines at most, in chat or in a frame):

```
Design Notes
- Register · archetype: Product · Admin/back-office (runner-up: Analytics SaaS)
- Dials: Density 7 · Expressiveness 3 · Motion 2
- Tokens: brand #0F766E · slate neutrals · Inter · radius 8 · shadows sm/md/lg
- Assumptions: …
- Added screens (not in spec): …
- Backend needs: …
- To restyle: say "warmer", "denser", "dark mode" or "more playful" — only tokens change (Appendix F).
```

---

# PART 2 — REFERENCE APPENDICES

## Appendix A — Archetypes and dials

Pick the closest archetype (§4), set the three dials from it, and adjust by at most ±2 with a stated reason.

- **Density** — 1–3 airy (reading, marketing) · 4–6 balanced (everyday apps) · 7–10 dense (cockpit, tables).
- **Expressiveness** — 1–3 utility (familiar layouts, restrained color) · 4–6 polished with character · 7–10 bold, editorial.
- **Motion** — 1–2 feedback only · 3–5 plus subtle transitions and one entrance · 6–10 rich (Brand surfaces only).

| Archetype (tone reference) | D / E / M | Palette and shape | Type | Typical visual anchors |
|---|---|---|---|---|
| **Admin, back-office, school or company management** (Stripe Dashboard, Linear, Vercel) | 7 / 3 / 2 | Slate neutrals + blue, indigo or teal accent; 8px radius; 1px borders + sm shadow | Inter, Geist or Public Sans | Icon sidebar, filter chips, tables with avatars and status chips, KPI tiles |
| **Analytics, SaaS dashboard, CRM** (Plausible, PostHog, HubSpot) | 6 / 4 / 3 | Neutral + one vivid brand (blue, violet or teal); 12px radius | DM Sans, Manrope or Inter; tabular numerals | KPI cards with deltas, line and bar charts, activity feed |
| **Education, LMS, student portal** (Duolingo, Khan Academy, Google Classroom) | 4 / 6 / 4 | Friendly teal or blue + a warm accent (orange, yellow); soft tinted backgrounds; 16px radius | Nunito Sans, Plus Jakarta Sans or Figtree | Course cards with cover art, progress rings and bars, badges, illustration, larger readable type |
| **Consumer app, social, community** (Instagram, Pinterest) | 4 / 7 / 5 | Saturated brand + a playful accent; 16–24px radius | Plus Jakarta Sans, Outfit or Poppins | Avatars, image-first cards, reactions, bottom tabs |
| **E-commerce, marketplace, catalog** (Tokopedia, Shopee, Etsy) | 5 / 5 / 4 | Clean white or stone canvas so products lead; one CTA color; 8–12px radius | DM Sans or Inter | Product-image cards, price emphasis, ratings, filter sidebar, sticky cart |
| **Fintech, banking, accounting** (Wise, Revolut, Stripe) | 5 / 3 / 2 | Deep navy, blue or teal; green = positive, red = negative; 8–12px radius | IBM Plex Sans or Inter; tabular numerals | Large balance figures, right-aligned amounts, clear statements, security cues |
| **Healthcare, clinic, wellness** (One Medical, Halodoc) | 4 / 3 / 2 | Calm blue, teal or green on white; 12px radius; 48px targets | Source Sans 3, Nunito Sans or Public Sans | Appointment cards, status timelines, large form fields, clear alerts |
| **Developer tool, API, DevOps, monitoring** (GitHub, Vercel, Railway) | 8 / 3 / 2 | Dark-first (offer light); zinc neutrals + one accent; 6–8px radius | Geist or Inter + JetBrains Mono | Logs, status dots, code blocks, command palette, tables |
| **Booking, travel, food, hospitality** (Airbnb, Traveloka) | 4 / 7 / 4 | Warm, appetizing palette (terracotta, amber, forest, ocean); 12–20px radius | DM Sans; optionally Fraunces or DM Serif Display for headings | Photo-forward cards, maps, ratings, date pickers, booking summary |
| **Productivity, collaboration, tasks** (Notion, Trello) | 6 / 4 / 3 | Neutral + a soft accent; 8px radius | Inter or Geist | Kanban and list views, avatars, due-date chips, hover actions |
| **Content, media, blog, docs** (Medium, Substack) | 3 / 5 / 2 | Warm white; optional serif body; 60–70 character measure | Source Serif 4, Lora or Newsreader + a sans for UI | Cover thumbnails, author avatars, category chips |
| **Logistics, POS, field operations, kiosk** | 5 / 2 / 1 | High contrast; targets ≥ 48px; strong status colors; 8–12px radius | Inter or Roboto Flex | Big numeric keypad, scan states, offline indicator, large primary buttons |
| **Government, public service, NGO** (GOV.UK) | 4 / 2 / 1 | Conservative blue or green + neutral; very high contrast; 4–8px radius | Public Sans or Source Sans 3 | Stepper forms, clear status pages, plain-language help |
| **Brand: landing, portfolio, campaign** (Brand register) | 3 / 7–9 / 4–6 | Bolder palette; one signature element; imagery | A display face + a readable body face | Hero visual, benefit strip with icons, alternating feature sections |

Tone references are for mood only — don't copy logos, colors or trade dress. If the audience is the general public on phones, assume mid-range Android devices, uneven connectivity and bright light unless the brief says otherwise: favor high contrast, large targets and light assets.

## Appendix B — From system spec to screens

### B1. Derive the screens

| Spec element | Becomes |
|---|---|
| Entity | List page + detail page + create/edit form (only the ones its operations imply) |
| Relationship | Links between detail pages; a related-items tab or panel; a searchable select in forms |
| Role or permission | Role-specific navigation and actions; role badge in the profile; hide what a role should never know about, disable-with-tooltip what it could get access to |
| Status enum or state machine | A status chip per value; allowed transitions as buttons (only valid next states); a history timeline |
| Multi-step flow | Stepper or wizard with progress, back/next, per-step validation and a review step |
| Async job, webhook, import | Progress or status chip, refresh or polling, cancel and retry, completion toast, failure detail |
| Aggregates and metrics | KPI tiles, charts, and the table behind the numbers |
| Notifications and events | Bell with unread count, notification list, per-type preferences |
| Validation rule or error code | Inline field message, or a banner with a next action |
| Config and profile data | Settings page grouped by topic |

### B2. Navigation model

- Up to 5 top-level sections: top bar on desktop, bottom tabs on mobile.
- 6–12 sections: grouped sidebar on desktop; bottom tabs for the top 4–5 plus "More" on mobile.
- More than 12: sidebar with collapsible groups plus search or a command palette.
- Group by the user's tasks, not by database tables.

### B3. Field type → component

| Data in the spec | Component |
|---|---|
| Enum or status | Colored chip (color + icon + label) in lists; select, segmented control or radio in forms |
| Boolean | Toggle for settings; checkbox for agreement or selection |
| Short text | Input with label, helper text, inline error; character count when there is a limit |
| Long text | Auto-growing textarea; truncate with "show more" in lists |
| Number or quantity | Numeric input or stepper; right-aligned with tabular numerals in tables |
| Money | Currency prefix, locale format (for example Rp 1.250.000), right-aligned, tabular |
| Date or time | Date picker; relative time ("2 jam lalu") with the exact value on hover |
| Date range | Range picker with presets |
| Relation (foreign key) | Linked chip or avatar + name that opens the detail; searchable select in forms |
| Many-to-many | Multi-select chips or a tag input |
| Image or avatar | Avatar with initials fallback; thumbnail grid; uploader with preview and progress |
| File | File row (type icon, name, size, download); dropzone |
| Email, phone, URL | Typed input with validation; copy and open actions |
| Rating | Stars or a number with a count |
| Progress or percentage | Bar or ring with a label |
| Time series | Line or area chart; KPI tile with delta |
| Category breakdown | Bar chart or donut (up to 5 slices) |
| Geo location | Map with pin and address |
| Hierarchy | Tree, breadcrumb or nested list |
| Large list (> 20 items) | Search + filters + sort + pagination (or infinite scroll) + bulk select |
| Long-running job | Progress + status chip + cancel/retry + toast on completion |
| Error code | Mapped to an inline field error or a banner with the next step |

## Appendix C — Starter tokens

An example starting point for the **Modern product** preset (blue). Swap the brand hue per archetype, keep the lightness steps, and recheck contrast. For light hues (yellow, lime, amber) use dark text on the brand color instead of white.

```css
:root {
  /* Brand — example: blue */
  --brand-50:#EFF6FF;  --brand-100:#DBEAFE; --brand-200:#BFDBFE;
  --brand-500:#3B82F6; --brand-600:#2563EB; --brand-700:#1D4ED8; --brand-900:#1E3A8A;

  /* Neutrals — slate (slightly cool); warm products use stone */
  --bg:#F8FAFC; --surface:#FFFFFF; --surface-2:#F1F5F9;
  --border:#E2E8F0;          /* decorative borders: cards, dividers */
  --border-input:#7B8BA3;    /* controls whose edge is the only affordance (>= 3:1) */
  --text:#0F172A; --text-2:#475569;
  --text-3:#64748B;          /* captions, hints — only on --surface and --bg */
  --focus:#2563EB;           /* 2px ring + 2px offset */

  /* Semantic — text shade + tinted background */
  --success:#15803D; --success-bg:#DCFCE7;
  --warning:#B45309; --warning-bg:#FEF3C7;
  --error:#B91C1C;   --error-bg:#FEE2E2;
  --info:#1D4ED8;    --info-bg:#DBEAFE;

  /* Radius */
  --r-sm:6px; --r-md:10px; --r-lg:16px; --r-full:999px;

  /* Elevation */
  --shadow-sm:0 1px 2px rgba(15,23,42,.06), 0 1px 3px rgba(15,23,42,.08);
  --shadow-md:0 4px 12px rgba(15,23,42,.08);
  --shadow-lg:0 16px 40px rgba(15,23,42,.14);

  /* Spacing: 4 · 8 · 12 · 16 · 24 · 32 · 48 · 64 · 96 */
}
```

The text, text-2 and semantic pairs above, white on brand-600/700, and `--border-input` against `--surface` and `--bg` were checked against WCAG AA (4.5:1 for text, 3:1 for boundaries).

**Type scale**

| Token | Desktop (size/line) | Mobile | Weight | Note |
|---|---|---|---|---|
| display | 48/56 | 36/44 | 700 | Page heroes and Brand surfaces; tracking −0.02em |
| h1 | 36/44 | 28/36 | 700 | |
| h2 | 28/36 | 22/30 | 600 | |
| h3 | 20/28 | 18/26 | 600 | |
| body-lg | 18/28 | 17/26 | 400 | Reading surfaces |
| body | 16/24 (dense apps 14/20) | 16/24 | 400 | |
| body-sm | 14/20 | 14/20 | 400 | |
| caption | 12/16 | 12/16 | 500 | Never smaller than 12 |
| label | 14/20 | 14/20 | 500–600 | Buttons, form labels |

**Control sizes** — buttons and inputs 32–36 (sm), 40 (md; 44 on touch), 48 (lg); table rows 36–40 (dense) or 48–56 (comfortable); avatars 24 / 32 / 40 / 56.

**Dark mode** (when requested, or for developer tools) — remap neutrals to a near-black blue-gray, separate surfaces by one or two lightness steps instead of shadows, use lighter brand steps (400–500) for accents, use off-white text rather than pure white, and recheck every pair.

## Appendix D — Screen recipes

Compositions that work as a starting point. Fill every block with data from the spec.

- **Sign in / sign up** — Desktop: split screen. A brand panel (about 45–50% width: gradient or tinted background, logo, one-line value statement, 2–3 icon bullets or an illustration) beside a form card of at most 400px, vertically centered. Mobile: brand header strip + form. Label above the field, show/hide password, inline errors, a full-width primary button with a loading state, and secondary links (forgot password, create account). Include social sign-in or SSO only if the spec has it.
- **Dashboard / home** — Page header (greeting or title, date or filter control, primary action). A row of 3–4 KPI tiles (icon in a tinted square, number, label, delta chip). Main grid: a chart at 2/3 width beside a "needs attention" or activity list at 1/3. Below: a recent-items table or card grid with "View all".
- **List / table page** — Header (title, total count, primary "Create …" button). Toolbar: search, filter chips, sort, view toggle. Table: sticky header; first column with avatar or thumbnail + name + a secondary line; status chip column; right-aligned numeric columns; row action menu; hover highlight; selection checkboxes with a bulk-action bar; pagination with page size. States: skeleton rows, empty (illustration + create action), no results (clear filters), error (retry). Mobile: cards or key-value rows.
- **Detail page** — Breadcrumb; header with title, status chip and primary and secondary actions; a summary strip of 3–5 key facts; tabs as the spec implies (Overview · Activity · Related); a main column plus a side column (metadata, owner, dates); history as a timeline.
- **Create / edit form** — Sections with a heading and a one-line description; 1–2 column grid on desktop (single column for long forms); labels above fields; helper text; required marks; inline validation from the spec; a sticky footer with Cancel and Save (loading state); an unsaved-changes guard; a stepper when the flow is sequential or has more than about 8 fields.
- **Settings / profile** — Left sub-navigation (tabs on mobile); each setting is a row of label + description + control; groups sit in cards; a danger zone in a red-tinted card with a confirmation step.
- **Empty state** — An illustration or tinted icon composition, a heading that names what lives here, one sentence of help, one primary action (and optionally a secondary link).
- **Error, 403, 404** — A friendly illustration, what happened, what to do next, one button back.
- **Landing page (Brand register, only when asked)** — Hero: a specific headline saying what the product does for whom, a sub-line, a primary CTA (and a secondary), and a product visual or illustration. Then a benefit strip (3–4 icon + short text), alternating feature sections (text ↔ visual), proof only if real data exists (otherwise omit or mark as placeholder), a closing CTA and a footer with real links. This classic skeleton is appropriate for SaaS; adapt it for portfolios and stores.

## Appendix E — Transitions and state sheet

Transitions are specified in a table attached to the two states they connect, not drawn frame by frame. The vocabulary matches Figma prototyping (trigger, action, Smart Animate) and maps directly to CSS transitions.

| From → To | Trigger | Property | Duration | Easing |
|---|---|---|---|---|
| Button: default → hover | Pointer enter | background, shadow | 120ms | ease-out |
| Button: default → pressed | Press | scale 0.98, background | 80ms | ease-out |
| Dropdown or accordion: collapsed → expanded | Click | height, opacity | 200ms | ease-out |
| Toggle: off → on | Click | thumb position, track color | 150ms | ease-in-out |
| Modal: closed → open | Click | opacity, translateY 8px → 0, backdrop | 200ms | ease-out |
| Toast: hidden → visible | Event | opacity, translateY | 200ms in / 150ms out | ease-out |
| Page load (Brand surfaces) | Load | opacity, translateY 12px → 0, staggered by 60ms | 400–600ms total | ease-out |

Under reduced motion: opacity only, or none.

**State sheet naming** — `component_state`, for example `button_loading.png`, `input_error`, `dropdown_expanded`, `role-toggle_admin-selected`. Scope each artifact to the component, or to its component group when the state only reads correctly in context (a role switch shown with all roles, one selected).

## Appendix F — Translating feedback

When the person reacts, change the matching lever and keep the tokens consistent.

| The person says | Do this |
|---|---|
| "Too plain / boring / empty / flat" | Expressiveness +2: tinted section bands, icons in tinted shapes, cover art or illustration, a brand-hue gradient on one hero element, richer cards (border + shadow), fill voids (§5), stronger type contrast |
| "Too busy / cluttered / noisy" | Density −1: remove decorative layers first (extra gradients, borders, shadows), increase group spacing, cut competing accent colors, collapse secondary content |
| "More modern" | Softer radius, lighter shadows, bigger type contrast, subtle glass or gradient accents, polished micro-interactions |
| "More professional / serious / corporate" | Expressiveness −2: deeper brand tone, 8px radius, flat shadows, fewer gradients, denser tables |
| "More fun / friendly / playful" | Rounder radius, a warm accent, illustration and friendly microcopy, bouncier motion (consumer and Brand surfaces) |
| "Feels weird / off" | Return to conventional structure and familiar fonts; check alignment, radius and spacing consistency, readable sizes; remove the single most unusual choice first |
| "Too much empty space / gaps" | Reduce section spacing one step, move to multiple columns, enlarge or add supporting content (§5) |
| "Hard to read" | Raise body size, contrast and line height; shorten the line length; calm any busy background behind text |
| "Make it dark / light" | Remap neutrals and surfaces; keep the brand hue; recheck contrast |
| "I don't like the colors" | Change only the brand hue and the tint of the neutrals; keep the structure |

## Appendix G — Style Override (optional)

Fill in what you know and leave the rest blank; blank lines are inferred (§4). Anything written here outranks this file's defaults (§12).

```
Product vibe (3 adjectives):
Reference products or sites you like (1–3):
Brand color(s):
Font(s):
Theme: light / dark / both
Density: airy / balanced / dense
Corners: sharp / balanced / soft
Language and locale:
Must have:
Must avoid:
```

## Appendix H — Code-output defaults

Applies only when the agent writes code and no stack is specified. An existing codebase's stack always wins.

- React + TypeScript + Tailwind CSS + shadcn/ui (Radix primitives); Lucide icons; Recharts for charts; fonts from Google Fonts.
- Map tokens to CSS variables and the Tailwind theme (shadcn's `--background`, `--foreground`, `--primary`, … convention) instead of hard-coding hex values in components.
- Semantic HTML (`nav`, `main`, `table`, `button`, `label`), `:focus-visible` styles, a `prefers-reduced-motion` media query, responsive utilities (`sm:` / `md:` / `lg:`), and no fixed pixel widths on containers.
- Realistic seed data in one typed module derived from the spec's schema, with loading, empty and error variants reachable through a prop or a mock state.
- Use local SVG placeholders or gradients instead of remote images that can break.

## Appendix I — References

These shaped the revision from v1.

- Anthropic — *Improving frontend design through Skills* — https://claude.com/blog/improving-frontend-design-through-skills
- Anthropic — *Prompting Claude Sonnet 5*, "Design and frontend defaults" — https://platform.claude.com/docs/en/build-with-claude/prompt-engineering/prompting-claude-sonnet-5
- Anthropic — *Prompting best practices* — https://platform.claude.com/docs/en/build-with-claude/prompt-engineering/claude-prompting-best-practices
- Google Labs — DESIGN.md specification — https://github.com/google-labs-code/design.md · https://stitch.withgoogle.com/docs/design-md/specification
- Figma — guidelines for Figma Make — https://developers.figma.com/docs/code/write-design-system-guidelines/
- Laws of UX — Aesthetic-Usability Effect and Jakob's Law — https://lawsofux.com
- Impeccable — brand vs. product register — https://impeccable.style/tutorials/brand-vs-product
- Taste Skill — the three-dial approach — https://github.com/Leonxlnx/taste-skill
- UI UX Pro Max — design-system generation by product type — https://github.com/nextlevelbuilder/ui-ux-pro-max-skill
- Wathan & Schoger — *Refactoring UI* — https://refactoringui.com
- Managed Code — *AI in UI Design Without the Purple Slop* — https://managed-code.com/blog-post/ai-slop-in-design
- AskCodi — *The AI UI Problem Nobody Talks About* — https://askcodi.substack.com/p/the-ai-ui-problem-nobody-talks-about
- Nick Babich (UX Planet) — *7 DESIGN.md Mistakes That Make AI-Generated UI Worse* — https://uxplanet.org/7-design-md-mistakes-that-make-ai-generated-ui-worse-9ec2dfcc44cd

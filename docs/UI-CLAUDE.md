# UI-CLAUDE.md — private UI working notes (Claude only)

Written 2026-11-06 01:30 WITA, read-only pass. Re-verify against source before acting; these are notes, not authority.
Authority order for UI: Ren explicit > AGENTS.md §C > PRD/blueprint (WHAT exists) > docs/UI.md (HOW it looks) > Figma exports (visual reference, not law) > docs/DESIGN.md (fallback only when UI.md silent).
Do NOT start polishing until Ren says so.

---

## 0. Hard rules (never violate)

| # | Rule | Source |
|---|---|---|
| H1 | Payment technical panel (`payments/_panel.blade.php`: HTTP traces, callback, cURL, timeline) is HIDDEN by default. Reveal = 5 taps/clicks on the "⚠ SIMULASI" badge within 3 s → small centered popup with only "Lihat detail teknis" + ×; pressing it swaps popup content to the full panel. Flag in `sessionStorage` (per tab/session). No hint text anywhere in UI. Panel stays in DOM (tests PAY-37..42/50/52/54 must stay green unmodified). Blade+JS+CSS only, no backend changes, no new deps. Esc/backdrop/× close, focus into popup, aria label. | docs/prompt-ui-panel-teknis-tersembunyi.md |
| H2 | Everything simulated carries text label SIMULASI/SIMULATOR + icon (not color only). Never remove a SIMULASI label for aesthetics. | UI.md §1.4, §2.4, §10.1 |
| H3 | All sample/mock data is fictional; never present as real Ira sales/customers. | UI.md §1.5, §14; Workflow §10 |
| H4 | Timezone WITA (Asia/Makassar, UTC+8). Store UTC, display WITA via `config('ira.reporting_timezone')`. Format `Rp 1.250.000`, `12 Okt 2026`. | UI.md header, §18 |
| H5 | No buyer profile/settings screens, no dark mode, no product photos, no bank/e-wallet logos or brand colors, no social login, no `$`, no NovaApp/Sarah/Jessica/upsell. | UI.md §15, §20 |
| H6 | Status = color + icon + text label (StatusChip). Shadow never the only state cue. | UI.md §2.4, §4.3, §13 |
| H7 | Stack: Blade + Tailwind v4 + Alpine + Chart.js bundled (no CDN, no React/Recharts, no starter kit). Never paste Figma React code; port. | UI.md header, §17; figma-mapping |
| H8 | Store location = Gorontalo (footer). Buyer data scope = all Indonesia. | Ren 2026-11-06 |

---

## 1. Visual system (UI.md = source)

### 1.1 Color tokens (already in `resources/css/tokens.css` as CSS vars)
- Primary `#6655F5` · hover `#5745E8` · active `#4938D4` · light `#E8E5FF` (selected/nav active) · soft `#F0EEFF` · dark `#39318F` (not in tokens.css yet).
- Gradients (accent only): primary `135deg #8172FF→#5946EF` (primary button); lavender `#F0EEFF→#DCD9FF`; blue `#E8F1FF→#D5E5FF`; purple `#EDE6FF→#D9CCFF`; cyan `#E1F8F7→#C9F0EF` (category placeholders).
- BG `#E9EBF8`, BG soft `#EFF0FA`, surface `#EAECF8`, surface light `#F4F5FC` (tables/charts), surface dark `#DDE1F1` (inset/track), surface hover `#E4E7F6`.
- Text: primary `#171A36`, secondary `#515A8C`, muted `#858CAD`, disabled `#AEB3CA`; extra per-element: secondary-2 `#737B9F`, placeholder `#9299B6`. Border subtle `#DCE0F0`, divider `#D7DBED`.
- Accent: blue `#438BFA`/`#DCEBFF` info; purple `#8875FA`; cyan `#55C9D5` data; green `#25B993`/`#D9F5EC` success; orange `#F5A443`/`#FFF0DA` warning + SIMULASI; red `#E76B82`/`#FCE4E9` error/destructive.
- Every new text/bg pair → check WCAG AA (4.5:1 normal, 3:1 large/UI). Orange text on orange-light likely FAILS 4.5:1 → needs darker text (finding F7).
- Page titles always text-primary, never accent.

### 1.2 Typography
Manrope = headings, nav labels, buttons, stat numbers. Inter = body, forms, tables, meta.
| el | size/weight/lh |
|---|---|
| Display | 40/800/1.15 |
| H1 | 32/800/1.2 |
| H2 | 26/750(700-800)/1.25 |
| H3 | 21/700/1.3 |
| H4 | 18/700/1.35 |
| Body L | 16/400/1.6 |
| Body | 14/400/1.6 |
| Body S | 13/400/1.5 |
| Caption | 12/400/1.4 |
| Button | Manrope 14/700/1.2 |
| Statistic | Manrope 28/800/1.2 |
Min important text 13–14px; body 16, dense tables 14. Font loading (Google vs local) = open (UI.md §19.7).

### 1.3 Radius / spacing / shadow
- Radius: xs 6 · sm 10 (badge) · md 14 (input/button) · lg 20 (card) · xl 24 (big panel, modal) · 2xl 30 (hero) · full (pill/avatar). Practical: button 14–18, card 20–24, modal 24, input 14–16.
- Spacing 4-base: 4/8/12/16/20/24/32/40/48/64. Card padding 20–24, section gap 32–48, field gap 16.
- Shadows: xs `3 3 6 #D2D6E7, -3 -3 6 #FFF`; sm `5 5 10 #D1D5E7, -5 -5 10 #FFF` (buttons); md `8 8 16 #CDD2E5, -8 -8 16 #FFF` (cards); lg `12 12 24 #CBD0E3, -12 -12 24 #FFF` (panels/modals); inset `inset 4 4 8 #D1D5E7, inset -4 -4 8 #FFF` (inputs, search, selected, progress track).
- Button states: default sm → hover `3 3 7` + lift → pressed inset `3 3 6` + scale .97 → focus-visible Primary outline w/ offset → disabled opacity+cursor → loading spinner + label, size kept, double-submit blocked.
- Tables/charts: NO heavy neumorphism. Clean surface-light, thin dividers, clear header.
- QR area: plain white, no shadow/gradient inside or around modules; shadow only on outer card.

### 1.4 Icons
Lucide outline only; 20 default /16 /24; stroke 1.8–2. Payment: `qr-code` QRIS, `wallet` e-wallet, `landmark` VA; method name as text.

### 1.5 Motion (Motion.md via UI.md §7)
Durations: 100 instant /150 hover /200 dropdown-toggle /300 modal-accordion /450 card entrance /600 reveal /800 hero(rare). Easing vars `--ease-standard|smooth|enter|exit` (only standard is in tokens.css). Page entrance fade + Y8→0 350ms. Modal: overlay 200 + panel scale .95→1, Y12→0, 250. Stagger 60–120ms, total ≤ ~400, related groups only. Reveal via `data-reveal` + IntersectionObserver (0.12, -40px bottom), once. `prefers-reduced-motion` → 0.01ms, no transforms. Animate transform/opacity only. Alpine `x-show/x-transition/x-cloak` for dropdown/modal/accordion/toggle/tooltip. Countdown not announced per second; status changes + copy via polite `aria-live`.

### 1.6 Component inventory (build as Blade components, variants not per-screen copies)
`Button(variant: primary|secondary|destructive, size, state)`, `Input(state)`, `StatusChip`, `DataTable`, `StatCard` (Manrope 28/800 + trend icon+text e.g. "Naik 12%" + optional sparkline), `EmptyState` (icon + text + one action), `Dialog` (24 radius, lg shadow, focus trap, Esc, specific labels), `Toast`, `Countdown`, `CopyButton`, `JsonPanel`, `SimBadge`, `SimBanner`, `SegmentedControl` (period quick picks; Admin status tabs), `Stepper` (qty, ≥44px).
Target files (UI.md §17): `resources/views/components/{button,card,input,modal,dropdown,accordion,status-chip,stat-card,empty-state,sim-badge,sim-banner,countdown,copy-button,json-panel}.blade.php`; JS `resources/js/animations/{reveal,scroll-progress,page-interactions}.js`. None exist yet.
- Card: surface, md shadow, radius 20–24; interactive hover Y-4 200ms (`hover:hover` only).
- Input: inset, radius 14–16, h 44–48, visible label always, error via `aria-describedby`.
- Nav active: primary-light bg + primary icon + text-primary label.
- Destructive confirm: explain impact; reset simulator = type phrase, button disabled until match.
- Labels: specific Indonesian ("Bayar Sekarang (Simulasi)", "Simulasikan Gagal", "Kembali ke Pesanan", "Batalkan pesanan", "Simpan perubahan"); never "Submit"/"OK".

### 1.7 Four surfaces (must be distinguishable at a glance)
| Surface | Character | Chrome | Nav desktop / tablet / mobile |
|---|---|---|---|
| Storefront (pembeli) | friendly, product-first, most motion | top bar: brand + search + Katalog/Keranjang/Pesanan/Pembayaran/Akun; no sim marks except payment | top bar / same / bottom tabs ≤5 |
| Admin Toko | dense, tables+charts | grouped sidebar: Analitik(Dashboard,Tren,Funnel,Perbandingan,Aktivitas & Minat,Kategori) · Toko(Produk,Kategori produk) · Transaksi(Pesanan,Pembayaran) · Akun(Akun pembeli). No simulator chrome; SimBadge only on sim rows | sidebar / icon rail / 4 bottom tabs + "Lainnya", tables→cards, filters→bottom sheet |
| Panel Developer | utilitarian, densest | permanent non-dismissable SimBanner "SIMULATOR — data eksperimental"; sidebar color ≠ Admin; every page title prefixed "Simulator — …" | 8-item sidebar (Beranda, Buat Akun, Transaksi per User, Transaksi per Periode, Funnel, Akun Simulasi, Jejak/Audit, Reset) / rail / bottom tabs + "Lainnya" |
| Payment sim page (token) | mobile-first, single column, high contrast | big "SIMULASI PEMBAYARAN" above fold | centered card / same / full column, actions sticky near bottom not covering summary/countdown |
Breakpoints check: ~390 / 768 / 1280. Touch ≥44×44. Sticky header may shrink (300ms), never hide nav.

### 1.8 Patterns per screen type
- **Auth**: split layout — brand panel (lavender/blue gradient accent) + form card ≤400px (surface, md shadow, r24), inset inputs w/ left icons, eye toggle, gradient primary button w/ arrow. ONE form at `/masuk` with actor switch (pembeli | admin_toko | developer) — Ren 2026-11-05, overrides Figma's per-role screens. Register = pembeli only.
- **Storefront list (katalog)**: hero band (lavender gradient), search inset, category sidebar, product card grid (category gradient placeholder + icon tile + category eyebrow + name + Rp price in primary-dark + stock line + "Tambah" secondary button; out-of-stock → "Stok habis" red + disabled "Habis"). States: skeleton, empty, not-found w/ suggestions, error.
- **Cart**: sticky summary desktop, below on mobile; qty Stepper with visible subtotal feedback; problem items (stock, inactive).
- **Checkout**: summary + 6 method cards (QRIS, Dana, GoPay, VA BRI, VA BCA, VA BNI) generic icon + text + SimBadge; selected = primary-light + primary outline + light inset; no method preselected? (see A9); double submit blocked.
- **Instruction**: QR ≥240 white SVG w/ SIMULASI ribbon outside modules, `<title>` contains SIMULASI; VA mono tabular grouped + copy w/o spaces + "Nomor VA SIMULASI — bukan nomor rekening"; countdown mm:ss; loopback/APP_URL warning.
- **Sim page**: label → summary (amount, method, ref) → countdown → 3 actions → (panel, now hidden per H1). Final status replaces actions with status message + next link. States: pending, success, failed, expired, final/reused, not found.
- **Result** (berhasil/gagal/kedaluwarsa): result card + summary + next actions. **Struk**: print CSS, SIMULASI printed.
- **Admin list**: filter row (inset search + selects/SegmentedControl) on top, filters in URL, DataTable clean, action column always visible, pagination, empty/no-result/error+retry, mobile → cards.
- **Admin form**: standard card form, validation per field, destructive confirm dialog.
- **Admin analytics (FR-01..06)**: identical period + data-origin filter (semua/normal/simulasi) on top, in URL; chart on surface-light; each chart = axes w/ units, tooltip, legend, accent palette, "Lihat sebagai tabel", "Lihat sumber"; states: loading, empty, "data belum cukup" (distinct, explains why + what to do), service error + retry. Donut only ≤5 slices. Dashboard: 2 KPI StatCards + trend chart + top/bottom sellers (composition from visual-reference: greeting + date context, KPIs w/ sparkline, right column quick actions/perf/categories — composition only).
- **Developer write actions**: segmented form → human-readable preview (not raw JSON) → confirm → tracked result. Limits shown BEFORE submit (100 accounts/request, tx/event caps, ≤366 days). Reset: warning bg, exact list of what is / is NOT deleted, typed phrase, before/after counts.
- **System**: 403/404/419/429/500 Indonesian msg + one back button; toast icon+text auto-dismiss but readable.

### 1.9 DESIGN.md fallback — only use when UI.md silent
Generic: spec fidelity, every enum has a look, mark assumptions, a11y floor, states for every screen. IGNORE its "add profile/settings screens" default (§2) — UI.md/PRD forbid. Ignore its starter tokens (Appendix C) — UI.md tokens win.

---

## 2. Current repo state vs Figma (as of commit c0832f3, M3c merged)

Global: views are functional M0–M3 skeletons — bare semantic HTML, a few classes (`.card .btn-primary .input .badge-simulasi .alert-*`) in `tokens.css`. No Blade components, no Lucide, no Alpine usage in views, no responsive nav, no sidebar. So EVERY existing screen deviates visually from Figma; content/structure mostly matches PRD. Deviations below are beyond "not yet polished".

| Route / view | Figma export(s) | Match | Notable deviations / why |
|---|---|---|---|
| `/masuk` auth/masuk | masuk-pembeli-*, login-admin-* | structure ≠ (intended) | Unified form + actor switch (links + radio) per Ren 2026-11-05; Figma has per-role screens. Polish: one split-layout card, actor switch as SegmentedControl, CTA text per actor ("Masuk sebagai pembeli"). No Figma for developer login. |
| `/daftar` auth/daftar | daftar-pembeli-* | partial | Only name/email/password. WILL CHANGE (§4 buyer fields). Figma fields not yet compared in detail. |
| `/katalog` shop/katalog-index | katalog-*, keadaan-katalog-keranjang-checkout-mobile | content ok, visual none | No hero, no card grid/gradient placeholders, no category sidebar styling, no skeleton. |
| `/produk/..` shop/produk-detail | detail-produk-* (listed under Admin in mapping but it is the storefront PDP) | partial | No Stepper, no placeholder art. Mapping groups it under "Admin — Katalog & stok" — likely misfiled (A11). |
| `/keranjang` shop/keranjang-index | keranjang-* | partial | No sticky summary. |
| checkout shop/checkout-show | checkout-* | partial | Radios, first method `@checked` by default → conflicts with "belum memilih metode" state (UI.md §9.2, Workflow W4: button disabled until chosen). Finding F4. No method cards/icons/SimBadge per card. |
| instruction payments/instruction-show | instruksi-qr-*, instruksi-va-* | content ok | VA copy uses inline `navigator.clipboard` onclick (no LAN fallback, unlike `[data-copy-target]` helper) — F5. |
| `/pay/sim/{token}` payments/sim-show | none dedicated (closest: keadaan-pembayaran-pesanan-mobile) | content ok | Panel currently visible below → must hide (H1). Page has no "⚠ SIMULASI" badge element; the trigger target is ambiguous (A2). |
| payment detail (buyer) payments/show | detail-pembayaran-* | conflict | Figma shows "Jejak HTTP simulasi" as the main left column, fully visible. H1 overrides Figma: trace hidden; summary card becomes primary content. |
| payment list payments/index | riwayat-pembayaran-* | partial | — |
| result payments/hasil-show | hasil-berhasil/gagal/kedaluwarsa-* | partial | 3 variants in one view. |
| struk payments/struk-show | struk-simulasi-* | partial | Verify print CSS exists. |
| orders shop/pesanan-index/detail | riwayat-pesanan-*, detail-pesanan-* | partial | Timeline styling missing. |
| admin dashboard `/admin` → placeholder.blade | dashboard-analitik-* | NOT BUILT | Analytics M5–M7. |
| admin kategori index/form | kategori-produk-*, tambah-/ubah-kategori-* | partial | — |
| admin produk index/form/stok/riwayat | produk-*, tambah-/ubah-produk-*, atur-stok-*, riwayat-stok-* | partial | — |
| admin pesanan index/detail | none dedicated (state sheets only) | no ref | Build from UI.md Admin list/detail pattern. |
| admin pembayaran index/detail | none dedicated (detail-pembayaran is buyer-side w/ storefront top bar) | no ref | Hide panel per H1 (prompt lists admin page too — A1). |
| admin akun-pembeli index/detail | none | no ref | Uses native `confirm()` for deactivate, not Dialog (F6). WILL CHANGE (§4). |
| developer `/developer` → placeholder; developer/akun-simulasi-index | NONE (Developer absent from Figma) | no ref | Build from UI.md §9.5 + Workflow §7.3/W8–W11 + PRD. Currently no SimBanner, no "Simulator —" title prefix (F3). |
| layouts/app.blade | top bars / sidebars in all frames | ≠ | Single generic header for all actors; no surface separation, no sidebar, no bottom tabs. Footer "Gorontalo • WITA" OK. |

Figma frames with no repo screen yet: all 12 analytics frames (+2 state sheets), dashboard. Figma has NO developer frames, NO admin order/payment/buyer-account frames, NO error-page frames beyond `keadaan-akun-sistem-mobile`.

---

## 3. Ambiguities / undecided (ask Ren before guessing)

| ID | Question | Why |
|---|---|---|
| A1 | Hidden panel on ADMIN payment detail too? | Ren's rule says "not visible to ordinary buyers"; the prompt file explicitly lists admin page among 3 pages to hide, while UI.md §10.4/Workflow say Admin shows it read-only. Default: follow prompt file (hide on all 3) unless Ren says otherwise. |
| A2 | Trigger badge on sim page | Prompt says tap "⚠ SIMULASI" badge on each page; `sim-show` has `<h1>SIMULASI PEMBAYARAN</h1>` + "SIMULASI — bukan…" but no "⚠ SIMULASI" badge; `show`/admin have "⚠ SIMULASI — bukan pembayaran riil." strong text. Which element is the hook? Adding a visible badge = adding UI (prompt says no visible change). |
| A3 | UI.md §9.3/§10.4 & Workflow §7.4 still say panel HTTP sits visibly under actions | Superseded by prompt file + Ren rule; docs not updated (not my job to edit). Treat H1 as winning. |
| A4 | Panel Developer: all visuals (sidebar color distinct from Admin — which token? banner exact look, form segmentation layout) | No Figma. UI.md gives only rules. Propose before building. |
| A5 | M4 developer screens + M5–M7 analytics not built | UI direction not final; analytics Figma exists but data shapes/filters come from M5–M7 code. Don't pre-build. |
| A6 | Storefront "Akun" nav item | Figma + UI.md §12 list "Akun", but no profile page allowed. Is it a minimal menu (Keluar only)? With new buyer fields, does buyer get any view of own data? (Currently forbidden.) |
| A7 | Fonts: Google Fonts vs bundled | UI.md §19.7 open. `app.css @theme --font-sans` still 'Instrument Sans' (stock) while tokens use Manrope/Inter (not loaded anywhere). |
| A8 | Admin default data-origin (sim + normal together?) | UI.md §19.5 open. |
| A9 | Checkout default method | Code preselects first; spec wants an explicit "not chosen" state. Changing it is behavior → needs Ren + test check. |
| A10 | VA dummy format, expiry minutes final, sim page login requirement, post-failure order status | UI.md §19 open items. |
| A11 | `detail-produk-*` filed under Admin in figma-mapping | Probably storefront PDP. Confirm visually before mapping. |
| A12 | `Data_Pembeli_di_E-Commerce.md` | Not found in repo or parent dirs during this pass. Using field list from Ren's message only. |
| A13 | Figma text content unverified for most frames (figma-mapping §16) | Check each export for banned items before copying layout. |

---

## 4. Areas affected by NEW buyer data model (decided 2026-11-06 00:29 WITA — decided, not pending)

Decision: buyer fields live in `users` table (not separate sim profile). Required:
- Identity (§1): nama lengkap, username, email, nomor telepon, jenis kelamin, tanggal lahir, tanggal registrasi, status akun.
- Address (§2): provinsi, kota/kabupaten, kecamatan, kelurahan, kode pos, alamat lengkap, jenis alamat.
- §3 transaction history = existing orders/payments. §4+ optional.
- Scope all Indonesia; only the store is Gorontalo.
- Today `users` = name, email, + M1 cols (role, is_active, deactivated_at, deactivation_reason, origin, simulation_run_id). New columns come with the M4 migration.

**Marked "WILL CHANGE WITH NEW FIELDS" — do not finalize layout until M4 migration exists:**
| Area | Current | Expected impact (shape NOT assumed) |
|---|---|---|
| `auth/daftar` register | name/email/password | Many more fields → likely multi-section/stepped form (Identitas / Alamat); region cascade (provinsi→kota→kecamatan→kelurahan) — data source & UX undecided; date picker (birth date); gender control; address-type control. Validation messages per field. |
| `admin/akun-pembeli-index` | Nama, Email, Status, Asal, Tanggal daftar | Possibly username/phone/kota columns; search may extend; mobile cards. Keep dense. |
| `admin/akun-pembeli-detail` | Nama/Email/Status/Asal/order count | Sections: Identitas, Alamat, Riwayat transaksi (link to orders/payments). Privacy: show only what admin needs (AGENTS §9). |
| Developer "Buat Akun" (M4) | not built | Generator must fill all required fields (random/deterministic, seed); preview must show field sample in readable form, Indonesia-wide regions, all FICTIONAL + SIMULASI. |
| `developer/akun-simulasi-index` | Nama, Email, Run, Status | New columns/filters likely. |
| Checkout / order detail / struk | no address shown | Possibly shipping address display — NOT decided; do not add. |
| Analytics FR-05/06 (M5–M7) | not built | May segment by region/gender/age — not decided. |

---

## 5. Findings (code issues noticed; NOT fixed — report only)

- F1 No Blade components / Lucide / design system yet; all views ad hoc. Expected (polish phase).
- F2 `app.css` `@theme --font-sans: 'Instrument Sans'` contradicts UI.md (Manrope/Inter); fonts not loaded.
- F3 Developer pages lack permanent SimBanner + "Simulator — " title prefix (UI.md §6.7). Layout shares Admin/buyer header.
- F4 Checkout preselects first method (`@checked($loop->first)`) vs required "belum memilih metode" state.
- F5 VA "Salin" in instruction-show uses inline `navigator.clipboard` (fails on http LAN); the `[data-copy-target]` helper w/ fallback exists in app.js.
- F6 Buyer-account status change uses native `confirm()` instead of project Dialog pattern (none exists yet).
- F7 `.badge-simulasi` orange `#F5A443` text on `#FFF0DA` ≈ 1.8:1 contrast (rough calc) — fails AA; needs darker label color (UI.md §13.2 requires check).
- F8 `.input` uses 1px border `#DCE0F0` + surface-light bg; UI.md says surface + inset, no contrast border. Minor.
- F9 tokens.css missing: primary-dark, surface-hover, border/divider tokens, light accent bgs, ease smooth/enter/exit. `.card` uses shadow-sm (UI.md: md).
- F10 Layout has no skip link / no landmarks beyond header/main/footer; error list is global, not field-associated in all forms (check per view).
- F11 Docs drift: UI.md/Workflow still describe visible HTTP panel (A3).

---

## 6. Pre-change checklist for future me
1. `git status` first; Ren's uncommitted work is untouchable.
2. Confirm milestone (M4 migration exists? analytics routes exist?) before touching affected areas (§4, A5).
3. Port Figma visually, never structurally against Ren decisions (unified login, hidden panel).
4. Keep all SIMULASI text that tests assert (PAY-54); run `vendor/bin/pest` + `npm run build`; never edit tests.
5. Verify 390/768/1280, keyboard, focus, reduced motion, contrast.
6. AGENTS.md + CLAUDE.md are mirrors; if rules change both change (not by me unless asked).

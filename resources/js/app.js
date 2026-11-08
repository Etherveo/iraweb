// QR pembayaran simulasi (M3b, script QR Ren — package npm `qrcode`).
// Halaman instruksi membawa URL sim di [data-qr-url]; script ini
// menggambar QR-nya (PNG data URL, scale 8 seperti script bot WA Ren)
// ke <img class="qr-image"> di dalam wadah yang sama. Bila JS mati atau
// gagal, fallback teks server (TextQrRenderer) tetap tampil.
import { toDataURL } from 'qrcode';

// Grafik analitik (M5 FR-01/FR-03, Chart.js dibundel lewat Vite — tanpa
// CDN, sesuai AGENTS.md). `chart.js/auto` mendaftarkan semua komponen
// yang dipakai halaman analitik (line + bar).
import Chart from 'chart.js/auto';

// Salin dari panel jejak HTTP (M3c PAY-40/41). Tombol membawa
// [data-copy-target="<id elemen <pre>"] dan [data-copy-label="<label
// awal>"]; teks yang disalin adalah isi persis elemen target
// (textContent), sama dengan yang tampil di layar. navigator.clipboard
// butuh konteks aman (https/localhost), jadi di host LAN (http)
// dipakai fallback textarea + execCommand agar salin tetap jalan
// dari HP. Tanpa bundle baru tombol tidak melakukan apa-apa:
// jalankan `npm run build` setelah pull.
//
// Perbaikan fallback HP (M3c, 2026-11-05): versi lama hanya memanggil
// ta.select() — di browser HP (terutama iOS Safari) itu saja tidak
// cukup: textarea harus difokuskan DAN seleksinya dibuat eksplisit
// dengan setSelectionRange, kalau tidak execCommand('copy') gagal
// diam-diam atau menyalin kosong. readOnly mencegah keyboard muncul
// saat focus di iOS. Inilah kenapa "Salin cURL" gagal dari HP
// sementara "Salin JSON" (yang kebetulan lolos seleksi) jalan.
function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }
    return new Promise((resolve, reject) => {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.readOnly = true;
        ta.style.position = 'fixed';
        ta.style.top = '0';
        ta.style.left = '0';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        ta.setSelectionRange(0, ta.value.length);
        try {
            const ok = document.execCommand('copy');
            document.body.removeChild(ta);
            if (ok) resolve();
            else reject(new Error('copy failed'));
        } catch (e) {
            document.body.removeChild(ta);
            reject(e);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-copy-target]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.getAttribute('data-copy-target'));
            if (!target) return;
            const label = btn.getAttribute('data-copy-label') || btn.textContent;
            // textContent, bukan innerText: untuk isi <pre> keduanya
            // sama, tapi textContent tidak tergantung layout/visibilitas
            // (innerText mengembalikan "" bila elemennya tersembunyi —
            // relevan saat panel nanti masuk ke popup tersembunyi).
            copyText(target.textContent).then(() => {
                btn.textContent = 'Tersalin';
                setTimeout(() => { btn.textContent = label; }, 2000);
            }).catch(() => {
                btn.textContent = 'Gagal menyalin';
                setTimeout(() => { btn.textContent = label; }, 2000);
            });
        });
    });

    document.querySelectorAll('[data-qr-url]').forEach((slot) => {
        const url = slot.getAttribute('data-qr-url');
        const img = slot.querySelector('img.qr-image');
        if (!url || !img) return;

        toDataURL(url.slice(0, 2048), { scale: 8 })
            .then((dataUrl) => {
                img.src = dataUrl;
                img.hidden = false;
                const fallback = slot.querySelector('.qr-fallback');
                if (fallback) fallback.hidden = true;
            })
            .catch(() => {
                // Biarkan fallback teks tampil; halaman tetap berfungsi
                // lewat tautan "Buka Halaman Simulasi Pembayaran".
            });
    });
});

// Eye toggle kata sandi (UI polish Tahap 1, halaman /masuk). Tombol membawa
// [data-password-toggle="<id input>"] dan tersembunyi lewat atribut hidden
// sampai script ini berjalan, jadi tanpa JS formulir tetap normal. Tidak
// menyentuh nilai atau pengiriman field: hanya mengganti type password/text.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
        const input = document.getElementById(btn.getAttribute('data-password-toggle'));
        if (!input) return;
        const iconShow = btn.querySelector('[data-icon-show]');
        const iconHide = btn.querySelector('[data-icon-hide]');
        btn.hidden = false;
        btn.addEventListener('click', () => {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            btn.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            btn.setAttribute('aria-label', reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            if (iconShow) iconShow.hidden = reveal;
            if (iconHide) iconHide.hidden = !reveal;
        });
    });
});

// Popup tamu di detail produk (UI polish Tahap 2). Tombol dengan
// [data-guest-popup-open="<id popup>"] membuka popup; [data-guest-popup-close]
// (tombol ×, backdrop) dan Esc menutupnya. Fokus pindah ke panel saat dibuka,
// kembali ke tombol pemicu saat ditutup, dan Tab dijebak di dalam panel.
// Tanpa JS, popup tetap tersembunyi (atribut hidden) dan tombol tidak
// melakukan apa-apa; POST /keranjang/tambah tetap dikunci auth di server.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-guest-popup-open]').forEach((opener) => {
        const popup = document.getElementById(opener.getAttribute('data-guest-popup-open'));
        if (!popup) return;
        const panel = popup.querySelector('[role="dialog"]');
        const focusables = () => panel.querySelectorAll('a[href], button:not([disabled])');

        const close = () => {
            popup.hidden = true;
            document.removeEventListener('keydown', onKey);
            opener.focus();
        };
        const onKey = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
                return;
            }
            if (e.key === 'Tab') {
                const items = focusables();
                if (items.length === 0) return;
                const first = items[0];
                const last = items[items.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        };

        opener.addEventListener('click', () => {
            popup.hidden = false;
            document.addEventListener('keydown', onKey);
            panel.focus();
        });
        popup.querySelectorAll('[data-guest-popup-close]').forEach((el) => el.addEventListener('click', close));
    });

    // Stepper jumlah: tombol ± hanya muncul bila JS aktif; input angka tetap
    // sumber nilai yang dikirim, dibatasi min/max milik input itu sendiri.
    document.querySelectorAll('[data-stepper]').forEach((box) => {
        const input = box.querySelector('input[type="number"]');
        const minus = box.querySelector('[data-stepper-minus]');
        const plus = box.querySelector('[data-stepper-plus]');
        if (!input || !minus || !plus) return;
        const step = (delta) => {
            const min = Number(input.min) || 1;
            const max = Number(input.max) || 99;
            const next = Math.min(max, Math.max(min, (Number(input.value) || min) + delta));
            input.value = String(next);
        };
        minus.hidden = false;
        plus.hidden = false;
        minus.addEventListener('click', () => step(-1));
        plus.addEventListener('click', () => step(1));
    });
});

// Panel teknis pembayaran tersembunyi (detail pembayaran admin di Tahap 4b/5
// + detail pembayaran pembeli & halaman simulator di Tahap 7):
// ketuk/klik 5 kali dalam 3 detik pada elemen [data-trace-trigger="<id popup>"]
// membuka popup kecil berisi tombol "Lihat detail teknis" + tombol tutup (×).
// Menekan "Lihat detail teknis" mengganti isi popup menjadi panel teknis lengkap.
// Tanpa tombol permanen dan tanpa sessionStorage: setiap kali ingin membuka
// kembali setelah ditutup, ketuk 5× lagi.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-trace-trigger]').forEach((trigger) => {
        const popupId = trigger.getAttribute('data-trace-trigger');
        const popup = document.getElementById(popupId);
        if (!popup) return;

        const dialog = popup.querySelector('[role="dialog"]');
        const gateStage = popup.querySelector('[data-trace-stage="gate"]');
        const panelStage = popup.querySelector('[data-trace-stage="panel"]');
        const expandBtn = popup.querySelector('[data-trace-expand]');
        if (!dialog || !gateStage || !panelStage || !expandBtn) return;

        let taps = [];

        const visibleFocusables = () => Array.from(
            dialog.querySelectorAll('button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])')
        ).filter((el) => !el.hidden && el.offsetParent !== null);

        const setStage = (mode) => {
            const showPanel = mode === 'panel';
            gateStage.hidden = showPanel;
            panelStage.hidden = !showPanel;
            dialog.classList.toggle('is-expanded', showPanel);
        };

        const closePopup = () => {
            popup.hidden = true;
            document.removeEventListener('keydown', onKey);
        };

        const onKey = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                closePopup();
                return;
            }
            if (e.key === 'Tab') {
                const items = visibleFocusables();
                if (items.length === 0) return;
                const first = items[0];
                const last = items[items.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        };

        const openPopup = () => {
            setStage('gate');
            popup.hidden = false;
            document.addEventListener('keydown', onKey);
            expandBtn.focus();
        };

        trigger.addEventListener('click', () => {
            const now = Date.now();
            taps = taps.filter((t) => now - t <= 3000);
            taps.push(now);
            if (taps.length >= 5) {
                taps = [];
                openPopup();
            }
        });

        expandBtn.addEventListener('click', () => {
            setStage('panel');
            dialog.focus();
        });

        popup.querySelectorAll('[data-trace-close]').forEach((el) => {
            el.addEventListener('click', closePopup);
        });
    });

    // Drawer sidebar admin untuk tampilan mobile (<1024px, Tahap 5).
    const sidebar = document.getElementById('admin-sidebar');
    const openBtn = document.querySelector('[data-admin-drawer-open]');
    const backdrop = document.querySelector('.admin-sidebar-backdrop');
    if (sidebar && openBtn) {
        const closeDrawer = () => {
            sidebar.classList.remove('is-open');
            openBtn.setAttribute('aria-expanded', 'false');
            if (backdrop) backdrop.hidden = true;
            document.removeEventListener('keydown', onDrawerKey);
            openBtn.focus();
        };

        const onDrawerKey = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeDrawer();
            }
        };

        openBtn.addEventListener('click', () => {
            const isOpen = sidebar.classList.toggle('is-open');
            openBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (backdrop) backdrop.hidden = !isOpen;
            if (isOpen) {
                document.addEventListener('keydown', onDrawerKey);
                const closeBtn = sidebar.querySelector('.admin-drawer-close');
                if (closeBtn) closeBtn.focus();
            } else {
                document.removeEventListener('keydown', onDrawerKey);
            }
        });

        document.querySelectorAll('[data-admin-drawer-close]').forEach((el) => {
            el.addEventListener('click', closeDrawer);
        });
    }

    // Menu hamburger top bar untuk tampilan mobile (<1024px). Pola yang sama
    // dengan drawer admin: tombol membuka/menutup, backdrop dan Esc menutup.
    const topbarNav = document.getElementById('topbar-nav');
    const topbarOpenBtn = document.querySelector('[data-topbar-menu-open]');
    const topbarBackdrop = document.querySelector('.topbar-backdrop');
    if (topbarNav && topbarOpenBtn) {
        const closeTopbarMenu = () => {
            topbarNav.classList.remove('is-open');
            topbarOpenBtn.setAttribute('aria-expanded', 'false');
            if (topbarBackdrop) topbarBackdrop.hidden = true;
            document.removeEventListener('keydown', onTopbarKey);
            topbarOpenBtn.focus();
        };

        const onTopbarKey = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeTopbarMenu();
            }
        };

        topbarOpenBtn.addEventListener('click', () => {
            const isOpen = topbarNav.classList.toggle('is-open');
            topbarOpenBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (topbarBackdrop) topbarBackdrop.hidden = !isOpen;
            if (isOpen) {
                document.addEventListener('keydown', onTopbarKey);
            } else {
                document.removeEventListener('keydown', onTopbarKey);
            }
        });

        document.querySelectorAll('[data-topbar-menu-close]').forEach((el) => {
            el.addEventListener('click', closeTopbarMenu);
        });
    }

    // Drawer sidebar developer untuk tampilan mobile (<1024px, M4).
    // Pola yang sama dengan drawer admin.
    const devSidebar = document.getElementById('developer-sidebar');
    const devOpenBtn = document.querySelector('[data-developer-drawer-open]');
    const devBackdrop = document.querySelector('.developer-sidebar-backdrop');
    if (devSidebar && devOpenBtn) {
        const closeDevDrawer = () => {
            devSidebar.classList.remove('is-open');
            devOpenBtn.setAttribute('aria-expanded', 'false');
            if (devBackdrop) devBackdrop.hidden = true;
            document.removeEventListener('keydown', onDevDrawerKey);
            devOpenBtn.focus();
        };

        const onDevDrawerKey = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeDevDrawer();
            }
        };

        devOpenBtn.addEventListener('click', () => {
            const isOpen = devSidebar.classList.toggle('is-open');
            devOpenBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (devBackdrop) devBackdrop.hidden = !isOpen;
            if (isOpen) {
                document.addEventListener('keydown', onDevDrawerKey);
                const closeBtn = devSidebar.querySelector('.developer-drawer-close');
                if (closeBtn) closeBtn.focus();
            } else {
                document.removeEventListener('keydown', onDevDrawerKey);
            }
        });

        document.querySelectorAll('[data-developer-drawer-close]').forEach((el) => {
            el.addEventListener('click', closeDevDrawer);
        });
    }

    // Pemilih akun simulasi (M4, D8): pencarian menyaring daftar di
    // sisi klien, supaya centang tidak hilang saat berpindah halaman.
    const accountFilter = document.querySelector('[data-sim-account-filter]');
    if (accountFilter) {
        const picker = accountFilter.closest('[data-sim-account-picker]');
        const rows = picker ? Array.from(picker.querySelectorAll('[data-sim-account-row]')) : [];
        const emptyNote = picker ? picker.querySelector('[data-sim-account-empty]') : null;
        const countEl = picker ? picker.querySelector('[data-sim-account-count]') : null;
        const total = rows.length;

        accountFilter.addEventListener('input', () => {
            const query = accountFilter.value.trim().toLowerCase();
            let visible = 0;

            rows.forEach((row) => {
                const match = query === '' || (row.dataset.search || '').includes(query);
                row.hidden = !match;
                if (match) visible += 1;
            });

            if (emptyNote) emptyNote.hidden = visible !== 0;
            if (countEl) countEl.textContent = query === '' ? String(total) : String(visible);
        });
    }

    // Pilihan cepat periode (M4 SIM-16): tanggal dihitung server (WITA)
    // dan disalin ke dua input tanggal dalam form yang sama.
    document.querySelectorAll('[data-period-quick]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const scope = btn.closest('[data-period-scope]');
            if (!scope) return;
            const start = scope.querySelector('input[name="period_start"]');
            const end = scope.querySelector('input[name="period_end"]');
            if (start && btn.dataset.start) start.value = btn.dataset.start;
            if (end && btn.dataset.end) end.value = btn.dataset.end;
        });
    });

    // Distribusi 'satu titik': tampilkan input tanggal+jam hanya bila
    // distribusi waktu = single.
    document.querySelectorAll('[data-time-distribution]').forEach((radio) => {
        radio.addEventListener('change', () => {
            const field = document.querySelector('[data-single-point-field]');
            if (!field) return;
            const selected = document.querySelector('[data-time-distribution]:checked');
            field.hidden = !selected || selected.value !== 'single';
        });
    });

    // Preset jumlah funnel (M7 SIM-32/33): tombol hanya mengisi empat
    // angka formulir Aksi 4 — tanpa perubahan planner/validator
    // (blueprint §9.8). Tombol bertipe button, bukan submit.
    document.querySelectorAll('[data-funnel-preset]').forEach((button) => {
        button.addEventListener('click', () => {
            const set = (id, value) => {
                const input = document.getElementById(id);
                if (input) input.value = value;
            };
            set('view_count', button.dataset.view);
            set('add_to_cart_count', button.dataset.cart);
            set('booking_count', button.dataset.booking);
            set('purchase_count', button.dataset.purchase);
        });
    });

    // Cakupan user period-first (SIM-18): pemilih akun hanya untuk
    // 'one'/'several', input k hanya untuk 'random'.
    const scopeGroup = document.querySelector('[data-user-scope]');
    if (scopeGroup) {
        const syncScope = () => {
            const selected = scopeGroup.querySelector('[data-user-scope-option]:checked');
            const value = selected ? selected.value : 'all';
            const picker = scopeGroup.querySelector('[data-user-scope-picker]');
            const random = scopeGroup.querySelector('[data-user-scope-random]');
            if (picker) picker.hidden = value !== 'one' && value !== 'several';
            if (random) random.hidden = value !== 'random';
        };
        scopeGroup.querySelectorAll('[data-user-scope-option]').forEach((radio) => {
            radio.addEventListener('change', syncScope);
        });
        syncScope();
    }

    // Reset (SIM-38/39): tombol aktif hanya bila frasa diketik persis.
    // Validasi server tetap yang menentukan; ini hanya kenyamanan.
    document.querySelectorAll('[data-reset-form]').forEach((form) => {
        const input = form.querySelector('[data-reset-phrase]');
        const submit = form.querySelector('[data-reset-submit]');
        if (!input || !submit) return;

        const syncPhrase = () => {
            submit.disabled = input.value.trim() !== input.dataset.resetPhrase;
        };
        input.addEventListener('input', syncPhrase);
        syncPhrase();
    });

    // Konfirmasi eksekusi (SIM-26): cegah klik ganda — kunci
    // idempotensi di server tetap menjadi pengaman terakhir.
    document.querySelectorAll('[data-confirm-once]').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('button[type="submit"]').forEach((btn) => {
                btn.disabled = true;
            });
        });
    });

    // Grafik analitik (M5 FR-01/FR-03): canvas membawa [data-chart] dan
    // konfigurasi JSON di [data-chart-config] (dibangun server-side,
    // hanya data + opsi aman-JSON). Sumbu/tooltip Rp diaktifkan lewat
    // [data-chart-rupiah] — callback tidak bisa lewat JSON, jadi
    // ditambahkan di sini.
    const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('[data-chart]').forEach((canvas) => {
        let config;
        try {
            config = JSON.parse(canvas.dataset.chartConfig || '{}');
        } catch {
            return;
        }
        if (!config.type || !config.data) return;

        const isRupiah = canvas.hasAttribute('data-chart-rupiah');
        const formatNum = (val) => Number(val ?? 0).toLocaleString('id-ID');
        const rupiah = (value) => 'Rp ' + formatNum(value);

        config.options = config.options || {};
        if (prefersReducedMotion) {
            config.options.animation = false;
        }

        // Interaksi hover lebih mudah dibaca pada grafik garis harian
        if (config.type === 'line') {
            config.options.interaction = {
                mode: 'index',
                intersect: false,
                ...(config.options.interaction || {}),
            };
            const ctx2d = typeof canvas.getContext === 'function' ? canvas.getContext('2d') : null;
            if (Array.isArray(config.data.datasets)) {
                const hasAnyFilled = config.data.datasets.some((d) => d.fill !== false);
                config.data.datasets = config.data.datasets.map((ds, idx) => {
                    const isFilled = ds.fill !== false;
                    let bg = ds.backgroundColor || (isFilled ? 'rgba(102, 85, 245, 0.16)' : 'transparent');
                    if (ctx2d && isFilled) {
                        const grad = ctx2d.createLinearGradient(0, 0, 0, 280);
                        grad.addColorStop(0, 'rgba(102, 85, 245, 0.28)');
                        grad.addColorStop(1, 'rgba(102, 85, 245, 0.01)');
                        bg = grad;
                    }
                    return {
                        borderWidth: idx === 0 ? 2.75 : 2.35,
                        borderDash: hasAnyFilled && !isFilled && idx > 0 ? [6, 4] : undefined,
                        pointRadius: 3.5,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#FFFFFF',
                        pointBorderColor: ds.borderColor || '#6655F5',
                        pointBorderWidth: 2,
                        ...ds,
                        backgroundColor: bg,
                    };
                });
            }
        } else if (config.type === 'bar' && Array.isArray(config.data.datasets)) {
            config.data.datasets = config.data.datasets.map((ds) => ({
                maxBarThickness: 36,
                borderSkipped: false,
                ...ds,
            }));
        }

        config.options.plugins = config.options.plugins || {};
        if (config.options.plugins.legend && config.options.plugins.legend.display) {
            config.options.plugins.legend = {
                ...config.options.plugins.legend,
                labels: {
                    usePointStyle: true,
                    boxWidth: 8,
                    padding: 16,
                    color: '#171A36',
                    font: { family: "'Manrope', sans-serif", size: 12, weight: '700' },
                    ...(config.options.plugins.legend.labels || {}),
                },
            };
        }
        config.options.plugins.tooltip = {
            backgroundColor: '#171A36',
            titleColor: '#FFFFFF',
            bodyColor: '#EAECF8',
            titleFont: { family: "'Manrope', sans-serif", size: 13, weight: '700' },
            bodyFont: { family: "'Inter', sans-serif", size: 13 },
            padding: 12,
            cornerRadius: 12,
            displayColors: true,
            boxPadding: 5,
            ...(config.options.plugins.tooltip || {}),
            callbacks: {
                ...((config.options.plugins.tooltip && config.options.plugins.tooltip.callbacks) || {}),
                label: (ctx) => {
                    const rawVal = config.options.indexAxis === 'y'
                        ? (ctx.parsed.x ?? ctx.parsed.y ?? 0)
                        : (ctx.parsed.y ?? ctx.parsed.x ?? 0);
                    const formatted = isRupiah ? rupiah(rawVal) : formatNum(rawVal);
                    const prefix = ctx.dataset && ctx.dataset.label ? ctx.dataset.label + ': ' : '';
                    return ' ' + prefix + formatted;
                },
            },
        };

        const axisTickStyle = {
            color: '#515A8C',
            font: { family: "'Inter', sans-serif", size: 12 },
        };
        const axisGridStyle = {
            color: 'rgba(209, 213, 231, 0.55)',
            drawTicks: false,
        };

        config.options.scales = config.options.scales || {};
        config.options.scales.x = {
            grid: config.options.indexAxis === 'y' ? axisGridStyle : { display: false },
            border: { display: false },
            ...(config.options.scales.x || {}),
            ticks: {
                ...axisTickStyle,
                ...((config.options.scales.x && config.options.scales.x.ticks) || {}),
            },
        };
        config.options.scales.y = {
            grid: config.options.indexAxis === 'y' ? { display: false } : axisGridStyle,
            border: { display: false },
            ...(config.options.scales.y || {}),
            ticks: {
                ...axisTickStyle,
                ...((config.options.scales.y && config.options.scales.y.ticks) || {}),
                ...(isRupiah ? { callback: (value) => rupiah(value) } : {}),
            },
        };

        new Chart(canvas, config);
    });
});

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1"
        />
        <title>Content Sections selector fixture</title>
        <style>
            :root {
                color-scheme: light;
                font-family:
                    Inter,
                    ui-sans-serif,
                    system-ui,
                    -apple-system,
                    BlinkMacSystemFont,
                    'Segoe UI',
                    sans-serif;
                background: #f8fafc;
                color: #0f172a;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            }

            .fixture-shell {
                width: min(1120px, calc(100vw - 48px));
                border: 1px solid #d7dde8;
                background: #ffffff;
                box-shadow: 0 24px 70px rgba(15, 23, 42, 0.16);
                border-radius: 14px;
                overflow: hidden;
            }

            .fixture-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px 24px;
                border-bottom: 1px solid #e5e7eb;
            }

            .fixture-header h1 {
                margin: 0;
                font-size: 18px;
                font-weight: 650;
            }

            .fixture-header span {
                color: #64748b;
                font-size: 13px;
            }

            .section-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px;
                padding: 24px;
            }

            .section-card {
                border: 1px solid #dde3ee;
                border-radius: 8px;
                padding: 16px;
                background: #ffffff;
            }

            .section-card strong {
                display: block;
                margin-bottom: 8px;
                font-size: 14px;
            }

            .section-card p {
                margin: 0;
                min-height: 42px;
                color: #475569;
                font-size: 13px;
                line-height: 1.45;
            }

            .section-card button {
                margin-top: 14px;
                width: 100%;
                border: 0;
                border-radius: 6px;
                padding: 9px 12px;
                background: #1f2937;
                color: #ffffff;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <main
            class="fixture-shell"
            aria-label="Section selector modal fixture"
        >
            <header class="fixture-header">
                <h1>Choose a reusable section</h1>
                <span>17 registered section families</span>
            </header>
            <section class="section-grid">
                @foreach (['Hero', 'Content', 'Features', 'FAQ', 'Pricing', 'Testimonials', 'Team', 'Timeline'] as $sectionName)
                    <article class="section-card">
                        <strong>{{ $sectionName }}</strong>
                        <p>
                            Reusable, themeable content block ready for Layout
                            Builder and Block Library composition.
                        </p>
                        <button type="button">
                            Insert {{ $sectionName }}
                        </button>
                    </article>
                @endforeach
            </section>
        </main>
    </body>
</html>

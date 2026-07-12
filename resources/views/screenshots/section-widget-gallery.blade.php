<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    />
    <title>Content Sections gallery fixture</title>
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
            background: #f9fafb;
            color: #111827;
        }

        body {
            margin: 0;
            background: #f9fafb;
        }

        .gallery-shell {
            width: min(1180px, calc(100vw - 40px));
            margin: 32px auto;
        }

        .gallery-header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: end;
            margin-bottom: 24px;
        }

        h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: 0;
        }

        .gallery-header p {
            margin: 8px 0 0;
            max-width: 620px;
            color: #4b5563;
            line-height: 1.55;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .gallery-item {
            min-height: 168px;
            border: 1px solid #dce3ef;
            border-radius: 8px;
            background: #ffffff;
            padding: 18px;
            display: grid;
            align-content: space-between;
        }

        .gallery-item span {
            display: inline-flex;
            width: fit-content;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            padding: 4px 9px;
            font-size: 12px;
            font-weight: 650;
        }

        .gallery-item strong {
            display: block;
            margin-top: 16px;
            font-size: 17px;
        }

        .gallery-item p {
            margin: 8px 0 0;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <main class="gallery-shell">
        <header class="gallery-header">
            <div>
                <h1>Content Sections widget gallery</h1>
                <p>Anonymous-safe fixture showing the breadth of package-owned reusable sections without admin chrome or editor-link metadata.</p>
            </div>
        </header>
        <section
            class="gallery-grid"
            aria-label="Reusable section widget gallery"
        >
            @foreach ([
                              ['Hero', 'Lead with a strong page intro and action set.'],
                              ['Features', 'Compare product capabilities in a scannable grid.'],
                              ['FAQ', 'Publish accessible disclosure content for support questions.'],
                              ['Pricing', 'Show plans, benefits, and calls to action.'],
                              ['Team', 'Introduce people with roles and short biographies.'],
                              ['Timeline', 'Sequence milestones, launches, or process steps.'],
                          ] as [$sectionName, $summary])
                <article class="gallery-item">
                    <div>
                        <span>Section</span>
                        <strong>{{ $sectionName }}</strong>
                        <p>{{ $summary }}</p>
                    </div>
                </article>
            @endforeach
        </section>
    </main>
</body>
</html>

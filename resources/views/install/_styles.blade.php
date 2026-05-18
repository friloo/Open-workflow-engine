<style>
    :root { color-scheme: light; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
           background: #f8fafc; color: #0f172a; margin: 0; min-height: 100vh; }
    .wrap { max-width: 720px; margin: 40px auto; padding: 0 16px; }
    .card { background: white; border: 1px solid #e2e8f0; border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04); padding: 24px; margin-bottom: 16px; }
    h1 { font-size: 22px; margin: 0 0 6px; }
    h2 { font-size: 16px; margin: 16px 0 8px; }
    p.sub { color: #64748b; margin: 0 0 16px; font-size: 14px; }
    .steps { display: flex; gap: 6px; margin-bottom: 20px; font-size: 12px; }
    .step { padding: 6px 10px; border-radius: 999px; background: #e2e8f0; color: #475569; }
    .step.active { background: #6366f1; color: white; }
    .step.done { background: #10b981; color: white; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #f1f5f9; }
    .badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 999px;
             font-size: 11px; font-weight: 600; }
    .ok { background: #d1fae5; color: #065f46; }
    .warn { background: #fef3c7; color: #92400e; }
    .fail { background: #fee2e2; color: #991b1b; }
    label { display: block; font-size: 13px; font-weight: 500; color: #334155; margin: 12px 0 4px; }
    input, select { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px;
                    font-size: 14px; font-family: inherit; box-sizing: border-box; }
    input:focus, select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    button.primary { background: #6366f1; color: white; border: 0; padding: 10px 18px;
                     border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; }
    button.primary:hover { background: #4f46e5; }
    button.primary:disabled { opacity: 0.5; cursor: not-allowed; }
    a.link { color: #6366f1; text-decoration: none; }
    a.link:hover { text-decoration: underline; }
    .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
                   padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 14px; }
    .alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af;
                  padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 14px; }
    .alert-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46;
                padding: 14px; border-radius: 8px; font-size: 14px; margin-bottom: 14px; }
    code { background: #f1f5f9; padding: 1px 6px; border-radius: 4px; font-size: 12px; }
</style>
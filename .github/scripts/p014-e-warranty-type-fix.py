from pathlib import Path

path = Path('apps/platform/resources/js/Components/ProfessionalFundOrdersPanel.vue')
text = path.read_text()
old = '    externally_settled_minor: number;\n'
new = '    externally_settled_minor: number;\n    delivered_at: string | null;\n'
if new not in text:
    if old not in text:
        raise SystemExit('Professional order delivered_at anchor not found')
    path.write_text(text.replace(old, new, 1))

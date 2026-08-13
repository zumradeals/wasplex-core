from pathlib import Path

path = Path('apps/platform/tests/Feature/Funds/FundsRealizationTest.php')
text = path.read_text()
old = "    expect($warranty->refresh()->status)->toBe('expired');\n"
new = "    expect(DB::table('fund_warranty_claims')->count())->toBe(0);\n"
if new not in text:
    if old not in text:
        raise SystemExit('Expired warranty assertion not found')
    text = text.replace(old, new, 1)

if 'use Illuminate\\Support\\Facades\\DB;' not in text:
    anchor = 'use Illuminate\\Support\\Facades\\'
    # Add DB beside the first Illuminate facade import when present, otherwise before RuntimeException.
    if 'use RuntimeException;' in text:
        text = text.replace('use RuntimeException;\n', 'use Illuminate\\Support\\Facades\\DB;\nuse RuntimeException;\n', 1)
    else:
        raise SystemExit('Import anchor not found')

path.write_text(text)

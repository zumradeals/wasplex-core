from pathlib import Path

path = Path('apps/platform/resources/js/Pages/Identity/UserShell.vue')
text = path.read_text()
text = text.replace(
    'class="flex h-12 w-12 items-center justify-center rounded-full text-2xl"\n                        class="bg-wpx-gold/10"',
    'class="flex h-12 w-12 items-center justify-center rounded-full bg-wpx-gold/10 text-2xl"',
)
path.write_text(text)

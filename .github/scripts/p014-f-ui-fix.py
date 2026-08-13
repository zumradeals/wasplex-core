from pathlib import Path

root = Path('apps/platform/resources/js/Components')

admin = root / 'AdminFundPilotage.vue'
text = admin.read_text()
text = text.replace(
    "{{ item.account?.display_name || item.account?.phone_e164 || 'Membre' }}",
    "{{ item.account_label }}",
    1,
)
text = text.replace(
    """{{ item.membership?.program?.name }} · {{ item.incident_count }} incident(s) ·
                                    {{ item.status }}""",
    "{{ item.program_name }} · {{ item.incident_count }} incident(s) · {{ item.status }}",
    1,
)
admin.write_text(text)

member = root / 'FundPilotageCard.vue'
text = member.read_text()
text = text.replace(
    '<span class="bg-wpx-blue/10 text-wpx-cyan rounded-xl px-3 py-2 text-sm font-extrabold" v-if="data">',
    '<span v-if="data" class="bg-wpx-blue/10 text-wpx-cyan rounded-xl px-3 py-2 text-sm font-extrabold">',
    1,
)
member.write_text(text)

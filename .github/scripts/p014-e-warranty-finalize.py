from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    if new in text:
        return
    if old not in text:
        raise SystemExit(f"Pattern not found in {path}: {old[:100]!r}")
    p.write_text(text.replace(old, new, 1))


routes = "apps/platform/app/Modules/Funds/Http/routes/api.php"
replace_once(routes,
    "use App\\Modules\\Funds\\Http\\Controllers\\Admin\\AdminFundRealizationsController;\n",
    "use App\\Modules\\Funds\\Http\\Controllers\\Admin\\AdminFundRealizationsController;\nuse App\\Modules\\Funds\\Http\\Controllers\\Admin\\AdminFundWarrantyController;\n")
replace_once(routes,
    "use App\\Modules\\Funds\\Http\\Controllers\\Professional\\ProfessionalFundOrdersController;\n",
    "use App\\Modules\\Funds\\Http\\Controllers\\Professional\\ProfessionalFundOrdersController;\nuse App\\Modules\\Funds\\Http\\Controllers\\Professional\\ProfessionalFundWarrantyController;\n")
replace_once(routes,
    "use App\\Modules\\Funds\\Http\\Controllers\\User\\FundRealizationStatusController;\n",
    "use App\\Modules\\Funds\\Http\\Controllers\\User\\FundRealizationStatusController;\nuse App\\Modules\\Funds\\Http\\Controllers\\User\\FundWarrantyController;\n")
replace_once(routes,
    "        Route::post('/orders/{order}/disputes', [FundRealizationStatusController::class, 'dispute']);\n",
    "        Route::post('/orders/{order}/disputes', [FundRealizationStatusController::class, 'dispute']);\n        Route::post('/orders/{order}/warranty-claims', [FundWarrantyController::class, 'claim']);\n")
replace_once(routes,
    "        Route::post('/orders/{order}/delivered', [ProfessionalFundOrdersController::class, 'markDelivered'])\n            ->middleware(EnsureCapability::class.':professional.funds.delivery.confirm,organization:professional_organization_id');\n",
    "        Route::post('/orders/{order}/delivered', [ProfessionalFundOrdersController::class, 'markDelivered'])\n            ->middleware(EnsureCapability::class.':professional.funds.delivery.confirm,organization:professional_organization_id');\n        Route::post('/orders/{order}/warranty', [ProfessionalFundWarrantyController::class, 'register'])\n            ->middleware(EnsureCapability::class.':professional.funds.warranty.manage,organization:professional_organization_id');\n        Route::post('/orders/{order}/warranty-claims/{claimId}/response', [ProfessionalFundWarrantyController::class, 'respond'])\n            ->middleware(EnsureCapability::class.':professional.funds.warranty.manage,organization:professional_organization_id');\n")
replace_once(routes,
    "        Route::post('/orders/{order}/disputes/{disputeId}/resolve', [AdminFundRealizationsController::class, 'resolveDispute'])->middleware(EnsureCapability::class.':admin.funds.review');\n",
    "        Route::post('/orders/{order}/disputes/{disputeId}/resolve', [AdminFundRealizationsController::class, 'resolveDispute'])->middleware(EnsureCapability::class.':admin.funds.review');\n        Route::post('/orders/{order}/warranty-claims/{claimId}/resolve', [AdminFundWarrantyController::class, 'resolve'])->middleware(EnsureCapability::class.':admin.funds.review');\n")

workspace = "apps/platform/app/Modules/Identity/Http/Controllers/Api/ProfessionalWorkspaceController.php"
replace_once(workspace,
    "                'professional.funds.delivery.confirm',\n",
    "                'professional.funds.delivery.confirm',\n                'professional.funds.warranty.manage',\n")

user_ctrl = "apps/platform/app/Modules/Funds/Http/Controllers/User/FundRealizationStatusController.php"
replace_once(user_ctrl,
    "                'open_dispute' => DB::table('fund_order_disputes')->where('fund_order_id', $order->id)->where('status', 'open')->exists(),\n",
    "                'open_dispute' => DB::table('fund_order_disputes')->where('fund_order_id', $order->id)->where('status', 'open')->exists(),\n                'warranty' => (function () use ($order): ?array {\n                    $warranty = DB::table('fund_order_warranties')->where('fund_order_id', $order->id)->first();\n                    if ($warranty === null) { return null; }\n                    return [\n                        'id' => $warranty->id, 'status' => $warranty->status, 'reference' => $warranty->reference,\n                        'terms' => $warranty->terms, 'starts_at' => $warranty->starts_at, 'ends_at' => $warranty->ends_at,\n                        'claims' => DB::table('fund_warranty_claims')->where('fund_order_warranty_id', $warranty->id)->orderByDesc('opened_at')->get(),\n                    ];\n                })(),\n")

pro_ctrl = "apps/platform/app/Modules/Funds/Http/Controllers/Professional/ProfessionalFundOrdersController.php"
replace_once(pro_ctrl,
    "                'proofs' => DB::table('fund_order_proofs')->where('fund_order_id', $order->id)->orderByDesc('submitted_at')->get(),\n",
    "                'proofs' => DB::table('fund_order_proofs')->where('fund_order_id', $order->id)->orderByDesc('submitted_at')->get(),\n                'warranty' => (function () use ($order): ?array {\n                    $warranty = DB::table('fund_order_warranties')->where('fund_order_id', $order->id)->first();\n                    if ($warranty === null) { return null; }\n                    return [\n                        'id' => $warranty->id, 'status' => $warranty->status, 'reference' => $warranty->reference,\n                        'terms' => $warranty->terms, 'starts_at' => $warranty->starts_at, 'ends_at' => $warranty->ends_at,\n                        'claims' => DB::table('fund_warranty_claims')->where('fund_order_warranty_id', $warranty->id)->orderByDesc('opened_at')->get(),\n                    ];\n                })(),\n")

admin_ctrl = "apps/platform/app/Modules/Funds/Http/Controllers/Admin/AdminFundRealizationsController.php"
replace_once(admin_ctrl,
    "            'disputes' => DB::table('fund_order_disputes')->where('fund_order_id', $order->id)->orderByDesc('opened_at')->get(),\n",
    "            'disputes' => DB::table('fund_order_disputes')->where('fund_order_id', $order->id)->orderByDesc('opened_at')->get(),\n            'warranty' => (function () use ($order): ?array {\n                $warranty = DB::table('fund_order_warranties')->where('fund_order_id', $order->id)->first();\n                if ($warranty === null) { return null; }\n                return [\n                    'id' => $warranty->id, 'status' => $warranty->status, 'reference' => $warranty->reference,\n                    'terms' => $warranty->terms, 'starts_at' => $warranty->starts_at, 'ends_at' => $warranty->ends_at,\n                    'claims' => DB::table('fund_warranty_claims')->where('fund_order_warranty_id', $warranty->id)->orderByDesc('opened_at')->get(),\n                ];\n            })(),\n")

user_ui = "apps/platform/resources/js/Components/FundRealizationStatusCard.vue"
replace_once(user_ui,
    "type Order = {\n",
    "type WarrantyClaim = { id: string; status: string; issue: string; provider_response: string | null; resolution_code: string | null; resolution_note: string | null };\ntype Warranty = { id: string; status: string; reference: string | null; terms: string | null; starts_at: string; ends_at: string; claims: WarrantyClaim[] };\ntype Order = {\n")
replace_once(user_ui,
    "    open_dispute: boolean;\n};",
    "    open_dispute: boolean;\n    warranty: Warranty | null;\n};")
replace_once(user_ui,
    "const disputeReason = ref('');\n",
    "const disputeReason = ref('');\nconst warrantyOrder = ref<Order | null>(null);\nconst warrantyIssue = ref('');\n")
replace_once(user_ui,
    "async function openDispute(): Promise<void> {",
    "async function openWarrantyClaim(): Promise<void> {\n    if (!warrantyOrder.value || warrantyIssue.value.trim().length < 10) return;\n    busy.value = true;\n    try {\n        await http.post(`/funds/orders/${warrantyOrder.value.id}/warranty-claims`, { issue: warrantyIssue.value });\n        warrantyOrder.value = null; warrantyIssue.value = ''; await load();\n    } catch { error.value = 'La réclamation de garantie n’a pas pu être ouverte.'; } finally { busy.value = false; }\n}\n\nasync function openDispute(): Promise<void> {")
replace_once(user_ui,
    "            <p v-if=\"order.status === 'completed'\" class=\"text-wpx-success-light mt-3 text-xs font-bold\">\n                ✓ Réalisation clôturée avec livraison et règlements confirmés.\n            </p>",
    "            <div v-if=\"order.warranty\" class=\"border-wpx-border-dark bg-wpx-navy-950/60 mt-3 rounded-2xl border p-3\">\n                <div class=\"flex items-start justify-between gap-3\"><div><p class=\"text-wpx-cyan text-[9px] font-black uppercase\">Garantie prestataire</p><p class=\"text-wpx-white-soft mt-1 text-xs font-bold\">{{ order.warranty.reference ?? 'Garantie enregistrée' }}</p><p class=\"text-wpx-muted-dark mt-1 text-[9px]\">Du {{ new Date(order.warranty.starts_at).toLocaleDateString('fr-FR') }} au {{ new Date(order.warranty.ends_at).toLocaleDateString('fr-FR') }}</p></div><span class=\"text-wpx-success-light text-[9px] font-black\">{{ order.warranty.status }}</span></div>\n                <p v-if=\"order.warranty.terms\" class=\"text-wpx-muted-dark mt-2 text-[10px] leading-relaxed\">{{ order.warranty.terms }}</p>\n                <p v-for=\"claim in order.warranty.claims\" :key=\"claim.id\" class=\"text-wpx-muted-dark mt-1 text-[9px]\">Réclamation : {{ claim.status }}<span v-if=\"claim.provider_response\"> · réponse prestataire reçue</span></p>\n                <button v-if=\"order.warranty.status === 'active' && !order.warranty.claims.some((claim) => ['open', 'provider_responded'].includes(claim.status))\" class=\"bg-wpx-cyan/10 text-wpx-cyan mt-3 rounded-xl px-3 py-2 text-[10px] font-black\" :disabled=\"busy\" @click=\"warrantyOrder = order\">Faire jouer la garantie</button>\n            </div>\n            <p v-if=\"order.status === 'completed'\" class=\"text-wpx-success-light mt-3 text-xs font-bold\">\n                ✓ Réalisation clôturée avec livraison et règlements confirmés.\n            </p>")
replace_once(user_ui,
    "        <div\n            v-if=\"disputeOrder\"",
    "        <div v-if=\"warrantyOrder\" class=\"fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center\" @click.self=\"warrantyOrder = null\">\n            <div class=\"bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl\"><h3 class=\"text-wpx-white-soft text-lg font-black\">Réclamation de garantie</h3><p class=\"text-wpx-muted-dark mt-1 text-xs\">Décris le défaut ou le problème constaté après livraison.</p><textarea v-model=\"warrantyIssue\" rows=\"5\" class=\"bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-4 w-full rounded-xl border p-3 text-sm\" /><button class=\"bg-wpx-cyan text-wpx-navy-950 mt-3 w-full rounded-xl px-4 py-3 text-sm font-black disabled:opacity-40\" :disabled=\"busy || warrantyIssue.trim().length < 10\" @click=\"openWarrantyClaim\">Envoyer la réclamation</button></div>\n        </div>\n\n        <div\n            v-if=\"disputeOrder\"")

pro_ui = "apps/platform/resources/js/Components/ProfessionalFundOrdersPanel.vue"
replace_once(pro_ui,
    "type Order = {\n",
    "type WarrantyClaim = { id: string; status: string; issue: string; provider_response: string | null };\ntype Warranty = { id: string; status: string; reference: string | null; terms: string | null; starts_at: string; ends_at: string; claims: WarrantyClaim[] };\ntype Order = {\n")
replace_once(pro_ui,
    "    milestones: Milestone[];\n};",
    "    milestones: Milestone[];\n    warranty: Warranty | null;\n};")
replace_once(pro_ui,
    "const deliveryDescription = ref('');\n",
    "const deliveryDescription = ref('');\nconst warrantyOrder = ref<Order | null>(null);\nconst warrantyReference = ref('');\nconst warrantyTerms = ref('');\nconst warrantyStartsAt = ref('');\nconst warrantyEndsAt = ref('');\nconst warrantyClaim = ref<{ order: Order; claim: WarrantyClaim } | null>(null);\nconst warrantyResponse = ref('');\n")
replace_once(pro_ui,
    "onMounted(load);",
    "async function registerWarranty(): Promise<void> {\n    if (!warrantyOrder.value || !warrantyStartsAt.value || !warrantyEndsAt.value) return;\n    busy.value = true;\n    try { await http.post(`/professional/funds/orders/${warrantyOrder.value.id}/warranty`, { reference: warrantyReference.value || null, terms: warrantyTerms.value || null, starts_at: warrantyStartsAt.value, ends_at: warrantyEndsAt.value }); warrantyOrder.value = null; warrantyReference.value = ''; warrantyTerms.value = ''; warrantyStartsAt.value = ''; warrantyEndsAt.value = ''; await load(); }\n    catch { error.value = 'La garantie n’a pas pu être enregistrée.'; } finally { busy.value = false; }\n}\n\nasync function respondWarranty(): Promise<void> {\n    if (!warrantyClaim.value || warrantyResponse.value.trim().length < 5) return;\n    busy.value = true;\n    try { await http.post(`/professional/funds/orders/${warrantyClaim.value.order.id}/warranty-claims/${warrantyClaim.value.claim.id}/response`, { response: warrantyResponse.value }); warrantyClaim.value = null; warrantyResponse.value = ''; await load(); }\n    catch { error.value = 'La réponse de garantie n’a pas pu être envoyée.'; } finally { busy.value = false; }\n}\n\nonMounted(load);")
replace_once(pro_ui,
    "            <button\n                v-if=\"props.canAct && !['completed', 'cancelled', 'delivered'].includes(order.status)\"",
    "            <div v-if=\"order.warranty\" class=\"border-wpx-border-dark bg-wpx-navy-950/60 mt-4 rounded-2xl border p-3\"><p class=\"text-wpx-cyan text-[9px] font-black uppercase\">Garantie</p><p class=\"text-wpx-white-soft mt-1 text-xs font-bold\">{{ order.warranty.reference ?? 'Garantie enregistrée' }} · {{ order.warranty.status }}</p><p class=\"text-wpx-muted-dark mt-1 text-[9px]\">{{ new Date(order.warranty.starts_at).toLocaleDateString('fr-FR') }} → {{ new Date(order.warranty.ends_at).toLocaleDateString('fr-FR') }}</p><button v-for=\"claim in order.warranty.claims.filter((claim) => claim.status === 'open')\" :key=\"claim.id\" class=\"bg-wpx-gold/10 text-wpx-gold mt-2 w-full rounded-xl px-3 py-2 text-[10px] font-black\" :disabled=\"busy\" @click=\"warrantyClaim = { order, claim }\">Répondre à la réclamation</button></div>\n            <button v-else-if=\"props.canAct && order.delivered_at\" type=\"button\" class=\"bg-wpx-cyan/10 text-wpx-cyan mt-4 w-full rounded-2xl px-4 py-3 text-[10px] font-black\" @click=\"warrantyOrder = order\">Enregistrer la garantie</button>\n            <button\n                v-if=\"props.canAct && !['completed', 'cancelled', 'delivered'].includes(order.status)\"")
replace_once(pro_ui,
    "    <Teleport to=\"body\">",
    "    <Teleport to=\"body\">\n        <div v-if=\"warrantyOrder\" class=\"fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center\" @click.self=\"warrantyOrder = null\"><div class=\"bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl\"><h3 class=\"text-wpx-white-soft text-lg font-black\">Enregistrer la garantie</h3><input v-model=\"warrantyReference\" placeholder=\"Référence garantie\" class=\"bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-4 w-full rounded-xl border px-3 py-3 text-sm\" /><textarea v-model=\"warrantyTerms\" rows=\"3\" placeholder=\"Conditions essentielles\" class=\"bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-2 w-full rounded-xl border p-3 text-sm\" /><div class=\"mt-2 grid grid-cols-2 gap-2\"><input v-model=\"warrantyStartsAt\" type=\"date\" class=\"bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3 text-sm\" /><input v-model=\"warrantyEndsAt\" type=\"date\" class=\"bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3 text-sm\" /></div><button class=\"bg-wpx-cyan text-wpx-navy-950 mt-3 w-full rounded-xl px-4 py-3 text-sm font-black disabled:opacity-40\" :disabled=\"busy || !warrantyStartsAt || !warrantyEndsAt\" @click=\"registerWarranty\">Enregistrer</button></div></div>\n        <div v-if=\"warrantyClaim\" class=\"fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center\" @click.self=\"warrantyClaim = null\"><div class=\"bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl\"><h3 class=\"text-wpx-white-soft text-lg font-black\">Répondre à la garantie</h3><p class=\"text-wpx-muted-dark mt-2 text-xs\">{{ warrantyClaim.claim.issue }}</p><textarea v-model=\"warrantyResponse\" rows=\"4\" class=\"bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-3 w-full rounded-xl border p-3 text-sm\" /><button class=\"bg-wpx-gold text-wpx-navy-950 mt-3 w-full rounded-xl px-4 py-3 text-sm font-black disabled:opacity-40\" :disabled=\"busy || warrantyResponse.trim().length < 5\" @click=\"respondWarranty\">Envoyer la réponse</button></div></div>")

admin_ui = "apps/platform/resources/js/Components/AdminFundRealizations.vue"
replace_once(admin_ui,
    "type Dispute = { id: string; status: string; reason: string; resolution_code: string | null };",
    "type Dispute = { id: string; status: string; reason: string; resolution_code: string | null };\ntype WarrantyClaim = { id: string; status: string; issue: string; provider_response: string | null; resolution_code: string | null };\ntype Warranty = { id: string; status: string; reference: string | null; terms: string | null; starts_at: string; ends_at: string; claims: WarrantyClaim[] };")
replace_once(admin_ui,
    "    disputes: Dispute[];\n};",
    "    disputes: Dispute[];\n    warranty: Warranty | null;\n};")
replace_once(admin_ui,
    "onMounted(load);",
    "async function resolveWarranty(order: Order, claim: WarrantyClaim, resolutionCode: 'provider_fix' | 'replacement' | 'accepted_as_is' | 'rejected' | 'closed'): Promise<void> {\n    const note = window.prompt('Note de résolution garantie obligatoire :');\n    if (!note?.trim()) return;\n    busy.value = true;\n    try { await http.post(`/admin/funds/orders/${order.id}/warranty-claims/${claim.id}/resolve`, { resolution_code: resolutionCode, resolution_note: note }); await load(); }\n    catch { error.value = 'La réclamation de garantie n’a pas pu être résolue.'; } finally { busy.value = false; }\n}\n\nonMounted(load);")
replace_once(admin_ui,
    "            <div class=\"mt-4 space-y-2\">",
    "            <div v-if=\"order.warranty\" class=\"border-wpx-border-dark bg-wpx-navy-950/60 mt-4 rounded-2xl border p-3\"><p class=\"text-wpx-cyan text-[9px] font-black uppercase\">Garantie · {{ order.warranty.status }}</p><p class=\"text-wpx-white-soft mt-1 text-xs font-bold\">{{ order.warranty.reference ?? 'Sans référence' }}</p><div v-for=\"claim in order.warranty.claims.filter((claim) => ['open', 'provider_responded'].includes(claim.status))\" :key=\"claim.id\" class=\"border-wpx-border-dark mt-2 rounded-xl border p-2\"><p class=\"text-wpx-white-soft text-[10px] font-bold\">{{ claim.issue }}</p><p v-if=\"claim.provider_response\" class=\"text-wpx-muted-dark mt-1 text-[9px]\">Réponse prestataire : {{ claim.provider_response }}</p><div class=\"mt-2 flex flex-wrap gap-1\"><button class=\"bg-wpx-success/15 text-wpx-success-light rounded-lg px-2 py-1 text-[9px] font-black\" @click=\"resolveWarranty(order, claim, 'provider_fix')\">Correction</button><button class=\"bg-wpx-cyan/10 text-wpx-cyan rounded-lg px-2 py-1 text-[9px] font-black\" @click=\"resolveWarranty(order, claim, 'replacement')\">Remplacement</button><button class=\"bg-wpx-danger/10 text-wpx-danger rounded-lg px-2 py-1 text-[9px] font-black\" @click=\"resolveWarranty(order, claim, 'rejected')\">Rejeter</button></div></div></div>\n\n            <div class=\"mt-4 space-y-2\">")

test_path = "apps/platform/tests/Feature/Funds/FundsRealizationTest.php"
replace_once(test_path,
    "use App\\Modules\\Funds\\Application\\Services\\FundRealizationService;\n",
    "use App\\Modules\\Funds\\Application\\Services\\FundRealizationService;\nuse App\\Modules\\Funds\\Application\\Services\\FundWarrantyService;\n")
p = Path(test_path)
text = p.read_text()
if "P014-E garantie prestataire fonctionne de bout en bout" not in text:
    text += """

test('P014-E garantie prestataire fonctionne de bout en bout', function (): void {
    $fixture = p014eFixture();
    $realization = app(FundRealizationService::class);
    $warranties = app(FundWarrantyService::class);
    $order = $realization->createOrder($fixture['wish'], $fixture['beneficiary']->id);
    $realization->markDelivered($order, $fixture['provider']->id, $fixture['beneficiary']->id, 'BL-GAR-001', 'Livraison garantie');

    $warranty = $warranties->register($order->refresh(), $fixture['provider']->id, $fixture['beneficiary']->id, 'GAR-24M-001', 'Pièces et main-d’œuvre couvertes selon les conditions du prestataire.', now()->subDay()->toDateString(), now()->addYear()->toDateString());
    expect($warranty->status)->toBe('active');

    $claim = $warranties->openClaim($order->refresh(), $fixture['beneficiary']->id, 'Le bien livré présente un défaut couvert par la garantie.');
    expect($claim->status)->toBe('open')->and($warranty->refresh()->status)->toBe('claim_open');

    $responded = $warranties->respond($order->refresh(), $fixture['provider']->id, $fixture['beneficiary']->id, $claim->id, 'Le prestataire confirme la prise en charge et propose une intervention.');
    expect($responded->status)->toBe('provider_responded');

    $resolved = $warranties->resolve($order->refresh(), $claim->id, $fixture['beneficiary']->id, 'provider_fix', 'Correction effectuée et dossier clôturé.');
    expect($resolved->status)->toBe('resolved')->and($resolved->resolution_code)->toBe('provider_fix')->and($warranty->refresh()->status)->toBe('active');
});

test('P014-E refuse une réclamation sur une garantie expirée', function (): void {
    $fixture = p014eFixture();
    $realization = app(FundRealizationService::class);
    $warranties = app(FundWarrantyService::class);
    $order = $realization->createOrder($fixture['wish'], $fixture['beneficiary']->id);
    $realization->markDelivered($order, $fixture['provider']->id, $fixture['beneficiary']->id, 'BL-GAR-EXP', null);
    $warranty = $warranties->register($order->refresh(), $fixture['provider']->id, $fixture['beneficiary']->id, 'GAR-EXP', null, now()->subDays(10)->toDateString(), now()->addDay()->toDateString());
    $warranty->update(['ends_at' => now()->subDay()]);

    expect(fn () => $warranties->openClaim($order->refresh(), $fixture['beneficiary']->id, 'Défaut déclaré après expiration de la garantie.'))->toThrow(RuntimeException::class, 'expirée');
    expect($warranty->refresh()->status)->toBe('expired');
});
"""
    p.write_text(text)

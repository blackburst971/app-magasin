<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  reservation:    Object,
  checkoutForms:  Array,
  selectedFormId: Number,
  schoolName:     String,
  schoolAddress:  String,
  printDate:      String,
  printSettings:  { type: Object, default: () => ({}) },
})

// ── Logos partagés globalement ─────────────────────────────────
const appLogos = computed(() => usePage().props.appLogos)

// ── Helpers paramètres ──────────────────────────────────────────
const ps = (key, fallback = 'true') => (props.printSettings?.[key] ?? fallback) === 'true'

const showThumbnails  = computed(() => ps('print_show_thumbnails', 'false'))
const showCategory    = computed(() => ps('print_show_category'))
const showAssetTag    = computed(() => ps('print_show_asset_tag'))
const showNotes       = computed(() => ps('print_show_notes'))
const showForm        = computed(() => ps('print_show_form'))
const proviseurLabel  = computed(() =>
  props.printSettings?.print_proviseur_label || 'Signature du proviseur'
)

// ── Formulaire sélectionné ─────────────────────────────────────
const chosenFormId = ref(props.selectedFormId ?? null)

// Formulaires qui ont une réponse pour cette réservation
const formsWithResponse = computed(() =>
  props.checkoutForms.filter(f => props.reservation.form_responses?.[f.id])
)

const chosenForm = computed(() =>
  props.checkoutForms.find(f => f.id === chosenFormId.value) ?? null
)

// Réponse du formulaire sélectionné
const selectedFormResponse = computed(() => {
  if (!chosenFormId.value) return null
  return props.reservation.form_responses?.[chosenFormId.value] ?? null
})

// Réponses parsées du formulaire sélectionné
const formResponses = computed(() => {
  if (!selectedFormResponse.value) return null
  const r = selectedFormResponse.value.responses
  if (!r) return null
  if (typeof r === 'string') { try { return JSON.parse(r) } catch { return null } }
  return r
})

const formFields = computed(() => {
  const fields = selectedFormResponse.value?.form?.fields ?? []
  if (!formResponses.value) return fields
  return fields.filter(field => {
    const val = formResponses.value?.[field.id]
    if (val === undefined || val === null || val === '') return false
    if (field.type === 'checkbox') {
      if (Array.isArray(val)) return val.length > 0
      return true
    }
    if (field.type === 'signature') {
      const name = `${val?.first_name ?? ''} ${val?.last_name ?? ''}`.trim()
      return name !== ''
    }
    return String(val).trim() !== ''
  })
})

// Réponse formatée pour un champ (selon son type)
function formatResponse(field) {
  const val = formResponses.value?.[field.id]
  if (val === undefined || val === null || val === '') return '—'
  if (field.type === 'checkbox') {
    if (Array.isArray(val)) return val.length > 0 ? val.join(' · ') : '—'
    return val ? '✓ Oui' : '✗ Non'
  }
  if (field.type === 'signature') return `${val?.first_name ?? ''} ${val?.last_name ?? ''}`.trim() || '—'
  return String(val)
}

function changeForm() {
  router.get(route('reservations.print', props.reservation.id), { form_id: chosenFormId.value }, {
    preserveScroll: true,
    replace: true,
  })
}

function doPrint() {
  window.print()
}
</script>

<template>
  <!-- ═══ BARRE D'OUTILS (masquée à l'impression) ═══ -->
  <div class="no-print bg-gray-800 text-white px-6 py-3 flex items-center gap-4 sticky top-0 z-50 shadow">
    <Link :href="route('reservations.show', reservation.id)"
          class="text-gray-300 hover:text-white text-sm flex items-center gap-1.5">
      ← Retour
    </Link>
    <span class="text-gray-500">|</span>
    <span class="text-sm font-medium">Fiche de sortie — {{ reservation.title }}</span>

    <div class="ml-auto flex items-center gap-3">
      <div class="flex items-center gap-2">
        <label class="text-xs text-gray-400">Formulaire :</label>
        <select v-model="chosenFormId" @change="changeForm"
                class="bg-gray-700 border border-gray-600 text-white text-sm rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option :value="null">— Aucun formulaire —</option>
          <option v-for="f in formsWithResponse" :key="f.id" :value="f.id">{{ f.name }}</option>
          <optgroup v-if="checkoutForms.filter(f => !formsWithResponse.find(r => r.id === f.id)).length > 0"
                    label="─ Non remplis ─">
            <option v-for="f in checkoutForms.filter(f => !formsWithResponse.find(r => r.id === f.id))"
                    :key="f.id" :value="f.id" class="text-gray-400">{{ f.name }}</option>
          </optgroup>
        </select>
      </div>
      <button @click="doPrint"
              class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
        🖨️ Imprimer / Exporter PDF
      </button>
    </div>
  </div>

  <!-- ═══ PAGE A4 ═══ -->
  <div class="print-page">

    <!-- EN-TÊTE -->
    <div class="print-header">

      <!-- Colonne gauche : établissement -->
      <div class="school-info">
        <p class="school-name">{{ schoolName }}</p>
        <p class="school-address">{{ schoolAddress }}</p>
      </div>

      <!-- Colonne centre : titre du document -->
      <div class="doc-info">
        <p class="doc-title">FICHE DE SORTIE DE MATÉRIEL</p>
        <p class="doc-meta">N° {{ reservation.id }} · Imprimée le {{ printDate }}</p>
      </div>

      <!-- Colonne droite : logos lycée + BTS -->
      <div class="logos-col" v-if="appLogos?.lycee || appLogos?.bts">
        <img v-if="appLogos.lycee" :src="appLogos.lycee" class="header-logo" alt="Logo lycée" />
        <span v-if="appLogos.lycee && appLogos.bts" class="logo-sep"></span>
        <img v-if="appLogos.bts"   :src="appLogos.bts"  class="header-logo" alt="Logo BTS"   />
      </div>

    </div>

    <hr class="divider" />

    <!-- INFORMATIONS RÉSERVATION -->
    <section class="section">
      <h2 class="section-title">Réservation</h2>
      <div class="info-grid">
        <div class="info-item">
          <span class="info-label">Titre</span>
          <span class="info-value font-semibold">{{ reservation.title }}</span>
        </div>
        <div class="info-item">
          <span class="info-label">Demandeur</span>
          <span class="info-value">{{ reservation.user?.name }}</span>
        </div>
        <div class="info-item">
          <span class="info-label">Date de début</span>
          <span class="info-value">{{ reservation.start_date_formatted }}</span>
        </div>
        <div class="info-item">
          <span class="info-label">Date de fin prévue</span>
          <span class="info-value">{{ reservation.end_date_formatted }}</span>
        </div>
        <div class="info-item" v-if="reservation.approved_at_formatted">
          <span class="info-label">Validée par l'admin le</span>
          <span class="info-value">{{ reservation.approved_at_formatted }}</span>
        </div>
        <div class="info-item" v-if="showNotes && reservation.notes">
          <span class="info-label">Notes</span>
          <span class="info-value">{{ reservation.notes }}</span>
        </div>
      </div>
    </section>

    <!-- LISTE DES ÉQUIPEMENTS -->
    <section class="section">
      <h2 class="section-title">Équipements sortis ({{ reservation.assets?.length ?? 0 }})</h2>
      <table class="equipment-table">
        <thead>
          <tr>
            <th v-if="showThumbnails" class="col-photo">Photo</th>
            <th v-if="showAssetTag"   class="col-tag">Tag</th>
            <th>Désignation</th>
            <th v-if="showCategory"   class="col-category">Catégorie</th>
            <th class="col-state">État au retrait</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="asset in reservation.assets" :key="asset.id">
            <td v-if="showThumbnails" class="col-photo">
              <img v-if="asset.image_url" :src="asset.image_url" :alt="asset.name"
                   style="width:30px;height:30px;object-fit:cover;border-radius:4px;display:block;" />
              <span v-else style="font-size:16px;line-height:1;">📦</span>
            </td>
            <td v-if="showAssetTag" class="font-mono text-xs">{{ asset.asset_tag }}</td>
            <td>{{ asset.name }}</td>
            <td v-if="showCategory">{{ asset.category?.name ?? '—' }}</td>
            <td><!-- À remplir manuellement --></td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- FORMULAIRE DE SORTIE (si rempli, sélectionné et visible) -->
    <section v-if="showForm && formFields.length > 0 && formResponses && selectedFormResponse" class="section">
      <h2 class="section-title">{{ chosenForm?.name ?? 'Formulaire rempli par l\'utilisateur' }}</h2>
      <div class="form-responses-grid">
        <div v-for="field in formFields" :key="field.id"
             :class="['form-card', field.type === 'signature' ? 'form-card--signature' : '']"
             :style="{ gridColumn: `span ${field.col_span === 2 || field.type === 'signature' ? 2 : 1}` }">
          <p class="form-card-label">{{ field.label }}</p>
          <p :class="['form-card-value', field.type === 'signature' ? 'form-card-value--name' : '']">
            {{ formatResponse(field) }}
          </p>
        </div>
      </div>
    </section>

    <!-- SIGNATURE SUPERVISEUR -->
    <section v-if="reservation.supervisor_signature" class="section">
      <h2 class="section-title">Validation du responsable</h2>
      <div class="supervisor-block">
        <div class="supervisor-info">
          <p class="text-xs text-gray-600">
            Validé par <strong>{{ reservation.supervisor_approved_by?.name ?? '—' }}</strong>
            le {{ reservation.supervisor_approved_at_formatted }}
          </p>
        </div>
        <div class="supervisor-signature-img">
          <img :src="reservation.supervisor_signature" alt="Signature responsable"
               style="max-height: 80px; max-width: 250px;" />
        </div>
      </div>
    </section>

    <!-- SIGNATURES FINALES -->
    <section class="section signatures-section">
      <h2 class="section-title">Signatures</h2>
      <div class="signatures-grid">

        <!-- Signature emprunteur -->
        <div class="signature-block">
          <p class="signature-role">Signature de l'emprunteur</p>
          <p class="signature-name">{{ reservation.user?.name }}</p>
          <div class="signature-area"></div>
          <p class="signature-hint">Date : ___________</p>
        </div>

        <!-- Espace proviseur / tampon -->
        <div class="signature-block">
          <p class="signature-role">{{ proviseurLabel }}</p>
          <p class="signature-name">&nbsp;</p>
          <div class="signature-area stamp-area">
            <p class="stamp-hint">Signature et tampon</p>
          </div>
          <p class="signature-hint">Date : ___________</p>
        </div>

      </div>
    </section>

    <!-- PIED DE PAGE -->
    <footer class="print-footer">
      <p>{{ schoolName }} · Fiche de sortie n° {{ reservation.id }} · Confidentiel</p>
    </footer>

  </div>
</template>

<style scoped>
/* ─── Variables ───────────────────────────────────────────────── */
:root {
  --page-margin: 20mm;
  --font-base: 11pt;
  --color-primary: #1e3a5f;
  --color-border: #ccc;
}

/* ─── Écran : centrer la page A4 ─────────────────────────────── */
.print-page {
  background: white;
  width: 210mm;
  min-height: 297mm;
  margin: 20px auto;
  padding: var(--page-margin);
  box-shadow: 0 4px 24px rgba(0,0,0,.15);
  font-family: 'Arial', sans-serif;
  font-size: var(--font-base);
  color: #111;
  box-sizing: border-box;
}

/* ─── En-tête ─────────────────────────────────────────────────── */
.print-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 10px;
}
.school-info    { flex: 1 1 auto; min-width: 0; }
.doc-info       { flex: 0 0 auto; text-align: right; }
.school-name    { font-size: 14pt; font-weight: bold; color: var(--color-primary); }
.school-address { font-size: 9pt; color: #555; }
.doc-title      { font-size: 14pt; font-weight: bold; text-align: right; color: var(--color-primary); }
.doc-meta       { font-size: 9pt; color: #777; text-align: right; }

/* Logos en haut à droite */
.logos-col {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  gap: 8px;
  padding-left: 12px;
  border-left: 1px solid #ddd;
}
.header-logo {
  height: 52px;
  width: auto;
  max-width: 90px;
  object-fit: contain;
  display: block;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
.logo-sep {
  display: block;
  width: 1px;
  height: 38px;
  background: #ddd;
  flex-shrink: 0;
}

.divider { border: none; border-top: 2px solid var(--color-primary); margin: 8px 0 14px; }

/* ─── Sections ────────────────────────────────────────────────── */
.section { margin-bottom: 16px; }
.section-title {
  font-size: 10pt;
  font-weight: bold;
  text-transform: uppercase;
  letter-spacing: .5px;
  color: var(--color-primary);
  border-bottom: 1px solid var(--color-border);
  padding-bottom: 3px;
  margin-bottom: 8px;
}

/* ─── Grille d'infos ──────────────────────────────────────────── */
.info-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px; }
.info-item  { display: flex; flex-direction: column; }
.info-label { font-size: 8.5pt; color: #666; }
.info-value { font-size: 10pt; }

/* ─── Tableau équipements ─────────────────────────────────────── */
.equipment-table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
.equipment-table th {
  background: var(--color-primary);
  color: white;
  padding: 5px 8px;
  text-align: left;
  font-size: 9pt;
}
.equipment-table td {
  padding: 5px 8px;
  border-bottom: 1px solid #e5e7eb;
  vertical-align: middle;
}
.equipment-table tr:nth-child(even) td { background: #f8f9fa; }
.col-photo    { width: 38px; text-align: center; }
.col-tag      { width: 90px; }
.col-category { width: 100px; }
.col-state    { width: 100px; }

/* ─── Formulaire de sortie redesigné ─────────────────────────── */
.form-responses-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 6px;
}

.form-card {
  border: 1px solid #e5e7eb;
  border-radius: 5px;
  padding: 7px 10px;
  background: #f9fafb;
  break-inside: avoid;
}

/* Champs signature → fond bleu clair (le span est géré en inline style) */
.form-card--signature {
  background: #eff6ff;
  border-color: #bfdbfe;
}

.form-card-label {
  font-size: 7.5pt;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: .3px;
  margin: 0 0 3px;
}

.form-card-value {
  font-size: 10pt;
  font-weight: 600;
  color: #111;
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
}

.form-card-value--name {
  font-size: 11pt;
  color: var(--color-primary);
}

/* ─── Signature superviseur ───────────────────────────────────── */
.supervisor-block {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 8px;
  border: 1px solid #dbeafe;
  background: #eff6ff;
  border-radius: 6px;
}

/* ─── Bloc signatures finales ─────────────────────────────────── */
.signatures-section { margin-top: 20px; }
.signatures-grid    { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.signature-block    { display: flex; flex-direction: column; gap: 4px; }
.signature-role     { font-size: 9pt; font-weight: bold; color: var(--color-primary); }
.signature-name     { font-size: 9pt; color: #444; min-height: 14px; }

.signature-area {
  border: 1.5px solid var(--color-border);
  border-radius: 4px;
  height: 60mm;
  background: #fafafa;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Zone proviseur — plus grande pour tampon */
.stamp-area {
  height: 80mm;
  border: 2px solid var(--color-primary);
  background: white;
}
.stamp-hint {
  font-size: 9pt;
  color: #aaa;
  font-style: italic;
  user-select: none;
}

.signature-hint { font-size: 8.5pt; color: #666; }

/* ─── Pied de page ────────────────────────────────────────────── */
.print-footer {
  margin-top: 20px;
  border-top: 1px solid var(--color-border);
  padding-top: 6px;
  font-size: 8pt;
  color: #999;
  text-align: center;
}

/* ─── Règles impression ───────────────────────────────────────── */
@media print {
  @page {
    size: A4 portrait;
    margin: 15mm 20mm;
  }

  body { background: white !important; }

  .no-print { display: none !important; }

  .print-page {
    width: 100%;
    margin: 0;
    padding: 0;
    box-shadow: none;
  }

  /* Éviter les coupures de page dans les sections importantes */
  .section, .signatures-grid { page-break-inside: avoid; }
  .equipment-table thead     { display: table-header-group; }
  .form-card                 { break-inside: avoid; }

  /* S'assurer que les images s'impriment */
  img { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

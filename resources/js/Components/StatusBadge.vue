<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
  status: String,
  type:   { type: String, default: 'reservation' },
})

// Palette complète (toutes classes référencées pour que Tailwind ne les purge pas)
const colorMap = {
  green:  { bg: 'bg-green-100',  text: 'text-green-800'  },
  blue:   { bg: 'bg-blue-100',   text: 'text-blue-800'   },
  purple: { bg: 'bg-purple-100', text: 'text-purple-800' },
  yellow: { bg: 'bg-yellow-100', text: 'text-yellow-800' },
  orange: { bg: 'bg-orange-100', text: 'text-orange-800' },
  red:    { bg: 'bg-red-100',    text: 'text-red-800'    },
  gray:   { bg: 'bg-gray-100',   text: 'text-gray-800'   },
  indigo: { bg: 'bg-indigo-100', text: 'text-indigo-800' },
  pink:   { bg: 'bg-pink-100',   text: 'text-pink-800'   },
  teal:   { bg: 'bg-teal-100',   text: 'text-teal-800'   },
}

// Statuts de réservation (fixes)
const reservationConfig = {
  awaiting_supervisor: { label: 'En attente du responsable', color: 'purple' },
  pending:   { label: 'En attente', color: 'yellow'  },
  approved:  { label: 'Validée',    color: 'blue'    },
  active:    { label: 'En cours',   color: 'green'   },
  returned:  { label: 'Retourné',   color: 'gray'    },
  cancelled: { label: 'Annulée',    color: 'red'     },
  rejected:  { label: 'Refusée',    color: 'red'     },
}

const page = usePage()

const info = computed(() => {
  if (props.type === 'reservation') {
    const r = reservationConfig[props.status]
    const c = colorMap[r?.color ?? 'gray']
    return {
      label: r?.label ?? props.status,
      class: `${c.bg} ${c.text}`,
    }
  }

  // Type 'asset' — utilise les statuts dynamiques partagés
  const assetStatuses = page.props.assetStatuses ?? {}
  const s = assetStatuses[props.status]
  if (s) {
    const c = colorMap[s.color] ?? colorMap.gray
    return { label: s.label, class: `${c.bg} ${c.text}` }
  }

  // Fallback si le statut n'est pas encore chargé
  return { label: props.status ?? '—', class: 'bg-gray-100 text-gray-800' }
})
</script>

<template>
  <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', info.class]">
    {{ info.label }}
  </span>
</template>

<script setup>
/**
 * AssetThumbnail — Miniature universelle d'équipement.
 * Affiche l'image si disponible, sinon un emoji 📦 comme fallback.
 *
 * Props :
 *   src  — URL de l'image (null = fallback)
 *   alt  — Texte alternatif (nom de l'équipement)
 *   size — Taille : 'xs' (24px) | 'sm' (32px) | 'md' (40px, défaut) | 'lg' (56px)
 */
defineProps({
  src:  { type: String, default: null },
  alt:  { type: String, default: '' },
  size: { type: String, default: 'md' },
})
</script>

<template>
  <div :class="[
    'rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center bg-gray-100',
    size === 'xs' ? 'w-6 h-6'   :
    size === 'sm' ? 'w-8 h-8'   :
    size === 'lg' ? 'w-14 h-14' :
    'w-10 h-10'
  ]">
    <img v-if="src" :src="src" :alt="alt" class="w-full h-full object-cover" />
    <span v-else :class="[
      size === 'xs' ? 'text-xs'  :
      size === 'sm' ? 'text-sm'  :
      size === 'lg' ? 'text-2xl' :
      'text-lg'
    ]">📦</span>
  </div>
</template>

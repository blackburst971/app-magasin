import { ref, watch } from 'vue'

/**
 * Persistance de la vue (grille/liste) dans le localStorage.
 * @param {string} storageKey  Clé localStorage unique par page.
 * @param {string} defaultMode Mode par défaut ('grid' ou 'list').
 */
export function useViewMode(storageKey, defaultMode = 'grid') {
  const stored   = typeof localStorage !== 'undefined' ? localStorage.getItem(storageKey) : null
  const viewMode = ref(stored ?? defaultMode)
  watch(viewMode, v => localStorage.setItem(storageKey, v))
  return viewMode
}

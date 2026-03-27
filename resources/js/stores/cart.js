import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useCartStore = defineStore('cart', () => {
  // Restore from localStorage — handle both old format (array) and new format ({ items, kitItems })
  const stored = localStorage.getItem('reservation_cart')
  let parsedItems = []
  let parsedKitItems = []
  if (stored) {
    const parsed = JSON.parse(stored)
    if (Array.isArray(parsed)) {
      parsedItems = parsed  // ancien format : tableau direct
    } else {
      parsedItems    = parsed.items    ?? []
      parsedKitItems = parsed.kitItems ?? []
    }
  }

  const items    = ref(parsedItems)
  const kitItems = ref(parsedKitItems)

  const count   = computed(() => items.value.length + kitItems.value.length)
  const isEmpty = computed(() => items.value.length === 0 && kitItems.value.length === 0)

  function save() {
    localStorage.setItem('reservation_cart', JSON.stringify({
      items:    items.value,
      kitItems: kitItems.value,
    }))
  }

  // ── Équipements individuels ────────────────────────────────────────────────

  function addItem(asset) {
    if (!items.value.find(i => i.id === asset.id)) {
      items.value.push({
        id:        asset.id,
        name:      asset.name,
        asset_tag: asset.asset_tag,
        category:  asset.category ?? null,
        status:    asset.status,
        image:     asset.image ?? null,
      })
      save()
    }
  }

  function removeItem(assetId) {
    items.value = items.value.filter(i => i.id !== assetId)
    save()
  }

  function hasItem(assetId) {
    return !!items.value.find(i => i.id === assetId)
  }

  // ── Kits ───────────────────────────────────────────────────────────────────

  function addKit(kit) {
    if (!kitItems.value.find(k => k.id === kit.id)) {
      kitItems.value.push({
        id:           kit.id,
        name:         kit.name,
        description:  kit.description ?? null,
        image:        kit.image ?? null,
        status:       kit.status,
        status_label: kit.status_label,
        assets:       kit.assets ?? [],
      })
      save()
    }
  }

  function removeKit(kitId) {
    kitItems.value = kitItems.value.filter(k => k.id !== kitId)
    save()
  }

  function hasKit(kitId) {
    return !!kitItems.value.find(k => k.id === kitId)
  }

  // ── Global ─────────────────────────────────────────────────────────────────

  function clearCart() {
    items.value    = []
    kitItems.value = []
    save()
  }

  return {
    items, kitItems,
    count, isEmpty,
    addItem, removeItem, hasItem,
    addKit,  removeKit,  hasKit,
    clearCart,
  }
})

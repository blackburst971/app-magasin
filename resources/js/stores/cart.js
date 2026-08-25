import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export const useCartStore = defineStore('cart', () => {
  // Clé localStorage isolée par utilisateur pour éviter le partage entre comptes sur le même navigateur
  function storageKey() {
    const userId = usePage().props?.auth?.user?.id
    return userId ? `reservation_cart_${userId}` : 'reservation_cart'
  }

  // Restore from localStorage — handle both old format (array) and new format ({ items, kitItems })
  function loadFromStorage() {
    const stored = localStorage.getItem(storageKey())
    if (!stored) return { items: [], kitItems: [] }
    try {
      const parsed = JSON.parse(stored)
      if (Array.isArray(parsed)) {
        return { items: parsed, kitItems: [] } // ancien format
      }
      return {
        items:    parsed.items    ?? [],
        kitItems: parsed.kitItems ?? [],
      }
    } catch {
      return { items: [], kitItems: [] }
    }
  }

  const initial   = loadFromStorage()
  const items     = ref(initial.items)
  const kitItems  = ref(initial.kitItems)

  const count   = computed(() => items.value.length + kitItems.value.length)
  const isEmpty = computed(() => items.value.length === 0 && kitItems.value.length === 0)

  function save() {
    localStorage.setItem(storageKey(), JSON.stringify({
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
    clearCart, save,
  }
})

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export const useSelectionStore = defineStore('selection', () => {
  const selectedAssets = ref([])
  let initialized = false

  // Clé localStorage isolée par utilisateur
  function storageKey() {
    try {
      const userId = usePage().props?.auth?.user?.id
      return userId ? `asset_selection_${userId}` : 'asset_selection'
    } catch {
      return 'asset_selection'
    }
  }

  // Restaurer depuis localStorage (lazy)
  function loadFromStorage() {
    if (initialized) return
    initialized = true

    try {
      const stored = localStorage.getItem(storageKey())
      if (stored) {
        selectedAssets.value = JSON.parse(stored)
      }
    } catch {
      selectedAssets.value = []
    }
  }

  // Charger au premier accès
  loadFromStorage()

  const count = computed(() => selectedAssets.value.length)
  const isEmpty = computed(() => selectedAssets.value.length === 0)
  const selectedIds = computed(() => selectedAssets.value.map(a => a.id))

  function save() {
    localStorage.setItem(storageKey(), JSON.stringify(selectedAssets.value))
  }

  function addAsset(asset) {
    if (!selectedAssets.value.find(a => a.id === asset.id)) {
      selectedAssets.value.push({
        id:           asset.id,
        name:         asset.name,
        asset_tag:    asset.asset_tag,
        serial:       asset.serial ?? null,
        category:     asset.category?.name ?? null,
        model:        asset.equipmentModel?.name ?? asset.model ?? null,
        manufacturer: asset.manufacturer?.name ?? asset.manufacturer ?? null,
        location:     asset.location ?? null,
        status:       asset.status,
        image:        asset.image ?? null,
      })
      save()
    }
  }

  function removeAsset(assetId) {
    selectedAssets.value = selectedAssets.value.filter(a => a.id !== assetId)
    save()
  }

  function toggleAsset(asset) {
    if (isSelected(asset.id)) {
      removeAsset(asset.id)
    } else {
      addAsset(asset)
    }
  }

  function isSelected(assetId) {
    return !!selectedAssets.value.find(a => a.id === assetId)
  }

  function clearSelection() {
    selectedAssets.value = []
    save()
  }

  function selectAll(assets) {
    assets.forEach(asset => {
      if (!isSelected(asset.id)) {
        addAsset(asset)
      }
    })
  }

  function deselectAll(assets) {
    const assetIds = assets.map(a => a.id)
    selectedAssets.value = selectedAssets.value.filter(a => !assetIds.includes(a.id))
    save()
  }

  return {
    selectedAssets,
    selectedIds,
    count,
    isEmpty,
    addAsset,
    removeAsset,
    toggleAsset,
    isSelected,
    clearSelection,
    selectAll,
    deselectAll,
    save,
  }
})

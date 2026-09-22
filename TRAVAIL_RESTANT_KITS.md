# Travail restant - Workflow complet des kits

## ✅ Complété

1. **Statut dynamique des kits amélioré**
   - Les kits détectent maintenant correctement leur statut même si réservés via équipements individuels
   - Fonctionne pour réservations mixtes (kits + équipements)

2. **Nettoyage de 77 équipements bloqués**
   - Équipements coincés en 'checked_out' avec réservations retournées corrigés

## 🔨 À implémenter

### 1. Workflow d'approbation - Statut 'reserved' pour les kits

**Problème actuel**: Quand une réservation contenant un kit est approuvée, le kit ne passe pas en statut 'reserved'.

**Solution à implémenter**:
- Dans `app/Http/Controllers/ReservationController.php` méthode `approve()`:
  - Détecter si des kits complets sont dans la réservation
  - Mettre à jour `kit.status = 'reserved'` dans la table `kits`
  - Créer un `AssetMovement` pour tracer le changement

**Code suggéré**:
```php
// Dans ReservationController@approve, après l'approbation:
$this->updateKitsStatus($reservation, 'reserved');
```

### 2. Workflow de scan - Passage 'reserved' → 'checked_out'

**Problème actuel**: Quand tous les équipements d'un kit sont scannés, le statut statique du kit reste 'available' ou 'reserved'.

**Solution à implémenter**:
- Dans `app/Http/Controllers/AttributionController.php` après chaque scan:
  - Vérifier si l'équipement scanné appartient à un kit
  - Vérifier si TOUS les équipements du kit sont maintenant scannés
  - Si oui: mettre à jour `kit.status = 'checked_out'`
  - Créer un `AssetMovement` pour le kit

**Code suggéré**:
```php
// Après le scan d'un asset, dans AttributionController@scanCheckout:
$this->checkAndUpdateKitStatus($asset, $reservation);

private function checkAndUpdateKitStatus($asset, $reservation) {
    foreach ($asset->kits as $kit) {
        $allScanned = $kit->assets->every(function($a) use ($reservation) {
            return $reservation->assets->contains(function($ra) use ($a) {
                return $ra->id === $a->id && $ra->pivot->checkout_at !== null;
            });
        });

        if ($allScanned && $kit->status !== 'checked_out') {
            $kit->update(['status' => 'checked_out']);
            AssetMovement::create([...]);
        }
    }
}
```

### 3. Workflow de retour des kits

**Problème actuel**: Quand un kit est retourné, son statut statique ne revient pas à 'available'.

**Solution à implémenter**:
- Dans `app/Http/Controllers/ReturnController.php` méthode `resetKitStatusOnReturn()`:
  - Vérifier si TOUS les équipements du kit sont retournés
  - Si oui: remettre `kit.status = 'available'`
  - Créer un `AssetMovement` pour le kit

### 4. Organisation visuelle kits/équipements dans les réservations

**Problème actuel**: Dans l'affichage des réservations, kits et équipements individuels sont mélangés.

**Solution à implémenter**:
- Dans les composants Vue affichant les réservations:
  - Grouper visuellement les équipements par kit
  - Afficher un en-tête pour chaque kit avec son statut
  - Indenter les équipements du kit sous leur kit parent
  - Afficher séparément les équipements individuels

**Fichiers concernés**:
- `resources/js/Pages/Returns/Index.vue`
- `resources/js/Pages/Attribution/Index.vue`
- `resources/js/Pages/Reservations/*`

### 5. Bug création de préparation depuis réservation

**Symptôme**: Erreur silencieuse lors de la création d'une préparation depuis une réservation.

**À investiguer**:
- Vérifier les logs Laravel lors de la création
- Tester la création manuelle via Tinker
- Vérifier si c'est lié aux kits ou aux équipements individuels

## 📝 Notes importantes

1. **Statut statique vs dynamique**:
   - `kit.status` (statique, en DB) doit être mis à jour lors des actions
   - `kit.current_status` (dynamique, calculé) détecte automatiquement mais peut être lent

2. **Performance**:
   - Le statut dynamique fait plusieurs requêtes
   - Pour l'affichage en liste, mieux utiliser le statut statique mis à jour

3. **Cohérence**:
   - Toujours créer un `AssetMovement` lors du changement de statut
   - Utiliser des transactions pour les opérations multi-étapes

## 🚀 Ordre d'implémentation suggéré

1. Workflow d'approbation (le plus simple)
2. Workflow de scan (impact le plus important)
3. Workflow de retour (complémente le scan)
4. Organisation visuelle (amélioration UX)
5. Bug préparation (investigation requise)

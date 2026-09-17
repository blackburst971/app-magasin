# 📍 Demandes de Changement d'Adresse IP - Guide Utilisateur

## Pour les **utilisateurs non-admin** :

### Comment faire une demande de changement d'IP ?

1. **Accéder à la page Postes**
   - Cliquez sur "Postes" dans le menu latéral

2. **Ouvrir le modal d'édition**
   - Cliquez sur le bouton "Modifier" (icône crayon) du poste concerné
   - OU cliquez sur le poste puis sur "Modifier" dans le panneau de détails

3. **Modifier les adresses IP**
   - Dans le formulaire, localisez la section "Adresses IP"
   - Modifiez les adresses IP existantes ou ajoutez-en de nouvelles
   - Vous pouvez ajouter une description pour chaque IP

4. **Soumettre la demande**
   - Cliquez sur "Enregistrer"
   - Votre demande sera automatiquement envoyée à l'administrateur
   - Un message de confirmation apparaîtra : "Demande de changement d'adresse IP envoyée"

5. **Attendre la validation**
   - L'administrateur recevra une notification
   - Vous recevrez une notification une fois la demande approuvée ou rejetée
   - Les changements prendront effet immédiatement après approbation

### Restrictions pour les utilisateurs non-admin :
- ❌ **NE PEUVENT PAS** modifier : nom, emplacement, type, OS, processeur, RAM, notes
- ✅ **PEUVENT** consulter toutes les informations en lecture seule
- ✅ **PEUVENT** demander des changements d'adresse IP (validation requise)

---

## Pour l'**administrateur** :

### Réception et traitement des demandes :

1. **Notification de demande**
   - Une notification apparaît lorsqu'un utilisateur demande un changement d'IP
   - Le message indique : "[Utilisateur] a demandé un changement d'adresse IP pour le poste [Nom]"

2. **Accéder aux demandes en attente**
   - Les demandes sont stockées dans la base de données (`computer_ip_change_requests`)
   - **À VENIR** : Interface dédiée pour gérer les demandes

3. **Approuver ou rejeter**
   - Une fois l'interface implémentée, l'admin pourra :
     - Voir l'ancienne et la nouvelle adresse IP
     - Approuver → changement effectif immédiat
     - Rejeter → aucun changement, notification à l'utilisateur

### Modifications admin :
- ✅ **PEUT** modifier **tous les champs** directement (sans demande)
- ✅ Le nom du Computer reste **indépendant** du nom dans l'inventaire (Asset)
- ✅ Le champ **Emplacement** est uniquement modifiable par l'admin

---

## Champ "Emplacement" :
- **Visible par** : Tous les utilisateurs
- **Modifiable par** : Admin uniquement
- **Utilité** : Indiquer où se trouve physiquement le poste (salle, bureau, bâtiment, etc.)
- **Exemple** : "Salle B101", "Bureau RH - 2ème étage", "Laboratoire informatique"

---

## Statut technique :

### ✅ Implémenté :
- Table `computer_ip_change_requests` créée
- Notifications pour admin et utilisateur
- Système de demande/approbation/rejet backend
- Routes API pour récupérer les demandes

### 🚧 À implémenter (interface Vue) :
- Modal/section pour afficher les demandes en attente (admin)
- Boutons Approuver/Rejeter dans l'interface
- Badge "En attente" pour les utilisateurs qui ont fait une demande
- Historique des demandes approuvées/rejetées

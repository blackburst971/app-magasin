<template>
    <AppLayout :title="asset.name">
        <div class="max-w-4xl mx-auto">

            <!-- Bannière accessoire (visible utilisateurs non-admin) -->
            <div v-if="asset.parent_asset && !$page.props.auth.user.is_admin"
                 class="mb-4 flex items-center gap-3 bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3">
                <span class="text-2xl">🔗</span>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-indigo-800">
                        Cet équipement est un accessoire — il sera automatiquement réservé avec son équipement principal.
                    </p>
                    <p class="text-xs text-indigo-600 mt-0.5">
                        Réservez plutôt :
                        <Link :href="route('assets.show', asset.parent_asset.id)"
                              class="font-bold underline hover:text-indigo-800">
                            {{ asset.parent_asset.name }}
                        </Link>
                    </p>
                </div>
            </div>

            <!-- ══ BANNIÈRE ÉTAT DE L'ÉQUIPEMENT ════════════════════════════ -->
            <!-- Visible par tous si renseignée. Editable inline par l'admin.  -->
            <div v-if="localConditionNote || isAdmin" class="mb-4">
                <!-- Mode lecture -->
                <div v-if="!editingConditionNote && localConditionNote"
                     class="flex items-start gap-3 bg-amber-50 border border-amber-300 rounded-xl px-4 py-3">
                    <span class="text-2xl flex-shrink-0 mt-0.5">📋</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-0.5">État de l'équipement</p>
                        <p class="text-sm text-amber-900 whitespace-pre-wrap">{{ localConditionNote }}</p>
                    </div>
                    <button v-if="isAdmin"
                            @click="editingConditionNote = true"
                            class="flex-shrink-0 p-1.5 text-amber-600 hover:text-amber-800 hover:bg-amber-100 rounded-lg transition"
                            title="Modifier">
                        <PencilIcon class="w-4 h-4" />
                    </button>
                </div>
                <!-- Mode placeholder (admin, pas de note) -->
                <div v-else-if="!editingConditionNote && isAdmin"
                     @click="editingConditionNote = true"
                     class="flex items-center gap-3 border-2 border-dashed border-amber-200 rounded-xl px-4 py-3 cursor-pointer hover:border-amber-400 hover:bg-amber-50 transition group">
                    <span class="text-xl opacity-40">📋</span>
                    <p class="text-sm text-gray-400 group-hover:text-amber-600 transition">
                        Ajouter une note d'état visible par les utilisateurs (emojis autorisés)…
                    </p>
                </div>
                <!-- Mode édition (admin) -->
                <div v-if="editingConditionNote" class="bg-amber-50 border border-amber-300 rounded-xl p-4">
                    <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-2">Note d'état de l'équipement</p>
                    <textarea v-model="localConditionNote" rows="3"
                              placeholder="Ex : ⚠️ Objectif légèrement rayé — fonctionne correctement. Câble HDMI à demander séparément."
                              class="w-full border border-amber-300 bg-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none resize-none"
                              @keydown.escape="cancelConditionNote" />
                    <div class="flex gap-2 mt-2">
                        <button @click="saveConditionNote" :disabled="savingConditionNote"
                                class="px-4 py-1.5 bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white text-sm font-semibold rounded-lg transition">
                            {{ savingConditionNote ? '…' : '✓ Enregistrer' }}
                        </button>
                        <button @click="cancelConditionNote"
                                class="px-4 py-1.5 border border-gray-300 text-gray-600 text-sm rounded-lg hover:bg-gray-50 transition">
                            Annuler
                        </button>
                    </div>
                </div>
            </div>

            <!-- En-tête -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <button @click="goBack" class="text-gray-500 hover:text-gray-700">
                        <ArrowLeftIcon class="w-5 h-5" />
                    </button>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ asset.name }}</h1>
                        <p class="text-sm text-gray-500 mt-0.5">Tag : {{ asset.asset_tag }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <template v-if="$page.props.auth.user.is_admin">
                        <Link :href="route('assets.edit', asset.id)" class="btn-secondary">
                            Modifier
                        </Link>
                        <button @click="confirmDelete"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-red-600 border border-red-200 hover:bg-red-50 transition">
                            <TrashIcon class="w-4 h-4" />
                            Supprimer
                        </button>
                    </template>
                    <StatusBadge :status="asset.status" :label="asset.status_label" :color="asset.status_color" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Infos principales -->
                <div class="md:col-span-2 space-y-6">
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Informations</h2>
                        <dl class="grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-xs text-gray-500">Catégorie</dt>
                                <dd class="font-medium text-gray-900 mt-0.5">{{ asset.category?.name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Modèle</dt>
                                <dd class="font-medium text-gray-900 mt-0.5">
                                    <Link v-if="asset.model && isAdmin"
                                          :href="route('models.index') + '?highlight=' + asset.model.id"
                                          class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 hover:underline">
                                        {{ asset.model.name }}
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                    </Link>
                                    <span v-else-if="asset.model">{{ asset.model.name }}</span>
                                    <span v-else>—</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Fabricant</dt>
                                <dd class="font-medium text-gray-900 mt-0.5">{{ asset.manufacturer || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">N° de série</dt>
                                <dd class="font-medium text-gray-900 mt-0.5">{{ asset.serial || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Emplacement</dt>
                                <dd class="font-medium text-gray-900 mt-0.5">{{ asset.location || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Date d'achat</dt>
                                <dd class="font-medium text-gray-900 mt-0.5">{{ asset.purchase_date || '—' }}</dd>
                            </div>
                            <div v-if="asset.purchase_cost">
                                <dt class="text-xs text-gray-500">Coût d'achat</dt>
                                <dd class="font-medium text-gray-900 mt-0.5">{{ asset.purchase_cost }} €</dd>
                            </div>
                            <div v-if="asset.supplier">
                                <dt class="text-xs text-gray-500">Fournisseur</dt>
                                <dd class="font-medium text-gray-900 mt-0.5">{{ asset.supplier.name }}</dd>
                            </div>
                        </dl>
                        <div v-if="asset.notes" class="mt-4 pt-4 border-t">
                            <dt class="text-xs text-gray-500 mb-1">Notes</dt>
                            <dd class="text-gray-700 text-sm">{{ asset.notes }}</dd>
                        </div>
                    </div>

                    <!-- ══ FICHE PUBLIQUE ══════════════════════════════════ -->
                    <!-- Section visible par tous. Admin peut éditer inline.  -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <!-- En-tête -->
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <DocumentIcon class="w-4 h-4" />
                                Présentation de l'équipement
                            </h2>
                            <button v-if="isAdmin && !editingPublicInfo"
                                    @click="openPublicEdit"
                                    class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 border border-blue-200 px-2.5 py-1 rounded-lg hover:bg-blue-50 transition">
                                <PencilIcon class="w-3.5 h-3.5" /> Modifier
                            </button>
                        </div>

                        <!-- ── MODE LECTURE ─────────────────────────────── -->
                        <template v-if="!editingPublicInfo">
                            <!-- Description publique -->
                            <div v-if="asset.public_description"
                                 class="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap mb-4">{{ asset.public_description }}</div>
                            <p v-else-if="isAdmin" class="text-sm text-gray-400 italic mb-4">
                                Aucune description publique — cliquez "Modifier" pour en ajouter une.
                            </p>
                            <p v-else class="text-sm text-gray-400 italic mb-4">Aucune description disponible.</p>

                            <!-- Champs personnalisés -->
                            <dl v-if="asset.custom_fields && asset.custom_fields.length"
                                class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 border-t pt-4">
                                <div v-for="f in asset.custom_fields" :key="f.key">
                                    <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ f.key }}</dt>
                                    <dd class="text-sm font-medium text-gray-900 mt-0.5">{{ f.value }}</dd>
                                </div>
                            </dl>
                        </template>

                        <!-- ── MODE ÉDITION (admin) ─────────────────────── -->
                        <template v-else>
                            <div class="space-y-5">
                                <!-- Description -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                        Description publique
                                        <span class="font-normal text-gray-400 ml-1">(visible par les utilisateurs)</span>
                                    </label>
                                    <textarea v-model="publicForm.public_description" rows="5"
                                              placeholder="Décrivez l'équipement, ses caractéristiques, son usage…"
                                              class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none resize-y" />
                                </div>

                                <!-- Champs personnalisés -->
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="text-xs font-semibold text-gray-600">Champs personnalisés</label>
                                        <button type="button" @click="addCustomField"
                                                class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 border border-blue-200 px-2 py-1 rounded-lg hover:bg-blue-50 transition">
                                            <PlusIcon class="w-3.5 h-3.5" /> Ajouter un champ
                                        </button>
                                    </div>
                                    <div v-if="publicForm.custom_fields.length === 0"
                                         class="text-xs text-gray-400 text-center py-4 border border-dashed border-gray-200 rounded-xl">
                                        Aucun champ personnalisé. Cliquez "Ajouter" pour en créer.
                                    </div>
                                    <div class="space-y-2">
                                        <div v-for="(field, i) in publicForm.custom_fields" :key="i"
                                             class="flex items-center gap-2">
                                            <input v-model="field.key" type="text" placeholder="Étiquette (ex: Résolution)"
                                                   class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none" />
                                            <input v-model="field.value" type="text" placeholder="Valeur (ex: 1920×1080)"
                                                   class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-400 outline-none" />
                                            <button type="button" @click="removeCustomField(i)"
                                                    class="text-gray-400 hover:text-red-500 p-1 rounded transition flex-shrink-0">
                                                <XMarkIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-3 pt-2 border-t">
                                    <button @click="savePublicInfo" :disabled="savingPublicInfo"
                                            class="flex-1 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                                        {{ savingPublicInfo ? 'Enregistrement…' : '✓ Enregistrer' }}
                                    </button>
                                    <button @click="editingPublicInfo = false"
                                            class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-xl text-sm hover:bg-gray-50 transition">
                                        Annuler
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- ══ COMPOSANTS ════════════════════════════════════ -->
                    <div v-if="localComponents.length > 0 || isAdmin"
                         class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <PuzzlePieceIcon class="w-4 h-4" />
                                Composants
                                <span v-if="localComponents.length" class="text-indigo-500 text-xs font-normal normal-case">({{ localComponents.length }})</span>
                            </h2>
                            <button v-if="isAdmin" @click="openAddComponent"
                                    class="inline-flex items-center gap-1.5 text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg font-medium transition">
                                <PlusIcon class="w-3.5 h-3.5" /> Ajouter
                            </button>
                        </div>

                        <!-- Liste des composants -->
                        <div v-if="localComponents.length === 0 && isAdmin"
                             class="text-center py-6 border-2 border-dashed border-gray-200 rounded-xl text-gray-400 text-sm">
                            <PuzzlePieceIcon class="w-10 h-10 mx-auto mb-2 opacity-30" />
                            Aucun composant. Cliquez "Ajouter" pour en définir.
                        </div>

                        <ul v-else class="space-y-2">
                            <li v-for="comp in localComponents" :key="comp.id"
                                class="flex items-center gap-3 px-4 py-3 rounded-xl border border-gray-100 bg-gray-50 group"
                                :class="comp.condition === 'missing' ? 'border-red-200 bg-red-50/40' : comp.condition === 'damaged' ? 'border-orange-200 bg-orange-50/40' : comp.condition === 'defective' ? 'border-yellow-200 bg-yellow-50/40' : ''">
                                <!-- Icône quantité -->
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold">
                                    {{ comp.quantity }}
                                </div>
                                <!-- Infos -->
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ comp.name }}</p>
                                    <p v-if="comp.description" class="text-xs text-gray-500 mt-0.5 truncate">{{ comp.description }}</p>
                                </div>
                                <!-- Badge condition (toujours visible si défini) -->
                                <span v-if="comp.condition" class="flex-shrink-0 text-sm px-2 py-0.5 rounded-full font-medium"
                                      :class="componentConditionClass(comp.condition)">
                                    {{ componentConditionLabel(comp.condition) }}
                                </span>
                                <!-- Actions admin -->
                                <div v-if="isAdmin" class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <!-- Cycle condition -->
                                    <button @click="cycleComponentCondition(comp)"
                                            class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition"
                                            :title="comp.condition ? 'Changer l\'état' : 'Définir un état'">
                                        <span class="text-xs">{{ comp.condition ? componentConditionLabel(comp.condition) : '—' }}</span>
                                    </button>
                                    <button @click="openEditComponent(comp)"
                                            class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                                        <PencilIcon class="w-3.5 h-3.5" />
                                    </button>
                                    <button @click="deleteComponent(comp)"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                                        <TrashIcon class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </li>
                        </ul>

                        <!-- Feedback propagation -->
                        <Transition name="fade">
                            <div v-if="componentFeedback"
                                 :class="componentFeedback.type === 'propagated'
                                    ? 'bg-indigo-50 border-indigo-200 text-indigo-800'
                                    : componentFeedback.type === 'warning'
                                        ? 'bg-orange-50 border-orange-200 text-orange-800'
                                        : 'bg-green-50 border-green-200 text-green-800'"
                                 class="mt-3 border rounded-xl px-4 py-2.5 text-sm font-medium">
                                {{ componentFeedback.message }}
                            </div>
                        </Transition>

                        <!-- Formulaire ajout / édition inline -->
                        <div v-if="showComponentForm" class="mt-4 border-t pt-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                                {{ editingComponent ? 'Modifier le composant' : 'Nouveau composant' }}
                            </p>
                            <!-- Hint propagation -->
                            <p v-if="!editingComponent && hasModel" class="text-xs text-indigo-600 bg-indigo-50 rounded-lg px-3 py-1.5 mb-3 flex items-center gap-1.5">
                                <span>🔗</span>
                                Ce composant sera automatiquement ajouté avec le numéro correspondant à tous les équipements du même modèle.
                            </p>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-500 mb-1">Nom de base <span class="text-red-400">*</span></label>
                                    <input v-model="componentForm.name" type="text" placeholder="ex: Câble alimentation, Notice, Télécommande…"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none" />
                                </div>
                                <div class="w-20">
                                    <label class="block text-xs text-gray-500 mb-1">Qté</label>
                                    <input v-model.number="componentForm.quantity" type="number" min="1" max="999"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none" />
                                </div>
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-500 mb-1">Description (optionnel)</label>
                                    <input v-model="componentForm.description" type="text" placeholder="Détails supplémentaires…"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none" />
                                </div>
                                <div class="flex gap-2 flex-shrink-0">
                                    <button @click="submitComponent" :disabled="!componentForm.name.trim() || savingComponent"
                                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition">
                                        {{ savingComponent ? '…' : (editingComponent ? 'Modifier' : 'Ajouter') }}
                                    </button>
                                    <button @click="cancelComponentForm"
                                            class="px-4 py-2 border border-gray-300 text-gray-600 text-sm rounded-lg hover:bg-gray-50 transition">
                                        Annuler
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ MODAL : Suppression composant (propagation) ══════ -->
                    <Teleport to="body">
                        <div v-if="showDeleteCompModal"
                             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                                <div class="bg-red-500 px-6 py-4 text-white">
                                    <p class="text-xs font-semibold text-red-100 uppercase tracking-widest mb-1">Supprimer le composant</p>
                                    <p class="text-base font-bold">{{ deleteCompTarget ? stripNumberSuffix(deleteCompTarget.name) : '' }}</p>
                                </div>
                                <div class="px-6 py-5 space-y-3">
                                    <p class="text-sm text-gray-600">Cet équipement fait partie d'un modèle. Comment voulez-vous supprimer ce composant ?</p>
                                    <button @click="doDeleteComponent(deleteCompTarget, false)"
                                            class="w-full text-left flex items-start gap-3 border border-gray-200 rounded-xl px-4 py-3 hover:bg-gray-50 transition">
                                        <span class="text-lg mt-0.5">📌</span>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">Supprimer uniquement sur cet équipement</p>
                                            <p class="text-xs text-gray-500 mt-0.5">Les autres équipements du modèle conservent leur composant.</p>
                                        </div>
                                    </button>
                                    <button @click="doDeleteComponent(deleteCompTarget, true)"
                                            class="w-full text-left flex items-start gap-3 border border-red-200 rounded-xl px-4 py-3 hover:bg-red-50 transition">
                                        <span class="text-lg mt-0.5">🗑️</span>
                                        <div>
                                            <p class="text-sm font-semibold text-red-700">Supprimer sur TOUS les équipements du même modèle</p>
                                            <p class="text-xs text-red-400 mt-0.5">Supprime « {{ deleteCompTarget ? stripNumberSuffix(deleteCompTarget.name) : '' }} » de tous les équipements de ce modèle.</p>
                                        </div>
                                    </button>
                                    <button @click="showDeleteCompModal = false"
                                            class="w-full py-2 text-sm text-gray-500 hover:text-gray-700 transition">
                                        Annuler
                                    </button>
                                </div>
                            </div>
                        </div>
                    </Teleport>

                    <!-- ══ FICHIERS ATTACHÉS (images + PDFs) ══════════════ -->
                    <div v-if="assetFiles.length > 0 || isAdmin"
                         class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <PaperClipIcon class="w-4 h-4" />
                                Fichiers attachés
                                <span v-if="assetFiles.length" class="text-blue-500 text-xs font-normal normal-case">({{ assetFiles.length }})</span>
                            </h2>
                            <!-- Upload (admin) -->
                            <template v-if="isAdmin">
                                <input ref="fileUploadInput" type="file"
                                       accept="image/*,application/pdf"
                                       class="hidden" @change="handleFileUpload" />
                                <button @click="triggerUpload" :disabled="uploadingFile"
                                        class="inline-flex items-center gap-1.5 text-xs bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white px-3 py-1.5 rounded-lg font-medium transition">
                                    <PlusIcon class="w-3.5 h-3.5" />
                                    {{ uploadingFile ? 'Upload…' : 'Ajouter un fichier' }}
                                </button>
                            </template>
                        </div>

                        <div v-if="assetFiles.length === 0 && isAdmin"
                             class="text-center py-6 border-2 border-dashed border-gray-200 rounded-xl text-gray-400 text-sm">
                            <PhotoIcon class="w-10 h-10 mx-auto mb-2 opacity-30" />
                            Aucun fichier. Cliquez "Ajouter" pour joindre des images ou PDF.
                        </div>

                        <!-- Galerie images -->
                        <div v-if="imageFiles.length"
                             class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                            <div v-for="f in imageFiles" :key="f.id"
                                 class="group relative rounded-xl overflow-hidden border border-gray-100 bg-gray-50 aspect-video">
                                <img :src="f.url" :alt="f.name" class="w-full h-full object-cover" />
                                <!-- Overlay admin -->
                                <div v-if="isAdmin"
                                     class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100">
                                    <button @click="startRename(f)"
                                            class="bg-white/90 text-gray-800 p-1.5 rounded-lg hover:bg-white transition"
                                            title="Renommer">
                                        <PencilIcon class="w-3.5 h-3.5" />
                                    </button>
                                    <button @click="deleteFile(f.id)"
                                            class="bg-red-500/90 text-white p-1.5 rounded-lg hover:bg-red-600 transition"
                                            title="Supprimer">
                                        <TrashIcon class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                                <!-- Nom / renommage -->
                                <div v-if="renamingFileId === f.id"
                                     class="absolute bottom-0 inset-x-0 p-2 bg-white border-t flex gap-1">
                                    <input v-model="renameValue" @keydown.enter="confirmRename(f)" @keydown.esc="renamingFileId=null"
                                           class="flex-1 text-xs border border-gray-300 rounded px-2 py-1 outline-none focus:ring-1 focus:ring-blue-400"
                                           autofocus />
                                    <button @click="confirmRename(f)" class="text-green-600 hover:text-green-800 p-1">
                                        <CheckIconSolid class="w-4 h-4" />
                                    </button>
                                </div>
                                <p v-else class="absolute bottom-0 inset-x-0 px-2 py-1 text-xs text-white bg-black/40 truncate">{{ f.name }}</p>
                            </div>
                        </div>

                        <!-- Liste PDFs / documents -->
                        <div v-if="documentFiles.length" class="space-y-2">
                            <div v-for="f in documentFiles" :key="f.id"
                                 class="flex items-center justify-between gap-3 p-3 rounded-xl border border-gray-200 hover:border-red-200 hover:bg-red-50 transition group">
                                <a :href="f.url" target="_blank" rel="noopener noreferrer"
                                   class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="w-9 h-9 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-red-200 transition">
                                        <DocumentIcon class="w-5 h-5 text-red-600" />
                                    </div>
                                    <div class="min-w-0">
                                        <p v-if="renamingFileId !== f.id" class="text-sm font-medium text-gray-900 truncate">{{ f.name }}</p>
                                        <div v-else class="flex gap-1">
                                            <input v-model="renameValue" @keydown.enter="confirmRename(f)" @keydown.esc="renamingFileId=null"
                                                   class="flex-1 text-sm border border-gray-300 rounded px-2 py-0.5 outline-none focus:ring-1 focus:ring-blue-400" autofocus />
                                            <button @click="confirmRename(f)" class="text-green-600 p-1"><CheckIconSolid class="w-4 h-4" /></button>
                                        </div>
                                        <p class="text-xs text-gray-400">{{ f.formatted_size }}</p>
                                    </div>
                                </a>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <ArrowDownTrayIcon class="w-4 h-4 text-gray-400 group-hover:text-red-600 transition" />
                                    <template v-if="isAdmin">
                                        <button @click.prevent="startRename(f)"
                                                class="text-gray-400 hover:text-blue-600 p-1 rounded transition" title="Renommer">
                                            <PencilIcon class="w-3.5 h-3.5" />
                                        </button>
                                        <button @click.prevent="deleteFile(f.id)"
                                                class="text-gray-400 hover:text-red-600 p-1 rounded transition" title="Supprimer">
                                            <TrashIcon class="w-3.5 h-3.5" />
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ═══════════════════════════════════════════════════ -->

                    <!-- Documents / Notices PDF du modèle -->
                    <div v-if="modelDocuments.length" class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <DocumentIcon class="w-4 h-4" />
                            Documents disponibles
                        </h2>
                        <div class="space-y-2">
                            <a v-for="doc in modelDocuments" :key="doc.id"
                               :href="doc.url" target="_blank" rel="noopener noreferrer"
                               class="flex items-center justify-between gap-3 p-3 rounded-lg border border-gray-200 hover:border-red-300 hover:bg-red-50 transition group">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-red-200 transition">
                                        <DocumentIcon class="w-5 h-5 text-red-600" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ doc.name }}</p>
                                        <p class="text-xs text-gray-400">PDF · {{ doc.formatted_size }}</p>
                                    </div>
                                </div>
                                <ArrowDownTrayIcon class="w-4 h-4 text-gray-400 group-hover:text-red-600 flex-shrink-0 transition" />
                            </a>
                        </div>
                    </div>

                    <!-- ── Accessoires ────────────────────────────────── -->
                    <div v-if="accessories.length > 0 || asset.parent_asset || isAdmin"
                         class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <LinkIcon class="w-4 h-4" />
                                Accessoires inclus
                                <span v-if="accessories.length" class="text-blue-600">({{ accessories.length }})</span>
                            </h2>
                            <button v-if="isAdmin" @click="showLinkModal = true"
                                    class="text-xs text-blue-600 hover:underline">
                                + Lier un accessoire
                            </button>
                        </div>

                        <!-- Note utilisateur : auto-inclus à la réservation -->
                        <div v-if="!isAdmin && accessories.length > 0"
                             class="mb-3 flex items-center gap-2 text-xs text-green-700 bg-green-50 border border-green-200 px-3 py-2 rounded-lg">
                            ✅ Ces accessoires seront automatiquement inclus dans votre réservation.
                        </div>

                        <!-- Équipement parent -->
                        <div v-if="asset.parent_asset" class="mb-3 flex items-center gap-2 text-xs text-indigo-700 bg-indigo-50 px-3 py-2 rounded-lg">
                            🔗 Accessoire de :
                            <Link :href="route('assets.show', asset.parent_asset.id)"
                                  class="font-semibold hover:underline">
                                {{ asset.parent_asset.name }}
                            </Link>
                        </div>

                        <div v-if="accessories.length === 0 && !asset.parent_asset" class="text-center py-4 text-gray-400 text-sm">
                            Aucun accessoire lié
                        </div>

                        <div v-else class="space-y-2">
                            <div v-for="acc in accessories" :key="acc.id"
                                 class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2.5 hover:bg-gray-50">
                                <div class="flex items-center gap-3">
                                    <img v-if="acc.image_url" :src="acc.image_url" class="w-9 h-9 object-cover rounded-lg flex-shrink-0" />
                                    <div v-else class="w-9 h-9 bg-gray-100 rounded-lg flex-shrink-0 flex items-center justify-center text-gray-400 text-lg">📦</div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ acc.name }}</p>
                                        <p class="text-xs text-gray-400 font-mono">{{ acc.asset_tag }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">{{ acc.status_label }}</span>
                                    <button v-if="isAdmin" @click="unlinkAccessory(acc.id)"
                                            class="text-xs text-red-400 hover:text-red-600 transition px-1">✕</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal lier accessoire -->
                    <Teleport to="body">
                        <div v-if="showLinkModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                                <h3 class="font-semibold text-gray-900 mb-4">Lier un accessoire à {{ asset.name }}</h3>
                                <input v-model="linkSearch" type="text" placeholder="Rechercher par nom ou tag…"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3 focus:ring-2 focus:ring-blue-500 outline-none" />
                                <div class="max-h-60 overflow-y-auto space-y-1">
                                    <div v-if="filteredLinkable.length === 0" class="text-center text-gray-400 text-sm py-4">
                                        Aucun équipement disponible
                                    </div>
                                    <button v-for="a in filteredLinkable" :key="a.id"
                                            @click="linkAccessory(a.id)"
                                            :disabled="linkingId === a.id"
                                            class="w-full text-left flex items-center justify-between px-3 py-2 rounded-lg hover:bg-blue-50 transition disabled:opacity-50 text-sm">
                                        <span class="font-medium text-gray-900">{{ a.name }}</span>
                                        <span class="text-xs text-gray-400 font-mono">{{ a.asset_tag }}</span>
                                    </button>
                                </div>
                                <button @click="showLinkModal = false; linkSearch = ''"
                                        class="mt-4 w-full border border-gray-300 text-gray-600 rounded-lg py-2 text-sm hover:bg-gray-50">
                                    Annuler
                                </button>
                            </div>
                        </div>
                    </Teleport>

                    <!-- ── Affectation équipement→équipement (admin) ── -->
                    <div v-if="isAdmin" class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                🔀 Affectation à un équipement
                            </h2>
                        </div>

                        <!-- Cet asset est affecté à un autre -->
                        <div v-if="activeAssignment"
                            class="mb-3 flex items-start gap-3 bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3">
                            <span class="text-xl mt-0.5">📌</span>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-indigo-800">
                                    Affecté à :
                                    <Link :href="route('assets.show', activeAssignment.assigned_to_id)" class="underline">
                                        {{ activeAssignment.assigned_to_name }}
                                    </Link>
                                </p>
                                <p class="text-xs text-indigo-600 mt-0.5">Depuis le {{ activeAssignment.assigned_at }}</p>
                                <p v-if="activeAssignment.notes" class="text-xs text-indigo-500 mt-0.5 italic">{{ activeAssignment.notes }}</p>
                            </div>
                            <button @click="returnAssignment(activeAssignment.id)"
                                :disabled="returningAssignId === activeAssignment.id"
                                class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg transition disabled:opacity-50">
                                Retourner
                            </button>
                        </div>

                        <!-- Bouton affecter (si pas déjà affecté et pas sorti) -->
                        <div v-else-if="asset.status !== 'checked_out'">
                            <button @click="showAssignModal = true"
                                class="flex items-center gap-2 text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 px-3 py-2 rounded-lg transition font-medium">
                                + Affecter à un équipement
                            </button>
                        </div>
                        <p v-else class="text-xs text-gray-400">Impossible d'affecter — équipement actuellement sorti.</p>

                        <!-- Équipements affectés à CET asset (en tant que destinataire) -->
                        <div v-if="assignedAssets.length > 0" class="mt-4">
                            <p class="text-xs font-semibold text-gray-600 mb-2">Équipements affectés à cet équipement :</p>
                            <div class="space-y-2">
                                <div v-for="asgn in assignedAssets" :key="asgn.id"
                                    class="flex items-center justify-between px-3 py-2 rounded-lg border border-indigo-100 bg-indigo-50/40 text-sm">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ asgn.asset_name }}</p>
                                        <p class="text-xs text-gray-400 font-mono">{{ asgn.asset_tag }} · depuis {{ asgn.assigned_at }}</p>
                                        <p v-if="asgn.notes" class="text-xs text-gray-500 italic">{{ asgn.notes }}</p>
                                    </div>
                                    <button @click="returnAssignment(asgn.id)"
                                        :disabled="returningAssignId === asgn.id"
                                        class="text-xs bg-white hover:bg-red-50 text-red-600 border border-red-200 px-2.5 py-1 rounded-lg transition disabled:opacity-50">
                                        Retourner
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal affectation -->
                    <Teleport to="body">
                        <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                                <h3 class="font-semibold text-gray-900 mb-4">
                                    Affecter <span class="text-indigo-700">{{ asset.name }}</span> à un équipement
                                </h3>
                                <input v-model="assignSearch" type="text" placeholder="Rechercher un équipement destinataire…"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3 focus:ring-2 focus:ring-indigo-500 outline-none" />
                                <div class="max-h-52 overflow-y-auto space-y-1 mb-3">
                                    <div v-if="filteredAssignable.length === 0" class="text-center text-gray-400 text-sm py-4">
                                        Aucun équipement disponible
                                    </div>
                                    <button v-for="a in filteredAssignable" :key="a.id"
                                        @click="assignToAsset(a.id)"
                                        :disabled="assigningId === a.id"
                                        class="w-full text-left flex items-center justify-between px-3 py-2 rounded-lg hover:bg-indigo-50 transition disabled:opacity-50 text-sm border border-transparent hover:border-indigo-200">
                                        <span class="font-medium text-gray-900">{{ a.name }}</span>
                                        <span class="text-xs text-gray-400 font-mono">{{ a.asset_tag }}</span>
                                    </button>
                                </div>
                                <textarea v-model="assignNotes" rows="2" placeholder="Notes (optionnel)…"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3 focus:ring-2 focus:ring-indigo-500 outline-none resize-none" />
                                <button @click="showAssignModal = false; assignSearch = ''; assignNotes = ''"
                                    class="w-full border border-gray-300 text-gray-600 rounded-lg py-2 text-sm hover:bg-gray-50">
                                    Annuler
                                </button>
                            </div>
                        </div>
                    </Teleport>

                    <!-- Maintenances (admin) -->
                    <div v-if="isAdmin" class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <WrenchScrewdriverIcon class="w-4 h-4" /> Maintenances
                            </h2>
                            <div class="flex items-center gap-3">
                                <button @click="openMaintenanceModal"
                                        class="inline-flex items-center gap-1 text-xs bg-orange-500 hover:bg-orange-600 text-white px-3 py-1.5 rounded-lg font-medium transition">
                                    + Créer une fiche
                                </button>
                                <Link :href="route('maintenance.index', { asset_id: asset.id })"
                                      class="text-xs text-blue-600 hover:underline">
                                    Tout voir →
                                </Link>
                            </div>
                        </div>
                        <div v-if="maintenances.length === 0" class="text-center py-4 text-gray-400">
                            <p class="text-sm">Aucune fiche de maintenance</p>
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="m in maintenances" :key="m.id"
                                 class="flex items-center justify-between p-3 rounded-lg border border-gray-100 text-sm">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-gray-900 truncate">{{ m.title }}</p>
                                    <p class="text-xs text-gray-400">{{ m.started_at }}<span v-if="m.resolved_at"> → {{ m.resolved_at }}</span></p>
                                </div>
                                <span :class="['text-xs font-medium px-2 py-0.5 rounded-full flex-shrink-0 ml-3',
                                    m.status === 'resolved' ? 'bg-green-100 text-green-700' :
                                    m.status === 'in_progress' ? 'bg-blue-100 text-blue-700' :
                                    'bg-yellow-100 text-yellow-700']">
                                    {{ m.status_label }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- ── Modal Créer fiche maintenance ──────────────── -->
                    <Teleport to="body">
                        <div v-if="showMaintenanceModal"
                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                             @click.self="showMaintenanceModal = false">
                            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
                                <h3 class="font-bold text-gray-900 text-lg mb-1">Nouvelle fiche de maintenance</h3>
                                <p class="text-sm text-gray-500 mb-5">Équipement : <span class="font-medium text-gray-700">{{ asset.name }}</span></p>
                                <form @submit.prevent="submitMaintenance" class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Titre <span class="text-red-500">*</span></label>
                                        <input v-model="maintenanceForm.title" type="text" required
                                               placeholder="Ex : Remplacement écran…"
                                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 outline-none" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                                        <textarea v-model="maintenanceForm.description" rows="3"
                                                  placeholder="Détails du problème…"
                                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 outline-none resize-none" />
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Prestataire</label>
                                            <input v-model="maintenanceForm.provider" type="text"
                                                   placeholder="Nom du technicien…"
                                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 outline-none" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Coût estimé (€)</label>
                                            <input v-model="maintenanceForm.cost" type="number" min="0" step="0.01"
                                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 outline-none" />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Date de début <span class="text-red-500">*</span></label>
                                            <input v-model="maintenanceForm.started_at" type="date" required
                                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 outline-none" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Retour prévu</label>
                                            <input v-model="maintenanceForm.expected_return_at" type="date"
                                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 outline-none" />
                                        </div>
                                    </div>
                                    <div v-if="maintenanceErrors" class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg">
                                        {{ maintenanceErrors }}
                                    </div>
                                    <div class="flex gap-3 pt-2">
                                        <button type="submit" :disabled="maintenanceSubmitting"
                                                class="flex-1 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                                            {{ maintenanceSubmitting ? 'Création…' : 'Créer la fiche' }}
                                        </button>
                                        <button type="button" @click="showMaintenanceModal = false"
                                                class="px-4 py-2.5 border border-gray-300 text-gray-600 rounded-xl text-sm hover:bg-gray-50 transition">
                                            Annuler
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </Teleport>

                    <!-- ══ RAPPORTS UTILISATEURS ════════════════════════════ -->
                    <div id="reports"
                         v-if="localReports.length > 0 || isAdmin"
                         class="bg-white rounded-xl shadow-sm p-6 scroll-mt-4">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <span>📋</span>
                                Rapports utilisateurs
                                <span v-if="localReports.length" class="text-xs font-normal normal-case text-gray-400">({{ localReports.length }})</span>
                            </h2>
                        </div>
                        <div v-if="localReports.length === 0" class="text-center py-6 text-gray-400 text-sm border-2 border-dashed border-gray-200 rounded-xl">
                            Aucun rapport validé pour le moment.
                        </div>
                        <div class="space-y-3">
                            <div v-for="report in localReports" :key="report.id"
                                 class="border rounded-xl overflow-hidden"
                                 :class="report.is_validated ? 'border-gray-200' : 'border-amber-200 bg-amber-50/30'">
                                <!-- En-tête rapport -->
                                <div class="flex items-start gap-3 px-4 py-3"
                                     :class="report.is_validated ? 'bg-gray-50' : 'bg-amber-50'">
                                    <span class="text-lg flex-shrink-0 mt-0.5">{{ reportTypeIcon(report.type) }}</span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-sm font-semibold text-gray-900">{{ report.title }}</p>
                                            <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                                  :class="reportTypeBadgeClass(report.type)">{{ report.type_label }}</span>
                                            <span v-if="!report.is_validated && isAdmin"
                                                  class="text-xs bg-amber-200 text-amber-800 px-2 py-0.5 rounded-full font-medium">
                                                En attente de validation
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            {{ report.user?.name ?? '—' }} · {{ report.created_at }}
                                        </p>
                                    </div>
                                    <!-- Actions admin -->
                                    <div v-if="isAdmin" class="flex items-center gap-1 flex-shrink-0">
                                        <button v-if="!report.is_validated"
                                                @click="validateReport(report)"
                                                class="text-xs bg-green-600 hover:bg-green-700 text-white px-2.5 py-1 rounded-lg font-medium transition">
                                            Valider
                                        </button>
                                        <button @click="openEditReport(report)"
                                                class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                                            <PencilIcon class="w-3.5 h-3.5" />
                                        </button>
                                        <button @click="deleteReport(report.id)"
                                                class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                                            <TrashIcon class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                                <!-- Corps rapport -->
                                <div class="px-4 py-3">
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ report.content }}</p>
                                    <!-- Notes admin -->
                                    <div v-if="report.admin_notes"
                                         class="mt-2 pt-2 border-t border-gray-200 text-xs text-gray-500 italic">
                                        <span class="font-semibold not-italic text-gray-700">Note admin :</span>
                                        {{ report.admin_notes }}
                                    </div>
                                    <p v-if="report.is_validated" class="mt-1.5 text-xs text-gray-400">
                                        ✓ Validé {{ report.validated_at }}
                                        <span v-if="report.validated_by"> par {{ report.validated_by.name }}</span>
                                    </p>
                                </div>
                                <!-- Formulaire édition admin -->
                                <div v-if="editingReportId === report.id" class="px-4 pb-4 pt-2 border-t border-gray-200 bg-gray-50 space-y-2">
                                    <select v-model="reportEditForm.type"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                                        <option value="damage">⚠️ Dégradation</option>
                                        <option value="loss">❌ Perte</option>
                                        <option value="malfunction">🔧 Dysfonctionnement</option>
                                        <option value="observation">📋 Observation</option>
                                    </select>
                                    <input v-model="reportEditForm.title" type="text" placeholder="Titre…"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none" />
                                    <textarea v-model="reportEditForm.content" rows="3" placeholder="Contenu…"
                                              class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none" />
                                    <textarea v-model="reportEditForm.admin_notes" rows="2" placeholder="Notes admin (optionnel)…"
                                              class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none" />
                                    <div class="flex gap-2">
                                        <button @click="saveReportEdit(report)"
                                                class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                                            Enregistrer
                                        </button>
                                        <button @click="editingReportId = null"
                                                class="px-4 py-1.5 border border-gray-300 text-gray-600 text-sm rounded-lg hover:bg-white transition">
                                            Annuler
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Historique des mouvements (admin uniquement) -->
                    <div v-if="isAdmin" class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Historique</h2>
                        <div v-if="movements.length === 0" class="text-center py-6 text-gray-400">
                            <ClockIcon class="w-8 h-8 mx-auto mb-2 opacity-40" />
                            <p class="text-sm">Aucun mouvement enregistré</p>
                        </div>
                        <div v-else class="space-y-3">
                            <div v-for="movement in movements" :key="movement.id"
                                 class="flex items-start gap-3 pb-3 border-b last:border-0">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                     :class="movementClass(movement.action)">
                                    <span class="text-sm">{{ movementIcon(movement.action) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ movementLabel(movement.action) }}
                                        <span v-if="movement.user" class="font-normal text-gray-600">par {{ movement.user.name }}</span>
                                    </p>
                                    <p v-if="movement.notes" class="text-xs text-gray-500 mt-0.5">{{ movement.notes }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ formatDate(movement.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Colonne latérale -->
                <div class="space-y-4">

                    <!-- Photo de l'équipement -->
                    <div v-if="asset.image" class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <img :src="asset.image.startsWith('http') ? asset.image : '/storage/' + asset.image"
                             :alt="asset.name" class="w-full object-cover max-h-64" />
                    </div>

                    <!-- ── Utilisateurs : bouton panier ─────────────────── -->
                    <template v-if="!isAdmin">
                        <div v-if="asset.is_reservable && asset.status === 'available'"
                             class="bg-white rounded-xl shadow-sm p-4 space-y-2">
                            <button @click="cart.hasItem(asset.id) ? cart.removeItem(asset.id) : cart.addItem(asset)"
                                    :class="cart.hasItem(asset.id)
                                        ? 'bg-green-100 text-green-700 border border-green-300 hover:bg-red-50 hover:text-red-600 hover:border-red-300'
                                        : 'bg-blue-600 text-white hover:bg-blue-700'"
                                    class="w-full flex items-center justify-center gap-2 font-semibold py-3 rounded-xl text-sm transition">
                                <ShoppingCartIcon class="w-5 h-5" />
                                {{ cart.hasItem(asset.id) ? '✓ Dans le panier' : 'Ajouter au panier' }}
                            </button>
                            <Link v-if="cart.count > 0" :href="route('cart.index')"
                                  class="block text-center text-xs text-blue-600 hover:underline py-1">
                                Voir mon panier ({{ cart.count }}) →
                            </Link>
                        </div>
                        <div v-else-if="asset.status === 'maintenance'"
                             class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 text-center text-sm text-yellow-800">
                            <p class="font-semibold">🔧 En cours de maintenance</p>
                            <p class="text-xs mt-1 text-yellow-600">Cet équipement n'est pas disponible à la réservation pour le moment.</p>
                        </div>
                        <div v-else-if="asset.is_reservable"
                             class="bg-orange-50 border border-orange-200 rounded-xl p-4 text-center text-sm text-orange-700">
                            <p class="font-semibold">Indisponible actuellement</p>
                            <p class="text-xs mt-1 text-orange-500">{{ asset.status_label }}</p>
                        </div>
                    </template>

                    <!-- Liste d'attente (utilisateurs, équipement non disponible) -->
                    <div v-if="!isAdmin && asset.status !== 'available'" class="bg-white rounded-xl shadow-sm border p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <BellIcon class="w-4 h-4 text-blue-500" />
                            <h3 class="text-sm font-semibold text-gray-700">Me prévenir</h3>
                        </div>
                        <p class="text-xs text-gray-500 mb-3">
                            {{ isOnWaitlist
                                ? 'Vous serez notifié par email dès que cet équipement sera disponible.'
                                : 'Recevez une notification quand cet équipement sera de nouveau disponible.' }}
                        </p>
                        <button v-if="!isOnWaitlist" @click="joinWaitlist"
                                class="w-full text-xs bg-blue-600 text-white py-2 px-3 rounded-lg hover:bg-blue-700 transition font-medium">
                            Me prévenir quand disponible
                        </button>
                        <button v-else @click="leaveWaitlist"
                                class="w-full text-xs border border-gray-300 text-gray-600 py-2 px-3 rounded-lg hover:bg-gray-50 transition">
                            Quitter la liste d'attente
                        </button>
                    </div>

                    <!-- Réservation active (admin : lien vers fiche réservation) -->
                    <div v-if="activeReservation && isAdmin" class="bg-orange-50 border border-orange-200 rounded-xl p-4">
                        <h3 class="text-sm font-semibold text-orange-800 mb-2">📋 Réservation en cours</h3>
                        <p class="text-sm text-orange-700">{{ activeReservation.user?.name }}</p>
                        <p class="text-xs text-orange-600 mt-1">
                            {{ formatDate(activeReservation.start_date) }} → {{ formatDate(activeReservation.end_date) }}
                        </p>
                        <Link :href="route('reservations.show', activeReservation.id)"
                              class="text-xs text-orange-700 underline mt-2 inline-block">
                            Voir la réservation →
                        </Link>
                    </div>

                    <!-- ── Admin : Étiquettes ── -->
                    <!-- ── Admin : Disponibilité à la réservation ── -->
                    <div v-if="isAdmin" class="bg-white rounded-xl shadow-sm border p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">📅 Réservable par les utilisateurs</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ localIsReservable
                                        ? 'Visible et réservable depuis le catalogue'
                                        : 'Masqué du catalogue — non réservable par les utilisateurs' }}
                                </p>
                            </div>
                            <button @click="toggleReservable"
                                :class="localIsReservable
                                    ? 'bg-green-500 hover:bg-green-600'
                                    : 'bg-gray-200 hover:bg-gray-300'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none flex-shrink-0"
                                :title="localIsReservable ? 'Désactiver la réservation' : 'Activer la réservation'">
                                <span :class="localIsReservable ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform" />
                            </button>
                        </div>
                        <div v-if="!localIsReservable"
                             class="mt-3 text-xs bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5 text-amber-700">
                            ⚠ Cet équipement n'apparaît pas dans le catalogue utilisateurs et ne peut pas être réservé.
                        </div>
                    </div>

                    <div v-if="isAdmin" class="bg-white rounded-xl shadow-sm border p-5">
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                            🏷️ Étiquettes
                        </h2>
                        <div class="space-y-3">
                            <!-- À créer -->
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">✏️ Étiquette à créer</p>
                                    <p class="text-xs text-gray-400">L'équipement nécessite une nouvelle étiquette</p>
                                </div>
                                <button @click="toggleLabelCreate"
                                    :class="localLabelToCreate
                                        ? 'bg-indigo-600 hover:bg-indigo-700'
                                        : 'bg-gray-200 hover:bg-gray-300'"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none flex-shrink-0">
                                    <span :class="localLabelToCreate ? 'translate-x-6' : 'translate-x-1'"
                                        class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform" />
                                </button>
                            </div>
                            <!-- À coller -->
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">📌 Étiquette à coller</p>
                                    <p class="text-xs text-gray-400">L'étiquette est prête mais pas encore collée</p>
                                </div>
                                <button @click="toggleLabelApply"
                                    :class="localLabelToApply
                                        ? 'bg-indigo-600 hover:bg-indigo-700'
                                        : 'bg-gray-200 hover:bg-gray-300'"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none flex-shrink-0">
                                    <span :class="localLabelToApply ? 'translate-x-6' : 'translate-x-1'"
                                        class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform" />
                                </button>
                            </div>
                        </div>
                        <div v-if="localLabelToCreate || localLabelToApply"
                             class="mt-3 text-xs bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-1.5 text-indigo-700">
                            <template v-if="localLabelToCreate && localLabelToApply">✏️📌 Étiquette à créer et à coller</template>
                            <template v-else-if="localLabelToCreate">✏️ Étiquette à créer</template>
                            <template v-else>📌 Étiquette à coller</template>
                            — visible sur la
                            <a :href="route('assets.labels')" class="underline font-medium">page étiquettes</a>.
                        </div>
                    </div>

                    <!-- ── Admin : Configuration retour (scratch / belge) ── -->
                    <div v-if="isAdmin" class="bg-white rounded-xl shadow-sm border p-5">
                        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                            ↩ Retour attendu avec
                        </h2>
                        <div class="space-y-3">
                            <!-- Scratch -->
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">🪢 Velcro</p>
                                </div>
                                <button @click="toggleScratch"
                                    :class="localHasScratch
                                        ? 'bg-indigo-600 hover:bg-indigo-700'
                                        : 'bg-gray-200 hover:bg-gray-300'"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none flex-shrink-0">
                                    <span :class="localHasScratch ? 'translate-x-6' : 'translate-x-1'"
                                        class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform" />
                                </button>
                            </div>
                            <!-- Belge -->
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">👜 Belge</p>
                                </div>
                                <button @click="toggleBelge"
                                    :class="localHasBelge
                                        ? 'bg-indigo-600 hover:bg-indigo-700'
                                        : 'bg-gray-200 hover:bg-gray-300'"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none flex-shrink-0">
                                    <span :class="localHasBelge ? 'translate-x-6' : 'translate-x-1'"
                                        class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform" />
                                </button>
                            </div>
                        </div>
                        <p v-if="localHasScratch || localHasBelge"
                           class="mt-3 text-xs text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-1.5">
                            Affiché sur la page retours pour rappel à l'admin.
                        </p>

                        <!-- Note de vérification retour -->
                        <div class="mt-4 pt-4 border-t">
                            <p class="text-sm font-medium text-gray-800 mb-1">📋 Vérification à faire pour le retour</p>
                            <p class="text-xs text-gray-400 mb-2">
                                Message affiché en pop-up lors du scan retour et sous l'équipement sur la page retours.
                            </p>
                            <textarea
                                v-model="localReturnCheckNote"
                                rows="3"
                                placeholder="Ex : Vérifier la présence du câble d'alimentation et de la télécommande…"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 outline-none resize-none"
                                @blur="saveReturnCheckNote"
                            />
                            <div class="flex items-center justify-between mt-1.5">
                                <p class="text-xs text-gray-400">
                                    <span v-if="returnCheckNoteSaved" class="text-green-600 font-medium">✓ Enregistré</span>
                                    <span v-else-if="localReturnCheckNote">Cliquez hors du champ pour enregistrer</span>
                                    <span v-else-if="asset.return_check_note">Effacez le texte puis cliquez hors du champ pour supprimer</span>
                                </p>
                                <button v-if="localReturnCheckNote"
                                    @click="localReturnCheckNote = ''; saveReturnCheckNote()"
                                    class="text-xs text-red-500 hover:text-red-700 underline">
                                    Supprimer
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ── Admin : QR Code ──────────────────────────────── -->
                    <template v-if="isAdmin">
                        <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">QR Code</h2>
                            <div v-if="asset.qr_code_url" class="flex justify-center">
                                <img :src="asset.qr_code_url" alt="QR Code" class="w-40 h-40" @error="qrError = true" />
                            </div>
                            <div v-else class="w-40 h-40 mx-auto bg-gray-100 rounded-lg flex items-center justify-center">
                                <QrCodeIcon class="w-12 h-12 text-gray-300" />
                            </div>
                            <p class="text-xs text-gray-400 mt-3">{{ asset.asset_tag }}</p>
                            <button @click="printQr" class="mt-3 text-xs text-blue-600 hover:text-blue-800 underline">
                                Imprimer le QR code
                            </button>
                        </div>

                        <!-- Code-barres Code 128 -->
                        <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Code-barres</h2>
                            <div class="flex justify-center overflow-x-auto">
                                <svg ref="barcodeSvg" class="max-w-full" />
                            </div>
                            <button @click="downloadBarcode" class="mt-3 text-xs text-blue-600 hover:text-blue-800 underline">
                                Télécharger le code-barres
                            </button>
                        </div>
                    </template>

                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { ArrowLeftIcon, ClockIcon, QrCodeIcon, DocumentIcon, ArrowDownTrayIcon, BellIcon, ShoppingCartIcon, LinkIcon, PaperClipIcon, WrenchScrewdriverIcon, PencilIcon, PlusIcon, TrashIcon, PhotoIcon, XMarkIcon, CheckIcon as CheckIconSolid, PuzzlePieceIcon } from '@heroicons/vue/24/outline'
import { ref, onMounted, computed } from 'vue'
import JsBarcode from 'jsbarcode'
import { useCartStore } from '@/stores/cart'

const page = usePage()

const props = defineProps({
    asset:             Object,
    movements:         { type: Array,   default: () => [] },
    modelDocuments:    { type: Array,   default: () => [] },
    maintenances:      { type: Array,   default: () => [] },
    activeReservation: { type: Object,  default: null },
    isOnWaitlist:      { type: Boolean, default: false },
    accessories:       { type: Array,   default: () => [] },
    linkableAssets:    { type: Array,   default: () => [] },
    assetFiles:        { type: Array,   default: () => [] },
    activeAssignment:  { type: Object,  default: null },
    assignedAssets:    { type: Array,   default: () => [] },
    assignableAssets:  { type: Array,   default: () => [] },
    components:        { type: Array,   default: () => [] },
    reports:           { type: Array,   default: () => [] },
})

const isAdmin = computed(() => page.props.auth?.user?.is_admin)
const cart    = useCartStore()

// ── État local is_reservable (mise à jour optimiste) ─────────────────────────
const localIsReservable = ref(props.asset.is_reservable ?? true)

async function toggleReservable() {
    localIsReservable.value = !localIsReservable.value
    try {
        await axios.patch(route('assets.toggle-reservable', props.asset.id))
    } catch {
        localIsReservable.value = !localIsReservable.value // rollback
    }
}

// ── État local scratch / belge (mise à jour optimiste) ───────────────────────
const localHasScratch = ref(props.asset.has_scratch ?? false)
const localHasBelge   = ref(props.asset.has_belge ?? false)

async function toggleScratch() {
    localHasScratch.value = !localHasScratch.value
    try {
        await axios.patch(route('assets.toggle-scratch', props.asset.id))
    } catch {
        localHasScratch.value = !localHasScratch.value // rollback
    }
}

async function toggleBelge() {
    localHasBelge.value = !localHasBelge.value
    try {
        await axios.patch(route('assets.toggle-belge', props.asset.id))
    } catch {
        localHasBelge.value = !localHasBelge.value // rollback
    }
}

// ── Étiquettes (label_to_create / label_to_apply) ─────────────────────────────
const localLabelToCreate = ref(props.asset.label_to_create ?? false)
const localLabelToApply  = ref(props.asset.label_to_apply  ?? false)

async function toggleLabelCreate() {
    localLabelToCreate.value = !localLabelToCreate.value
    try {
        await axios.patch(`/assets/${props.asset.id}/toggle-label-create`)
    } catch (e) {
        localLabelToCreate.value = !localLabelToCreate.value // rollback
        console.error('toggleLabelCreate error:', e?.response?.status, e?.message)
    }
}

async function toggleLabelApply() {
    localLabelToApply.value = !localLabelToApply.value
    try {
        await axios.patch(`/assets/${props.asset.id}/toggle-label-apply`)
    } catch (e) {
        localLabelToApply.value = !localLabelToApply.value // rollback
        console.error('toggleLabelApply error:', e?.response?.status, e?.message)
    }
}

// ── Note de vérification retour ───────────────────────────────────────────────
const localReturnCheckNote  = ref(props.asset.return_check_note ?? '')
const returnCheckNoteSaved  = ref(false)
let returnCheckNoteTimer    = null

async function saveReturnCheckNote() {
    try {
        await axios.patch(route('assets.return-check-note', props.asset.id), {
            return_check_note: localReturnCheckNote.value || null,
        })
        returnCheckNoteSaved.value = true
        clearTimeout(returnCheckNoteTimer)
        returnCheckNoteTimer = setTimeout(() => { returnCheckNoteSaved.value = false }, 2500)
    } catch {}
}

// ── Note d'état de l'équipement (condition_note) ─────────────────────────────
const localConditionNote    = ref(props.asset.condition_note ?? '')
const editingConditionNote  = ref(false)
const savingConditionNote   = ref(false)
let   conditionNoteOriginal = props.asset.condition_note ?? ''

function cancelConditionNote() {
    localConditionNote.value  = conditionNoteOriginal
    editingConditionNote.value = false
}

async function saveConditionNote() {
    savingConditionNote.value = true
    try {
        await axios.patch(route('assets.condition-note', props.asset.id), {
            condition_note: localConditionNote.value || null,
        })
        conditionNoteOriginal      = localConditionNote.value
        editingConditionNote.value = false
    } catch {
        alert('Erreur lors de la sauvegarde.')
    } finally {
        savingConditionNote.value = false
    }
}

// ── Condition des composants ──────────────────────────────────────────────────
const CONDITION_CYCLE = [null, 'good', 'damaged', 'defective', 'missing']

function componentConditionLabel(condition) {
    const labels = { good: '✅ Bon état', damaged: '⚠️ Endommagé', missing: '❌ Manquant', defective: '🔧 Défectueux' }
    return labels[condition] ?? condition
}

function componentConditionClass(condition) {
    const classes = {
        good:      'bg-green-100 text-green-700',
        damaged:   'bg-orange-100 text-orange-700',
        missing:   'bg-red-100 text-red-700',
        defective: 'bg-yellow-100 text-yellow-700',
    }
    return classes[condition] ?? 'bg-gray-100 text-gray-600'
}

async function cycleComponentCondition(comp) {
    const compIdx = localComponents.value.findIndex(c => c.id === comp.id)
    if (compIdx === -1) return

    const idx  = CONDITION_CYCLE.indexOf(localComponents.value[compIdx].condition ?? null)
    const next = CONDITION_CYCLE[(idx + 1) % CONDITION_CYCLE.length]
    const prev = localComponents.value[compIdx].condition

    // Mise à jour optimiste via splice (réactivité garantie)
    localComponents.value.splice(compIdx, 1, { ...localComponents.value[compIdx], condition: next })

    try {
        await axios.patch(route('assets.components.condition', [props.asset.id, comp.id]), {
            condition: next,
        })
    } catch (e) {
        // Retour arrière en cas d'erreur
        localComponents.value.splice(compIdx, 1, { ...localComponents.value[compIdx], condition: prev })
        console.error('Erreur condition composant:', e.response?.status, e.response?.data ?? e.message)
        alert('Erreur lors de la mise à jour de l\'état.')
    }
}

// ── Rapports utilisateurs ─────────────────────────────────────────────────────
const localReports    = ref([...props.reports])
const editingReportId = ref(null)
const reportEditForm  = ref({ type: '', title: '', content: '', admin_notes: '' })

function reportTypeIcon(type) {
    const icons = { damage: '⚠️', loss: '❌', malfunction: '🔧', observation: '📋' }
    return icons[type] ?? '📋'
}

function reportTypeBadgeClass(type) {
    const classes = {
        damage:      'bg-orange-100 text-orange-700',
        loss:        'bg-red-100 text-red-700',
        malfunction: 'bg-yellow-100 text-yellow-700',
        observation: 'bg-blue-100 text-blue-700',
    }
    return classes[type] ?? 'bg-gray-100 text-gray-600'
}

async function validateReport(report) {
    try {
        const { data } = await axios.patch(route('asset-reports.validate', report.id))
        const idx = localReports.value.findIndex(r => r.id === report.id)
        if (idx !== -1) localReports.value.splice(idx, 1, data.report)
    } catch {
        alert('Erreur lors de la validation.')
    }
}

function openEditReport(report) {
    editingReportId.value = report.id
    reportEditForm.value  = {
        type:        report.type,
        title:       report.title,
        content:     report.content,
        admin_notes: report.admin_notes ?? '',
    }
}

async function saveReportEdit(report) {
    try {
        const { data } = await axios.put(route('asset-reports.update', report.id), reportEditForm.value)
        const idx = localReports.value.findIndex(r => r.id === report.id)
        if (idx !== -1) localReports.value.splice(idx, 1, data.report)
        editingReportId.value = null
    } catch {
        alert('Erreur lors de l\'enregistrement.')
    }
}

async function deleteReport(reportId) {
    if (!confirm('Supprimer ce rapport définitivement ?')) return
    try {
        await axios.delete(route('asset-reports.destroy', reportId))
        localReports.value = localReports.value.filter(r => r.id !== reportId)
    } catch {
        alert('Erreur lors de la suppression.')
    }
}

function goBack() {
    if (window.history.length > 1) {
        window.history.back()
    } else {
        router.visit(route('assets.index'))
    }
}

function confirmDelete() {
    if (confirm(`Supprimer définitivement « ${props.asset.name} » ?\nCette action est irréversible.`)) {
        router.delete(route('assets.destroy', props.asset.id))
    }
}

function joinWaitlist() {
    router.post(route('waitlist.store', props.asset.id), {}, { preserveScroll: true })
}

function leaveWaitlist() {
    router.delete(route('waitlist.destroy', props.asset.id), {}, { preserveScroll: true })
}

// ── Accessoires ──────────────────────────────────────────────────────────────
const showLinkModal   = ref(false)
const linkSearch      = ref('')
const linkingId       = ref(null)

const filteredLinkable = computed(() => {
    const q = linkSearch.value.trim().toLowerCase()
    if (!q) return props.linkableAssets.slice(0, 10)
    return props.linkableAssets
        .filter(a => a.name.toLowerCase().includes(q) || a.asset_tag.toLowerCase().includes(q))
        .slice(0, 10)
})

function linkAccessory(accessoryId) {
    linkingId.value = accessoryId
    router.post(route('assets.link-accessory', props.asset.id), { accessory_id: accessoryId }, {
        preserveScroll: true,
        onSuccess: () => { showLinkModal.value = false; linkSearch.value = '' },
        onFinish: () => { linkingId.value = null },
    })
}

function unlinkAccessory(accessoryId) {
    router.delete(route('assets.unlink-accessory', props.asset.id), {
        data: { accessory_id: accessoryId },
        preserveScroll: true,
    })
}

// ── Affectation équipement → équipement ──────────────────────────────────────
const showAssignModal    = ref(false)
const assignSearch       = ref('')
const assignNotes        = ref('')
const assigningId        = ref(null)
const returningAssignId  = ref(null)

const filteredAssignable = computed(() => {
    const q = assignSearch.value.trim().toLowerCase()
    if (!q) return props.assignableAssets.slice(0, 10)
    return props.assignableAssets
        .filter(a => a.name.toLowerCase().includes(q) || a.asset_tag.toLowerCase().includes(q))
        .slice(0, 10)
})

function assignToAsset(targetId) {
    assigningId.value = targetId
    router.post(route('asset-assignments.assign'), {
        asset_id: props.asset.id,
        assigned_to_asset_id: targetId,
        notes: assignNotes.value || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { showAssignModal.value = false; assignSearch.value = ''; assignNotes.value = '' },
        onFinish: () => { assigningId.value = null },
    })
}

function returnAssignment(assignmentId) {
    if (!confirm('Confirmer le retour de cet équipement ?')) return
    returningAssignId.value = assignmentId
    router.post(route('asset-assignments.return', assignmentId), {}, {
        preserveScroll: true,
        onFinish: () => { returningAssignId.value = null },
    })
}

// ── Composants de l'équipement ───────────────────────────────────────────────
const localComponents     = ref([...props.components])
const showComponentForm   = ref(false)
const editingComponent    = ref(null)   // null = création, objet = édition
const savingComponent     = ref(false)
const componentForm       = ref({ name: '', description: '', quantity: 1 })
const componentFeedback   = ref(null)   // { message, type } affiché après ajout
let   componentFeedbackTimer = null

// Indique si cet asset appartient à un modèle (pour l'UI de propagation)
const hasModel = computed(() => !!props.asset.equipment_model_id)

// Retire le suffixe " #N" d'un nom de composant
function stripNumberSuffix(name) {
    return name.replace(/ #\d+\s*$/, '').trim()
}

function setComponentFeedback(message, type = 'success') {
    if (componentFeedbackTimer) clearTimeout(componentFeedbackTimer)
    componentFeedback.value = { message, type }
    componentFeedbackTimer = setTimeout(() => { componentFeedback.value = null }, 5000)
}

function openAddComponent() {
    editingComponent.value  = null
    componentForm.value     = { name: '', description: '', quantity: 1 }
    showComponentForm.value = true
}

function openEditComponent(comp) {
    editingComponent.value  = comp
    // Pour l'édition on montre le nom de base (sans #N) pour faciliter la saisie
    componentForm.value     = {
        name:        hasModel.value ? stripNumberSuffix(comp.name) : comp.name,
        description: comp.description ?? '',
        quantity:    comp.quantity,
    }
    showComponentForm.value = true
}

function cancelComponentForm() {
    showComponentForm.value = false
    editingComponent.value  = null
}

async function submitComponent() {
    if (!componentForm.value.name.trim()) return
    savingComponent.value = true
    try {
        if (editingComponent.value) {
            // Mise à jour uniquement sur cet équipement
            const { data } = await axios.put(
                route('assets.components.update', [props.asset.id, editingComponent.value.id]),
                componentForm.value
            )
            const idx = localComponents.value.findIndex(c => c.id === data.id)
            if (idx !== -1) localComponents.value.splice(idx, 1, data)
            setComponentFeedback('Composant modifié.')
        } else {
            // Création avec propagation automatique
            const { data } = await axios.post(
                route('assets.components.store', props.asset.id),
                componentForm.value
            )
            // Pousser un objet propre avec tous les champs attendus
            localComponents.value.push({
                id:          data.id,
                name:        data.name,
                description: data.description ?? null,
                quantity:    data.quantity ?? 1,
                sort_order:  data.sort_order ?? 0,
                condition:   data.condition ?? null,
            })
            if (data.propagated_to > 0) {
                setComponentFeedback(
                    `✓ « ${data.base_name} » ajouté et propagé automatiquement à ${data.propagated_to} autre${data.propagated_to > 1 ? 's' : ''} équipement${data.propagated_to > 1 ? 's' : ''} du même modèle.`,
                    'propagated'
                )
            } else {
                setComponentFeedback(`✓ Composant « ${data.name} » ajouté.`)
            }
        }
        showComponentForm.value = false
        editingComponent.value  = null
    } catch (e) {
        alert(e.response?.data?.message ?? 'Erreur lors de l\'enregistrement.')
    } finally {
        savingComponent.value = false
    }
}

// Modal de confirmation suppression (propagation ou non)
const showDeleteCompModal = ref(false)
const deleteCompTarget    = ref(null)

function deleteComponent(comp) {
    if (hasModel.value) {
        // Proposer deux options : supprimer ici seulement ou sur tout le modèle
        deleteCompTarget.value    = comp
        showDeleteCompModal.value = true
    } else {
        doDeleteComponent(comp, false)
    }
}

async function doDeleteComponent(comp, propagate) {
    showDeleteCompModal.value = false
    try {
        await axios.delete(route('assets.components.destroy', [props.asset.id, comp.id]), {
            data: { propagate },
        })
        localComponents.value = localComponents.value.filter(c => c.id !== comp.id)
        if (propagate) {
            setComponentFeedback(`✓ Composant « ${stripNumberSuffix(comp.name)} » supprimé sur tous les équipements du modèle.`, 'warning')
        }
    } catch (e) {
        alert('Erreur lors de la suppression.')
    }
}

// ── Fiche publique (admin) ────────────────────────────────────────────────────
const editingPublicInfo = ref(false)
const savingPublicInfo  = ref(false)
const publicForm = ref({
    public_description: props.asset.public_description ?? '',
    custom_fields: props.asset.custom_fields?.length
        ? props.asset.custom_fields.map(f => ({ key: f.key, value: f.value }))
        : [],
})

function openPublicEdit() {
    publicForm.value = {
        public_description: props.asset.public_description ?? '',
        custom_fields: props.asset.custom_fields?.length
            ? props.asset.custom_fields.map(f => ({ ...f }))
            : [],
    }
    editingPublicInfo.value = true
}

function addCustomField() {
    publicForm.value.custom_fields.push({ key: '', value: '' })
}

function removeCustomField(i) {
    publicForm.value.custom_fields.splice(i, 1)
}

function savePublicInfo() {
    savingPublicInfo.value = true
    router.patch(route('assets.update-public-info', props.asset.id), {
        public_description: publicForm.value.public_description || null,
        custom_fields: publicForm.value.custom_fields.filter(f => f.key.trim() && f.value.trim()),
    }, {
        preserveScroll: true,
        onSuccess: () => { editingPublicInfo.value = false },
        onFinish:  () => { savingPublicInfo.value = false },
    })
}

// Fichiers attachés
const fileUploadInput = ref(null)
const uploadingFile   = ref(false)
const renamingFileId  = ref(null)
const renameValue     = ref('')

const imageFiles    = computed(() => props.assetFiles.filter(f => f.is_image))
const documentFiles = computed(() => props.assetFiles.filter(f => !f.is_image))

function triggerUpload() { fileUploadInput.value?.click() }

function handleFileUpload(e) {
    const file = e.target.files?.[0]
    if (!file) return
    uploadingFile.value = true
    router.post(route('assets.files.store', props.asset.id),
        { file, name: file.name },
        {
            forceFormData:  true,
            preserveScroll: true,
            onFinish: () => { uploadingFile.value = false; e.target.value = '' },
        }
    )
}

function deleteFile(fileId) {
    if (!confirm('Supprimer ce fichier ?')) return
    router.delete(route('assets.files.destroy', { asset: props.asset.id, file: fileId }), {
        preserveScroll: true,
    })
}

function startRename(f) {
    renamingFileId.value = f.id
    renameValue.value    = f.name
}

function confirmRename(f) {
    if (!renameValue.value.trim()) return
    router.patch(route('assets.files.update', { asset: props.asset.id, file: f.id }), {
        name: renameValue.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => { renamingFileId.value = null },
    })
}
// ─────────────────────────────────────────────────────────────────────────────

// ── Création maintenance inline ───────────────────────────────────────────────
const showMaintenanceModal = ref(false)
const maintenanceSubmitting = ref(false)
const maintenanceErrors     = ref(null)
const maintenanceForm = ref({
    title: '',
    description: '',
    provider: '',
    cost: '',
    started_at: new Date().toISOString().slice(0, 10),
    expected_return_at: '',
})

function openMaintenanceModal() {
    maintenanceForm.value = {
        title: '',
        description: '',
        provider: '',
        cost: '',
        started_at: new Date().toISOString().slice(0, 10),
        expected_return_at: '',
    }
    maintenanceErrors.value = null
    showMaintenanceModal.value = true
}

function submitMaintenance() {
    maintenanceSubmitting.value = true
    maintenanceErrors.value = null
    router.post(route('maintenance.store'), {
        asset_id:            props.asset.id,
        title:               maintenanceForm.value.title,
        description:         maintenanceForm.value.description || null,
        provider:            maintenanceForm.value.provider    || null,
        cost:                maintenanceForm.value.cost        || null,
        started_at:          maintenanceForm.value.started_at,
        expected_return_at:  maintenanceForm.value.expected_return_at || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { showMaintenanceModal.value = false },
        onError: (errors) => {
            maintenanceErrors.value = Object.values(errors).flat().join(' ')
        },
        onFinish: () => { maintenanceSubmitting.value = false },
    })
}
// ─────────────────────────────────────────────────────────────────────────────

const qrError = ref(false)
const barcodeSvg = ref(null)

onMounted(() => {
    if (barcodeSvg.value && props.asset?.asset_tag) {
        try {
            JsBarcode(barcodeSvg.value, props.asset.asset_tag, {
                format: 'CODE128',
                lineColor: '#1e293b',
                width: 2,
                height: 60,
                displayValue: true,
                fontSize: 13,
                margin: 10,
                background: '#ffffff',
            })
        } catch (e) { /* tag incompatible avec CODE128 */ }
    }
})

function downloadBarcode() {
    if (!barcodeSvg.value) return
    const svgData = new XMLSerializer().serializeToString(barcodeSvg.value)
    const blob = new Blob([svgData], { type: 'image/svg+xml' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `barcode-${props.asset.asset_tag}.svg`
    a.click()
    URL.revokeObjectURL(url)
}

function formatDate(date) {
    if (!date) return '—'
    return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

function movementIcon(action) {
    const icons = { checkout: '📤', return: '📥', maintenance: '🔧', import: '📋', status_change: '🔄' }
    return icons[action] ?? '•'
}

function movementLabel(action) {
    const labels = { checkout: 'Sortie', return: 'Retour', maintenance: 'Maintenance', import: 'Import', status_change: 'Changement de statut' }
    return labels[action] ?? action
}

function movementClass(action) {
    const classes = {
        checkout: 'bg-orange-100',
        return: 'bg-green-100',
        maintenance: 'bg-yellow-100',
        import: 'bg-blue-100',
        status_change: 'bg-gray-100',
    }
    return classes[action] ?? 'bg-gray-100'
}

function printQr() {
    window.print()
}
</script>

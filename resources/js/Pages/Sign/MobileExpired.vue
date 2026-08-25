<script setup>
// Page standalone — affichée quand la session de signature est expirée ou invalide
</script>

<template>
  <div class="min-h-screen bg-gray-900 text-white flex flex-col items-center justify-center px-6">
    <div class="w-full max-w-sm text-center">
      <div class="text-6xl mb-6">⏰</div>
      <h1 class="text-xl font-bold mb-2">Session expirée</h1>
      <p class="text-gray-400 text-sm mb-8 leading-relaxed">
        Ce lien de signature n'est plus valide.<br>
        Les sessions durent <strong class="text-white">15 minutes</strong> pour des raisons de sécurité.
      </p>
      <div class="bg-gray-800 rounded-xl p-5 text-left text-sm space-y-3 mb-8">
        <p class="font-semibold text-gray-200">Pour continuer :</p>
        <ol class="list-decimal list-inside space-y-2 text-gray-400 text-sm">
          <li>Retournez sur le poste de travail</li>
          <li>Cliquez à nouveau sur <span class="text-indigo-400 font-medium">Signer depuis mobile</span></li>
          <li>Scannez le nouveau QR code affiché</li>
        </ol>
      </div>
      <p class="text-xs text-gray-600">Signature responsable · Session terminée</p>
    </div>
  </div>
</template>

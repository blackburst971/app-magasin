/**
 * useSound — Synthèse sonore via Web Audio API
 * Lit les préférences depuis les shared props Inertia (settings.sounds.*)
 */
import { usePage } from '@inertiajs/vue3'

// Sons disponibles (synthèse Web Audio, pas de fichiers)
const SOUNDS = {
  chime: (ctx, vol) => {
    // Ding montant doux (440 → 880 Hz)
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain); gain.connect(ctx.destination)
    osc.type = 'sine'
    osc.frequency.setValueAtTime(440, ctx.currentTime)
    osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.3)
    gain.gain.setValueAtTime(vol, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6)
    osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.6)
  },

  ding: (ctx, vol) => {
    // Note unique nette (660 Hz)
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain); gain.connect(ctx.destination)
    osc.type = 'sine'
    osc.frequency.setValueAtTime(660, ctx.currentTime)
    gain.gain.setValueAtTime(vol, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5)
    osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.5)
  },

  beep: (ctx, vol) => {
    // Bip court (1000 Hz)
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain); gain.connect(ctx.destination)
    osc.type = 'square'
    osc.frequency.setValueAtTime(1000, ctx.currentTime)
    gain.gain.setValueAtTime(vol * 0.3, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15)
    osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.15)
  },

  buzz: (ctx, vol) => {
    // Buzz d'erreur (200 Hz, descente)
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain); gain.connect(ctx.destination)
    osc.type = 'sawtooth'
    osc.frequency.setValueAtTime(300, ctx.currentTime)
    osc.frequency.exponentialRampToValueAtTime(100, ctx.currentTime + 0.4)
    gain.gain.setValueAtTime(vol * 0.4, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4)
    osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.4)
  },

  success: (ctx, vol) => {
    // Mélodie montante do-mi-sol
    const notes = [261.63, 329.63, 392.00]
    notes.forEach((freq, i) => {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.connect(gain); gain.connect(ctx.destination)
      osc.type = 'sine'
      osc.frequency.setValueAtTime(freq, ctx.currentTime)
      const t = ctx.currentTime + i * 0.12
      gain.gain.setValueAtTime(0, t)
      gain.gain.linearRampToValueAtTime(vol, t + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.001, t + 0.25)
      osc.start(t); osc.stop(t + 0.25)
    })
  },

  error: (ctx, vol) => {
    // Mélodie descendante sol-mi-do
    const notes = [392.00, 329.63, 261.63]
    notes.forEach((freq, i) => {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.connect(gain); gain.connect(ctx.destination)
      osc.type = 'triangle'
      osc.frequency.setValueAtTime(freq, ctx.currentTime)
      const t = ctx.currentTime + i * 0.12
      gain.gain.setValueAtTime(0, t)
      gain.gain.linearRampToValueAtTime(vol * 0.5, t + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.001, t + 0.25)
      osc.start(t); osc.stop(t + 0.25)
    })
  },

  none: () => {}, // silencieux
}

let audioCtx = null

function getAudioContext() {
  if (!audioCtx || audioCtx.state === 'closed') {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)()
  }
  return audioCtx
}

export function useSound() {
  const page = usePage()

  function getSoundSettings() {
    const s = page.props.soundSettings ?? {}
    return {
      enabled:              s.enabled              !== 'false',
      volume:               parseFloat(s.volume    ?? '80') / 100,
      notification_general: s.notification_general ?? 'chime',
      notification_kit:     s.notification_kit     ?? 'ding',
      scan_success:         s.scan_success         ?? 'beep',
      scan_error:           s.scan_error           ?? 'buzz',
    }
  }

  /**
   * @param {'notification_general'|'notification_kit'|'scan_success'|'scan_error'} type
   */
  function playSound(type) {
    const settings = getSoundSettings()
    if (!settings.enabled) return

    const soundName = settings[type] ?? 'none'
    const fn = SOUNDS[soundName] ?? SOUNDS.none

    try {
      const ctx = getAudioContext()
      // Reprendre le contexte si suspendu (politique navigateur)
      if (ctx.state === 'suspended') {
        ctx.resume().then(() => fn(ctx, settings.volume))
      } else {
        fn(ctx, settings.volume)
      }
    } catch (e) {
      // Ignore si AudioContext non disponible
    }
  }

  /**
   * Jouer un son par son nom (pour le bouton "Tester" dans les settings)
   */
  function playSoundByName(name, volume = 0.8) {
    const fn = SOUNDS[name] ?? SOUNDS.none
    try {
      const ctx = getAudioContext()
      if (ctx.state === 'suspended') {
        ctx.resume().then(() => fn(ctx, volume))
      } else {
        fn(ctx, volume)
      }
    } catch (e) {}
  }

  return { playSound, playSoundByName, SOUND_NAMES: Object.keys(SOUNDS) }
}

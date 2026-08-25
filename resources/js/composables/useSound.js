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

  fanfare: (ctx, vol) => {
    // ── Phase 1 : montée rapide do-mi-sol (3 × 0.08 s) ──────────────────────
    const run = [261.63, 329.63, 392.00] // C4, E4, G4
    run.forEach((freq, i) => {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.connect(gain); gain.connect(ctx.destination)
      osc.type = 'sine'
      osc.frequency.setValueAtTime(freq, ctx.currentTime)
      const t = ctx.currentTime + i * 0.09
      gain.gain.setValueAtTime(0, t)
      gain.gain.linearRampToValueAtTime(vol * 0.55, t + 0.015)
      gain.gain.exponentialRampToValueAtTime(0.001, t + 0.13)
      osc.start(t); osc.stop(t + 0.15)
    })

    // ── Phase 2 : accord plein C5+E5+G5 tenu (0.9 s) ───────────────────────
    const chord = [523.25, 659.25, 783.99] // C5, E5, G5
    chord.forEach(freq => {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.connect(gain); gain.connect(ctx.destination)
      osc.type = 'triangle'
      osc.frequency.setValueAtTime(freq, ctx.currentTime)
      const t = ctx.currentTime + 0.29
      gain.gain.setValueAtTime(0, t)
      gain.gain.linearRampToValueAtTime(vol * 0.45, t + 0.03)
      gain.gain.setValueAtTime(vol * 0.45, t + 0.35)
      gain.gain.exponentialRampToValueAtTime(0.001, t + 1.0)
      osc.start(t); osc.stop(t + 1.1)
    })

    // ── Phase 3 : note C6 finale (« point d'exclamation ») ──────────────────
    const osc3 = ctx.createOscillator()
    const gain3 = ctx.createGain()
    osc3.connect(gain3); gain3.connect(ctx.destination)
    osc3.type = 'sine'
    osc3.frequency.setValueAtTime(1046.50, ctx.currentTime) // C6
    const t3 = ctx.currentTime + 0.31
    gain3.gain.setValueAtTime(0, t3)
    gain3.gain.linearRampToValueAtTime(vol * 0.3, t3 + 0.02)
    gain3.gain.exponentialRampToValueAtTime(0.001, t3 + 0.7)
    osc3.start(t3); osc3.stop(t3 + 0.8)
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

  // Sonnette double — signal d'attention pour demande responsable
  supervisor_request: (ctx, vol) => {
    // Deux dings descendants (sol5 → mi5), espacés de 0.25s
    const pairs = [[784.00, 659.25], [784.00, 659.25]]
    pairs.forEach(([f1, f2], pair) => {
      const t0 = ctx.currentTime + pair * 0.55
      // Note 1
      ;[f1, f2].forEach((freq, i) => {
        const osc = ctx.createOscillator()
        const gain = ctx.createGain()
        osc.connect(gain); gain.connect(ctx.destination)
        osc.type = 'sine'
        osc.frequency.setValueAtTime(freq, t0 + i * 0.22)
        const t = t0 + i * 0.22
        gain.gain.setValueAtTime(0, t)
        gain.gain.linearRampToValueAtTime(vol * 0.65, t + 0.02)
        gain.gain.exponentialRampToValueAtTime(0.001, t + 0.35)
        osc.start(t); osc.stop(t + 0.4)
      })
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
      enabled:               s.enabled               !== 'false',
      volume:                parseFloat(s.volume     ?? '80') / 100,
      notification_general:  s.notification_general  ?? 'chime',
      notification_kit:      s.notification_kit      ?? 'ding',
      scan_success:          s.scan_success          ?? 'beep',
      scan_error:            s.scan_error            ?? 'buzz',
      reservation_complete:  s.reservation_complete  ?? 'fanfare',
      supervisor_request:    s.supervisor_request    ?? 'supervisor_request',
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

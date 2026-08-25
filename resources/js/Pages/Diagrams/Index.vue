<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import axios from 'axios'

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
  diagrams: { type: Array, default: () => [] },
  events:   { type: Array, default: () => [] },
})

// ─── Diagram list ─────────────────────────────────────────────────────────────
const localDiagrams   = ref([...props.diagrams])
const currentDiagram  = ref(null)
const diagTitle       = ref('')
const diagDesc        = ref('')
const showNewDiagram  = ref(false)
const newName         = ref('')
const newDesc         = ref('')
const saving          = ref(false)
const showEventModal  = ref(false)
const selectedEventId = ref(null)

// ─── Canvas objects ───────────────────────────────────────────────────────────
const svgEl  = ref(null)
const shapes = ref([])
const conns  = ref([])   // {id, from, fromAnchor, fromConnId, fromWpId, to, toAnchor, waypoints, label, arrow, straight, style}
const groups = ref([])

// ─── Viewport ─────────────────────────────────────────────────────────────────
const zoom = ref(1)
const panX = ref(0)
const panY = ref(0)

// ─── Interaction ──────────────────────────────────────────────────────────────
const MODE = { IDLE:'idle', DRAG:'drag', CONNECT:'connect', RESIZE:'resize', PAN:'pan', SELECT:'select', WAYPT:'waypoint', ROTATE:'rotate', LINE_EP:'line_ep' }
const mode = ref(MODE.IDLE)

const selected   = ref(new Set())
const hovered    = ref(null)
const hasDragged = ref(false)

const dragStart = ref({ mx:0, my:0 })
const dragItems = ref([])

const connectFrom   = ref(null)   // {shapeId, anchor} | {fromConnId, fromWpId, cx, cy}
const connectCursor = ref({x:0,y:0})

const wayptDrag  = ref(null)  // {connId, wid, ox, oy, mx, my}
const rotateDrag = ref(null)  // {id, cx, cy}
const lineEpDrag = ref(null)  // {id, ep:'start'|'end', ox, oy, mx, my}
const resizeTarget = ref(null)
const panStart     = ref({mx:0,px:0,py:0})
const selBox       = ref(null)

// ─── Label editing ────────────────────────────────────────────────────────────
const editingId    = ref(null)
const editingText  = ref('')
const editTextArea = ref(null)
const editPos      = ref({x:0,y:0,w:200,h:60})

const editingCell = ref(null)
const cellInput   = ref(null)
const cellPos     = ref({x:0,y:0,w:80,h:28})
const cellText    = ref('')

// ─── History ──────────────────────────────────────────────────────────────────
const history = ref([])
const histIdx = ref(-1)
const MAX_HIST = 50

// ─── Properties ──────────────────────────────────────────────────────────────
const showProps      = ref(false)
const propFill       = ref('#EFF6FF')
const propStroke     = ref('#4F46E5')
const propStrokeW    = ref(2)
const propFontColor  = ref('#111827')
const propFontSize   = ref(13)
const propOpacity    = ref(1)
const propDash       = ref(false)
const propBold       = ref(false)
const propRotation   = ref(0)
const propArrow      = ref('end')
const propStraight   = ref(false)
const propConnStroke = ref('#6B7280')
const propConnW      = ref(2)
// Line-specific props
const propLineColor  = ref('#374151')
const propLineW      = ref(2)
const propLineDash   = ref(false)
const propLineArrowS = ref(false)
const propLineArrowE = ref(false)
const propLineOpacity = ref(1)
// Arc-specific props
const arcStartAngle = ref(0)
const arcSweep      = ref(360)
const arcInner      = ref(0)
const arcClosed     = ref(true)

// ─── PNG library (serveur partagé) ───────────────────────────────────────────
const shapeLib    = ref([])
const libInput    = ref(null)
const sidebarTab  = ref('basic')
const libLoading  = ref(false)
const libUploading = ref(false)

async function loadLib() {
  libLoading.value = true
  try {
    const { data } = await axios.get(route('diagram-library.index'))
    shapeLib.value = data

    // ── Migration automatique depuis localStorage ──────────────────────────
    // Si cet ordinateur avait une bibliothèque locale, on l'envoie au serveur
    // puis on vide le localStorage pour ne plus en avoir besoin.
    const local = JSON.parse(localStorage.getItem('diag_shape_lib') || '[]')
    if (local.length) {
      const serverNames = new Set(data.map(i => i.name))
      for (const item of local) {
        // N'envoyer que les éléments absents du serveur (par nom)
        if (!serverNames.has(item.name)) {
          try {
            const res = await axios.post(route('diagram-library.store'), {
              name: item.name,
              src:  item.src,
            })
            shapeLib.value.push(res.data)
            serverNames.add(item.name)
          } catch { /* ignore les erreurs individuelles */ }
        }
      }
      localStorage.removeItem('diag_shape_lib')
    }
  } catch {
    shapeLib.value = []
  } finally {
    libLoading.value = false
  }
}

async function importLibPng(e) {
  const files = [...e.target.files]
  if (!files.length) return
  libUploading.value = true
  for (const f of files) {
    const src = await new Promise(resolve => {
      const reader = new FileReader()
      reader.onload = ev => resolve(ev.target.result)
      reader.readAsDataURL(f)
    })
    const name = f.name.replace(/\.[^.]+$/, '')
    try {
      const { data } = await axios.post(route('diagram-library.store'), { name, src })
      shapeLib.value.push(data)
    } catch { /* ignore */ }
  }
  libUploading.value = false
  e.target.value = ''
}

async function removeFromLib(id) {
  try {
    await axios.delete(route('diagram-library.destroy', id))
    shapeLib.value = shapeLib.value.filter(x => x.id !== id)
  } catch { /* ignore */ }
}

// ─── UID ──────────────────────────────────────────────────────────────────────
let idCounter = 1
function uid() { return 'n' + (idCounter++) }

// ─── Palette ──────────────────────────────────────────────────────────────────
const BASIC_SHAPES = [
  { type:'rect',          label:'▭ Rectangle' },
  { type:'rounded',       label:'▢ Arrondi' },
  { type:'circle',        label:'● Cercle' },
  { type:'diamond',       label:'◆ Losange' },
  { type:'hexagon',       label:'⬡ Hexagone' },
  { type:'parallelogram', label:'▱ Parallèle' },
  { type:'text',          label:'T Texte' },
  { type:'table',         label:'⊞ Tableau' },
  { type:'line',          label:'╱ Trait' },
  { type:'arc',           label:'◑ Arc/Cercle' },
]

// Présets d'arc : { l=label, sw=sweep°, sa=startAngle°, cl=closed, ir=innerRadius% }
const ARC_PRESETS = [
  { l:'⬤ Cercle',      sw:360, sa:0,   cl:true,  ir:0  },
  { l:'◑ ½ haut',      sw:180, sa:180, cl:true,  ir:0  },
  { l:'◒ ½ bas',       sw:180, sa:0,   cl:true,  ir:0  },
  { l:'◐ ½ gauche',    sw:180, sa:90,  cl:true,  ir:0  },
  { l:'◓ ½ droite',    sw:180, sa:270, cl:true,  ir:0  },
  { l:'◔ Quart',       sw:90,  sa:270, cl:true,  ir:0  },
  { l:'◉ Anneau',      sw:360, sa:0,   cl:true,  ir:50 },
  { l:'⌒ Arc ouvert',  sw:180, sa:180, cl:false, ir:0  },
]

const AV_SHAPES = [
  { key:'spot',          label:'🔦 Projecteur', path:'M50,10 L70,40 L80,40 L80,60 L70,60 L50,90 L30,60 L20,60 L20,40 L30,40 Z M38,50 A12,12 0 1,1 62,50 A12,12 0 1,1 38,50' },
  { key:'fresnel',       label:'💡 Fresnel',    path:'M18,18 L82,18 L82,72 L18,72 Z M50,72 L36,90 L64,90 Z M25,18 L25,72 M35,18 L35,72 M45,18 L45,72 M55,18 L55,72 M65,18 L65,72 M75,18 L75,72' },
  { key:'lyre',          label:'🎭 Lyre Moving', path:'M30,5 L70,5 L70,20 L62,20 L62,28 L82,28 L82,72 L62,72 L62,80 L70,80 L70,95 L30,95 L30,80 L38,80 L38,72 L18,72 L18,28 L38,28 L38,20 L30,20 Z M50,35 A15,15 0 1,1 50.01,35' },
  { key:'par',           label:'🔆 PAR / PC',   path:'M35,8 L65,8 L65,92 L35,92 Z M50,8 L50,92 M35,50 A15,42 0 0,1 65,50' },
  { key:'tube',          label:'💡 Tube LED',   path:'M12,32 L88,32 L88,68 L12,68 Z M12,40 L88,40 M12,50 L88,50 M12,60 L88,60' },
  { key:'camera',        label:'🎥 Caméra',     path:'M8,28 L62,28 L62,72 L8,72 Z M62,38 L92,24 L92,76 L62,62 Z M24,50 A13,13 0 1,1 50,50 A13,13 0 1,1 24,50' },
  { key:'truss_rect',    label:'▬ Truss rect.', path:'M5,28 L95,28 L95,72 L5,72 Z M5,28 L95,72 M95,28 L5,72 M50,28 L50,72' },
  { key:'truss_tri',     label:'△ Truss tri.',  path:'M50,5 L95,88 L5,88 Z M50,5 L50,88 M5,88 L72,46 M95,88 L28,46' },
  { key:'speaker',       label:'🔊 Baffle',     path:'M12,12 L88,12 L88,88 L12,88 Z M50,50 A22,22 0 1,1 50.01,50 M50,50 A10,10 0 1,1 50.01,50 M50,50 A34,34 0 1,1 50.01,50' },
  { key:'subwoofer',     label:'🔉 Subwoofer',  path:'M12,8 L88,8 L88,92 L12,92 Z M50,50 A30,30 0 1,1 50.01,50 M50,50 A15,15 0 1,1 50.01,50' },
  { key:'mixer',         label:'🎚 Console',    path:'M5,18 L95,18 L95,82 L5,82 Z M15,38 L15,72 M25,32 L25,72 M35,44 L35,72 M45,28 L45,72 M55,38 L55,72 M65,36 L65,72 M75,40 L75,72 M85,48 L85,72' },
  { key:'dmx',           label:'⚡ Node DMX',   path:'M18,18 L82,18 L82,82 L18,82 Z M28,34 L50,50 L28,66 M50,50 L72,50' },
  { key:'screen',        label:'🖥 Écran',      path:'M5,8 L95,8 L95,76 L5,76 Z M28,76 L28,92 M72,76 L72,92 M18,92 L82,92' },
  { key:'projvideo',     label:'📽 Vidéoproj.', path:'M14,24 L76,24 L76,76 L14,76 Z M76,38 L94,28 M76,62 L94,72 M76,50 L94,50 M34,50 A13,13 0 1,1 56,50 A13,13 0 1,1 34,50' },
]

// ─── Default styles ───────────────────────────────────────────────────────────
function defaultStyle() {
  return { fill:'#EFF6FF', stroke:'#4F46E5', strokeWidth:2, fontSize:13, fontColor:'#111827', opacity:1, dash:false, bold:false, rotation:0 }
}
function avStyle() {
  return { fill:'#1e293b', stroke:'#94a3b8', strokeWidth:1.5, fontSize:12, fontColor:'#f1f5f9', opacity:1, dash:false, bold:false, rotation:0 }
}

// ─── Canvas center ────────────────────────────────────────────────────────────
function canvasCenter() {
  const rect = svgEl.value?.getBoundingClientRect()
  return {
    cx: rect ? ((rect.width/2)  - panX.value) / zoom.value : 300,
    cy: rect ? ((rect.height/2) - panY.value) / zoom.value : 200,
  }
}

// ─── Add shapes ───────────────────────────────────────────────────────────────
function addShape(type) {
  if (!currentDiagram.value) return
  if (type === 'line') { addLine(); return }
  if (type === 'arc')  { addArc();  return }
  const {cx,cy} = canvasCenter()
  let w=140,h=70
  if (type==='circle')  { w=90;  h=90 }
  if (type==='diamond') { w=120; h=80 }
  if (type==='text')    { w=120; h=40 }
  if (type==='table')   { w=210; h=120 }
  push()
  shapes.value.push({ id:uid(), type, x:cx-w/2, y:cy-h/2, w, h,
    label: type==='table'?'': type==='text'?'Texte':'Forme',
    style: defaultStyle(),
    data: type==='table' ? {rows:3,cols:3,cells:Array.from({length:3},()=>Array(3).fill(''))} : {},
  })
  commit()
}

function addLine() {
  if (!currentDiagram.value) return
  const {cx,cy} = canvasCenter()
  const halfLen = 80
  push()
  shapes.value.push({
    id: uid(), type: 'line',
    // x,y,w,h = bounding box, kept in sync with endpoints
    x: cx - halfLen, y: cy, w: halfLen*2, h: 0,
    label: '',
    style: { stroke:'#374151', strokeWidth:2, opacity:1, dash:false, rotation:0,
             fill:'none', fontColor:'#111827', fontSize:12, bold:false },
    data: { x1: cx-halfLen, y1: cy, x2: cx+halfLen, y2: cy, arrowStart: false, arrowEnd: false },
  })
  commit()
}

function addArc() {
  if (!currentDiagram.value) return
  const {cx,cy} = canvasCenter()
  const r = 70
  push()
  shapes.value.push({
    id: uid(), type: 'arc',
    x: cx-r, y: cy-r, w: r*2, h: r*2,
    label: '',
    style: defaultStyle(),
    data: { startAngle: 0, sweep: 360, closed: true, innerRadius: 0 },
  })
  commit()
}

// ─── Arc path builder ────────────────────────────────────────────────────────
// Convention : 0° = droite (3h), sens horaire sur écran.
// sweep : étendue en degrés (1–360). closed : ferme vers le centre (secteur).
// innerRadius : % du rayon → anneau/donut (0 = plein).
function buildArcPath(s) {
  const cx = s.x + s.w/2, cy = s.y + s.h/2
  const rx = s.w/2,       ry = s.h/2
  const { startAngle=0, sweep=360, closed=true, innerRadius=0 } = s.data ?? {}
  const sw = Math.min(Math.max(sweep, 1), 360)

  // Cercle/ellipse complet(e) — deux demi-arcs pour éviter la dégénérescence
  if (sw >= 360) {
    if (innerRadius > 0) {
      const ir = innerRadius/100
      const irx = rx*ir, iry = ry*ir
      return (
        `M ${cx+rx} ${cy} A ${rx} ${ry} 0 1 1 ${cx-rx} ${cy} A ${rx} ${ry} 0 1 1 ${cx+rx} ${cy} Z ` +
        `M ${cx+irx} ${cy} A ${irx} ${iry} 0 1 0 ${cx-irx} ${cy} A ${irx} ${iry} 0 1 0 ${cx+irx} ${cy} Z`
      )
    }
    return `M ${cx+rx} ${cy} A ${rx} ${ry} 0 1 1 ${cx-rx} ${cy} A ${rx} ${ry} 0 1 1 ${cx+rx} ${cy} Z`
  }

  const sRad = startAngle * Math.PI/180
  const eRad = (startAngle + sw) * Math.PI/180
  const x1 = cx + rx*Math.cos(sRad), y1 = cy + ry*Math.sin(sRad)
  const x2 = cx + rx*Math.cos(eRad), y2 = cy + ry*Math.sin(eRad)
  const la = sw > 180 ? 1 : 0  // large-arc flag

  // Arc en anneau (avec rayon intérieur)
  if (innerRadius > 0) {
    const ir = innerRadius/100
    const irx = rx*ir, iry = ry*ir
    const ix1 = cx + irx*Math.cos(sRad), iy1 = cy + iry*Math.sin(sRad)
    const ix2 = cx + irx*Math.cos(eRad), iy2 = cy + iry*Math.sin(eRad)
    return `M ${x1} ${y1} A ${rx} ${ry} 0 ${la} 1 ${x2} ${y2} L ${ix2} ${iy2} A ${irx} ${iry} 0 ${la} 0 ${ix1} ${iy1} Z`
  }

  // Secteur fermé (camembert)
  if (closed) {
    return `M ${cx} ${cy} L ${x1} ${y1} A ${rx} ${ry} 0 ${la} 1 ${x2} ${y2} Z`
  }
  // Arc simple ouvert
  return `M ${x1} ${y1} A ${rx} ${ry} 0 ${la} 1 ${x2} ${y2}`
}

function addAvShape(avKey) {
  if (!currentDiagram.value) return
  const {cx,cy} = canvasCenter()
  const w=100,h=100
  push()
  shapes.value.push({ id:uid(), type:'av', x:cx-w/2, y:cy-h/2, w, h,
    label: AV_SHAPES.find(a=>a.key===avKey)?.label.replace(/^.+\s/,'') ?? avKey,
    style: avStyle(), data:{avKey},
  })
  commit()
}

function addImageShape(src, name='Image') {
  if (!currentDiagram.value) return
  const {cx,cy} = canvasCenter()
  const w=120,h=120
  push()
  shapes.value.push({ id:uid(), type:'image', x:cx-w/2, y:cy-h/2, w, h,
    label: name,
    style:{ ...defaultStyle(), fill:'transparent', stroke:'#6B7280', strokeWidth:1 },
    data:{ src, customAnchors:[] },
  })
  commit()
}

const imgInput = ref(null)
function triggerImgUpload() { imgInput.value?.click() }
function onImgUpload(e) {
  const f=e.target.files[0]; if(!f) return
  const reader=new FileReader()
  reader.onload=ev=>addImageShape(ev.target.result, f.name.replace(/\.[^.]+$/,''))
  reader.readAsDataURL(f)
  e.target.value=''
}

// ─── Rotation math ────────────────────────────────────────────────────────────
function rotatePoint(px, py, cx, cy, deg) {
  if (!deg) return {x:px, y:py}
  const rad = deg * Math.PI / 180
  const cos = Math.cos(rad), sin = Math.sin(rad)
  return {
    x: cx + (px-cx)*cos - (py-cy)*sin,
    y: cy + (px-cx)*sin + (py-cy)*cos,
  }
}

function shapeCenterOf(s) { return { cx: s.x+s.w/2, cy: s.y+s.h/2 } }

// ─── AV path scaling (100×100 viewBox → actual bounds) ───────────────────────
function avPathScaled(s) {
  const av = AV_SHAPES.find(a=>a.key===s.data?.avKey); if(!av) return ''
  const sx=s.w/100, sy=s.h/100
  // Replace all coordinate pairs in the path string
  return av.path
    .replace(/([MLCQTSAZamlcqtsaz])\s*/g, '$1')
    .replace(/(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/g, (_,a,b)=>
      `${s.x+parseFloat(a)*sx},${s.y+parseFloat(b)*sy}`)
    .replace(/([MLHVCSQTAZ])\s*(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)/g, (m,cmd,a,b)=>
      `${cmd}${s.x+parseFloat(a)*sx} ${s.y+parseFloat(b)*sy}`)
}

// ─── Anchor helpers ───────────────────────────────────────────────────────────
const STD_DIRS = ['N','E','S','W']

function getAnchor(s, dir) {
  // Line shape: endpoints are the anchors
  if (s.type === 'line') {
    const d = s.data
    if (dir === 'N' || dir === 'start') return {x: d.x1, y: d.y1}
    if (dir === 'S' || dir === 'end')   return {x: d.x2, y: d.y2}
    // E/W/center → midpoint
    return {x: (d.x1+d.x2)/2, y: (d.y1+d.y2)/2}
  }

  const {cx,cy} = shapeCenterOf(s)
  const rot = s.style?.rotation || 0

  // Custom anchor (image free point)
  if (typeof dir === 'string' && dir.startsWith('ca-')) {
    const ca = s.data?.customAnchors?.find(a=>a.id===dir)
    if (ca) {
      const raw = { x: s.x+ca.rx*s.w, y: s.y+ca.ry*s.h }
      return rot ? rotatePoint(raw.x, raw.y, cx, cy, rot) : raw
    }
  }

  let raw
  if (dir==='N') raw={x:cx,   y:s.y}
  else if (dir==='S') raw={x:cx,   y:s.y+s.h}
  else if (dir==='E') raw={x:s.x+s.w, y:cy}
  else if (dir==='W') raw={x:s.x,     y:cy}
  else raw={x:cx,y:cy}

  return rot ? rotatePoint(raw.x, raw.y, cx, cy, rot) : raw
}

// Sync line bounding box from its endpoints (for rubber-band & group bounds)
function syncLineBBox(s) {
  if (s.type !== 'line') return
  const {x1,y1,x2,y2} = s.data
  s.x = Math.min(x1,x2); s.y = Math.min(y1,y2)
  s.w = Math.abs(x2-x1); s.h = Math.abs(y2-y1)
}

function bestAnchors(a, b) {
  const pairs=[['E','W'],['W','E'],['S','N'],['N','S'],['E','N'],['E','S'],['W','N'],['W','S'],['N','E'],['N','W'],['S','E'],['S','W']]
  let best=null, bestD=Infinity
  for (const [da,db] of pairs) {
    const pa=getAnchor(a,da), pb=getAnchor(b,db)
    const d=Math.hypot(pa.x-pb.x, pa.y-pb.y)
    if (d<bestD) { bestD=d; best={fromAnchor:da,toAnchor:db} }
  }
  return best
}

// ─── Path building ────────────────────────────────────────────────────────────
function buildPath(p1, p2, waypoints, straight) {
  const pts=[p1,...waypoints,p2]
  if (pts.length===2) {
    if (straight) return `M ${p1.x} ${p1.y} L ${p2.x} ${p2.y}`
    const dx=(p2.x-p1.x)*0.4
    return `M ${p1.x} ${p1.y} C ${p1.x+dx} ${p1.y}, ${p2.x-dx} ${p2.y}, ${p2.x} ${p2.y}`
  }
  return 'M '+pts.map(p=>`${p.x} ${p.y}`).join(' L ')
}

// Resolve start point of a connection (handles waypoint-origin)
function connStartPoint(conn) {
  if (conn.from) {
    const a=shapes.value.find(s=>s.id===conn.from); if(!a) return null
    return getAnchor(a, conn.fromAnchor||'E')
  }
  if (conn.fromConnId && conn.fromWpId) {
    const src=conns.value.find(c=>c.id===conn.fromConnId); if(!src) return null
    const wp=src.waypoints?.find(w=>w.id===conn.fromWpId); if(!wp) return null
    return {x:wp.x, y:wp.y}
  }
  return null
}

function connEndPoint(conn) {
  const b=shapes.value.find(s=>s.id===conn.to); if(!b) return null
  return getAnchor(b, conn.toAnchor||'W')
}

function connPathFull(conn) {
  const p1=connStartPoint(conn), p2=connEndPoint(conn)
  if (!p1||!p2) return ''
  return buildPath(p1, p2, conn.waypoints||[], conn.straight)
}

function connMidpoint(conn) {
  const wps=conn.waypoints||[]
  if (wps.length>0) return { x:wps[Math.floor(wps.length/2)].x, y:wps[Math.floor(wps.length/2)].y }
  const p1=connStartPoint(conn), p2=connEndPoint(conn)
  if (!p1||!p2) return {x:0,y:0}
  return { x:(p1.x+p2.x)/2, y:(p1.y+p2.y)/2 }
}

function connSegmentMids(conn) {
  const p1=connStartPoint(conn), p2=connEndPoint(conn)
  if (!p1||!p2) return []
  const pts=[p1,...(conn.waypoints||[]),p2]
  return pts.slice(0,-1).map((p,i)=>({
    x:(p.x+pts[i+1].x)/2, y:(p.y+pts[i+1].y)/2,
    insertAfterIdx: i,   // insert after index i in waypoints (i=0 means before first wp)
  }))
}

function previewPath(from, cur) {
  if (!from) return ''
  let p1
  if (from.fromConnId) {
    const src=conns.value.find(c=>c.id===from.fromConnId)
    const wp=src?.waypoints?.find(w=>w.id===from.fromWpId)
    if (!wp) return ''
    p1={x:wp.x,y:wp.y}
  } else {
    const a=shapes.value.find(s=>s.id===from.shapeId); if(!a) return ''
    p1=getAnchor(a, from.anchor)
  }
  return buildPath(p1, {x:cur.x,y:cur.y}, [], false)
}

function groupBounds(g) {
  const ss=shapes.value.filter(s=>g.shapeIds.includes(s.id)); if(!ss.length) return {x:0,y:0,w:0,h:0}
  const xs=ss.flatMap(s=>[s.x,s.x+s.w]), ys=ss.flatMap(s=>[s.y,s.y+s.h])
  const pad=16, x=Math.min(...xs)-pad, y=Math.min(...ys)-pad
  return {x,y,w:Math.max(...xs)-x+pad,h:Math.max(...ys)-y+pad}
}

// ─── SVG coords ───────────────────────────────────────────────────────────────
function svgPt(clientX,clientY) {
  const r=svgEl.value.getBoundingClientRect()
  return {x:(clientX-r.left-panX.value)/zoom.value, y:(clientY-r.top-panY.value)/zoom.value}
}
function screenPt(cx,cy) {
  const r=svgEl.value.getBoundingClientRect()
  return {x:cx*zoom.value+panX.value+r.left, y:cy*zoom.value+panY.value+r.top}
}

// ─── History ──────────────────────────────────────────────────────────────────
function snapshot() { return JSON.stringify({shapes:shapes.value,conns:conns.value,groups:groups.value}) }
function push() {
  history.value=history.value.slice(0,histIdx.value+1)
  history.value.push(snapshot())
  if (history.value.length>MAX_HIST) history.value.shift()
  histIdx.value=history.value.length-1
}
function commit() {}
function undo() { if(histIdx.value<=0)return; histIdx.value--; restore(history.value[histIdx.value]) }
function redo() { if(histIdx.value>=history.value.length-1)return; histIdx.value++; restore(history.value[histIdx.value]) }
function restore(snap) { const d=JSON.parse(snap); shapes.value=d.shapes; conns.value=d.conns; groups.value=d.groups }
function initHistory() { history.value=[snapshot()]; histIdx.value=0 }

// ─── Selection ────────────────────────────────────────────────────────────────
const selectedShapes = computed(()=>shapes.value.filter(s=>selected.value.has(s.id)))
const selectedConns  = computed(()=>conns.value.filter(c=>selected.value.has(c.id)))
function clearSel()     { selected.value=new Set() }
function toggleSel(id)  { const s=new Set(selected.value); s.has(id)?s.delete(id):s.add(id); selected.value=s }
function setSel(id)     { selected.value=new Set([id]) }
function isSelected(id) { return selected.value.has(id) }
function isHovered(id)  { return hovered.value===id }

// ─── Mouse handlers ───────────────────────────────────────────────────────────
function onSvgMousedown(e) {
  if (e.button===1||(e.button===0&&e.altKey)) {
    mode.value=MODE.PAN; panStart.value={mx:e.clientX,my:e.clientY,px:panX.value,py:panY.value}
    e.preventDefault(); return
  }
  if (e.button!==0||mode.value===MODE.CONNECT) return
  if (!e.ctrlKey) clearSel()
  const pt=svgPt(e.clientX,e.clientY)
  mode.value=MODE.SELECT; selBox.value={x1:pt.x,y1:pt.y,x2:pt.x,y2:pt.y}
}

function onShapeMousedown(e,id) {
  e.stopPropagation()
  if (e.button!==0||mode.value===MODE.CONNECT) return
  e.ctrlKey?toggleSel(id):(!selected.value.has(id)&&setSel(id))
  const __s=shapes.value.find(s=>s.id===id)
  if (__s?.locked) return   // verrouillé : sélectionnable mais non déplaçable
  mode.value=MODE.DRAG; hasDragged.value=false
  dragStart.value={mx:e.clientX,my:e.clientY}
  dragItems.value=[...selected.value]
    .filter(sid=>shapes.value.find(s=>s.id===sid))
    .map(sid=>{
      const s=shapes.value.find(s=>s.id===sid)
      if (s.type==='line') return{id:sid,ox:s.x,oy:s.y,ox1:s.data.x1,oy1:s.data.y1,ox2:s.data.x2,oy2:s.data.y2}
      return{id:sid,ox:s.x,oy:s.y}
    })
  push()
}

function onConnMousedown(e,id) {
  e.stopPropagation(); if(e.button!==0)return
  e.ctrlKey?toggleSel(id):setSel(id)
}

function onGroupMousedown(e,g) {
  e.stopPropagation(); if(e.button!==0)return
  e.ctrlKey?toggleSel(g.id):setSel(g.id)
  mode.value=MODE.DRAG; hasDragged.value=false
  dragStart.value={mx:e.clientX,my:e.clientY}; push()
  dragItems.value=shapes.value.filter(s=>g.shapeIds.includes(s.id)).map(s=>({id:s.id,ox:s.x,oy:s.y}))
}

// Anchor → start connection from shape
function onAnchorMousedown(e,shapeId,anchor) {
  e.stopPropagation()
  mode.value=MODE.CONNECT
  connectFrom.value={shapeId,anchor}
  connectCursor.value=svgPt(e.clientX,e.clientY); push()
}

// Waypoint green dot → start new connection from waypoint
function onWayptConnMousedown(e,conn,wp) {
  e.stopPropagation()
  mode.value=MODE.CONNECT
  connectFrom.value={fromConnId:conn.id,fromWpId:wp.id}
  connectCursor.value=svgPt(e.clientX,e.clientY); push()
}

// Waypoint drag handle
function onWayptMousedown(e,conn,wp) {
  e.stopPropagation(); if(e.button!==0)return
  mode.value=MODE.WAYPT
  wayptDrag.value={connId:conn.id,wid:wp.id,ox:wp.x,oy:wp.y,mx:e.clientX,my:e.clientY}; push()
}

// Line endpoint drag
function onLineEpMousedown(e,id,ep) {
  e.stopPropagation(); if(e.button!==0)return
  const s=shapes.value.find(s=>s.id===id); if(!s||s.locked)return
  mode.value=MODE.LINE_EP
  lineEpDrag.value={id,ep,ox:s.data[ep==='start'?'x1':'x2'],oy:s.data[ep==='start'?'y1':'y2'],mx:e.clientX,my:e.clientY}
  push()
}

// Resize corner handle
function onResizeMousedown(e,id,handle) {
  e.stopPropagation()
  const s=shapes.value.find(s=>s.id===id); if(!s||s.locked)return
  push(); mode.value=MODE.RESIZE
  resizeTarget.value={id,handle,mx:e.clientX,my:e.clientY,ox:s.x,oy:s.y,ow:s.w,oh:s.h}
}

// Rotation handle (arc icon above shape)
function onRotateMousedown(e,id) {
  e.stopPropagation(); if(e.button!==0)return
  const s=shapes.value.find(s=>s.id===id); if(!s||s.locked)return
  push(); mode.value=MODE.ROTATE
  rotateDrag.value={id, cx:s.x+s.w/2, cy:s.y+s.h/2}
}

// Shift+click on image → add custom anchor
function onImageClick(e,s) {
  if (!e.shiftKey||mode.value===MODE.CONNECT) return
  e.stopPropagation()
  const pt=svgPt(e.clientX,e.clientY)
  const rx=Math.max(0,Math.min(1,(pt.x-s.x)/s.w))
  const ry=Math.max(0,Math.min(1,(pt.y-s.y)/s.h))
  push()
  if (!s.data.customAnchors) s.data.customAnchors=[]
  s.data.customAnchors.push({id:'ca-'+uid(),rx,ry})
  commit()
}

function removeCustomAnchor(e,s,aId) {
  e.stopPropagation(); push()
  s.data.customAnchors=s.data.customAnchors.filter(a=>a.id!==aId); commit()
}

// Click midpoint of segment → insert waypoint
function onConnSegMidClick(e,conn,mid) {
  e.stopPropagation(); push()
  const newWp={id:uid(),x:mid.x,y:mid.y}
  const wps=conn.waypoints||[]
  conn.waypoints=[...wps.slice(0,mid.insertAfterIdx),newWp,...wps.slice(mid.insertAfterIdx)]
  commit()
}

function removeWaypoint(conn,wpId) {
  push(); conn.waypoints=(conn.waypoints||[]).filter(w=>w.id!==wpId); commit()
}

// ─── Global mousemove ─────────────────────────────────────────────────────────
function onSvgMousemove(e) {
  const pt=svgPt(e.clientX,e.clientY)
  if (mode.value===MODE.PAN) {
    panX.value=panStart.value.px+(e.clientX-panStart.value.mx)
    panY.value=panStart.value.py+(e.clientY-panStart.value.my); return
  }
  if (mode.value===MODE.DRAG) {
    const dx=(e.clientX-dragStart.value.mx)/zoom.value
    const dy=(e.clientY-dragStart.value.my)/zoom.value
    if (Math.abs(dx)>2||Math.abs(dy)>2) hasDragged.value=true
    dragItems.value.forEach(item=>{
      const s=shapes.value.find(s=>s.id===item.id)
      if (!s) return
      if (s.type==='line') {
        s.data.x1=snap(item.ox1+dx); s.data.y1=snap(item.oy1+dy)
        s.data.x2=snap(item.ox2+dx); s.data.y2=snap(item.oy2+dy)
        syncLineBBox(s)
      } else {
        s.x=snap(item.ox+dx); s.y=snap(item.oy+dy)
      }
    }); return
  }
  if (mode.value===MODE.LINE_EP) {
    const ld=lineEpDrag.value; if(!ld)return
    const s=shapes.value.find(s=>s.id===ld.id); if(!s)return
    const dx=(e.clientX-ld.mx)/zoom.value, dy=(e.clientY-ld.my)/zoom.value
    if (ld.ep==='start') { s.data.x1=snap(ld.ox+dx); s.data.y1=snap(ld.oy+dy) }
    else                 { s.data.x2=snap(ld.ox+dx); s.data.y2=snap(ld.oy+dy) }
    syncLineBBox(s); return
  }
  if (mode.value===MODE.CONNECT) { connectCursor.value=pt; return }
  if (mode.value===MODE.WAYPT) {
    const wd=wayptDrag.value; if(!wd)return
    const conn=conns.value.find(c=>c.id===wd.connId); if(!conn)return
    const wp=conn.waypoints?.find(w=>w.id===wd.wid); if(!wp)return
    wp.x=wd.ox+(e.clientX-wd.mx)/zoom.value
    wp.y=wd.oy+(e.clientY-wd.my)/zoom.value; return
  }
  if (mode.value===MODE.ROTATE) {
    const rd=rotateDrag.value; if(!rd)return
    const s=shapes.value.find(s=>s.id===rd.id); if(!s)return
    let angle=Math.atan2(pt.y-rd.cy, pt.x-rd.cx)*180/Math.PI+90
    if (e.shiftKey) angle=Math.round(angle/15)*15  // snap to 15°
    s.style.rotation=((Math.round(angle)%360)+360)%360; return
  }
  if (mode.value===MODE.RESIZE) {
    const rt=resizeTarget.value
    const dx=(e.clientX-rt.mx)/zoom.value, dy=(e.clientY-rt.my)/zoom.value
    const s=shapes.value.find(s=>s.id===rt.id); if(!s)return
    const MIN=30
    if (rt.handle==='se') { s.w=Math.max(MIN,snap(rt.ow+dx)); s.h=Math.max(MIN,snap(rt.oh+dy)) }
    else if (rt.handle==='sw') { s.w=Math.max(MIN,snap(rt.ow-dx)); s.x=rt.ox+rt.ow-s.w; s.h=Math.max(MIN,snap(rt.oh+dy)) }
    else if (rt.handle==='ne') { s.w=Math.max(MIN,snap(rt.ow+dx)); s.h=Math.max(MIN,snap(rt.oh-dy)); s.y=rt.oy+rt.oh-s.h }
    else if (rt.handle==='nw') { s.w=Math.max(MIN,snap(rt.ow-dx)); s.x=rt.ox+rt.ow-s.w; s.h=Math.max(MIN,snap(rt.oh-dy)); s.y=rt.oy+rt.oh-s.h }
    return
  }
  if (mode.value===MODE.SELECT&&selBox.value) { selBox.value.x2=pt.x; selBox.value.y2=pt.y }
}

// ─── Global mouseup ───────────────────────────────────────────────────────────
function onSvgMouseup(e) {
  if (mode.value===MODE.CONNECT) {
    const pt=svgPt(e.clientX,e.clientY)
    // Find target shape under mouse (excluding origin shape for shape-origin)
    const originShapeId = connectFrom.value?.shapeId ?? null
    const target=shapes.value.find(s=>
      pt.x>=s.x&&pt.x<=s.x+s.w&&pt.y>=s.y&&pt.y<=s.y+s.h&&s.id!==originShapeId
    )
    if (target&&connectFrom.value) {
      const from=connectFrom.value
      let fromAnchor='E', toAnchor='W'
      if (!from.fromConnId) {
        // shape-to-shape: find best anchors
        const originShape=shapes.value.find(s=>s.id===from.shapeId)
        if (originShape) {
          const best=bestAnchors(originShape,target)
          fromAnchor=best.fromAnchor; toAnchor=best.toAnchor
        }
      } else {
        // waypoint-to-shape: use best anchor from waypoint position to target
        const wp=conns.value.find(c=>c.id===from.fromConnId)?.waypoints?.find(w=>w.id===from.fromWpId)
        if (wp) {
          const fakeShape={x:wp.x-1,y:wp.y-1,w:2,h:2,style:{rotation:0}}
          const best=bestAnchors(fakeShape,target)
          toAnchor=best.toAnchor
        }
      }
      conns.value.push({
        id:uid(),
        from: from.fromConnId ? null : from.shapeId,
        fromAnchor: from.fromConnId ? null : fromAnchor,
        fromConnId: from.fromConnId ?? null,
        fromWpId:   from.fromWpId   ?? null,
        to:target.id, toAnchor,
        waypoints:[], label:'',
        arrow:propArrow.value, straight:propStraight.value,
        style:{stroke:propConnStroke.value,strokeWidth:propConnW.value,dash:false},
      })
      commit()
    } else {
      history.value.pop(); histIdx.value=Math.max(0,histIdx.value-1)
    }
    connectFrom.value=null; mode.value=MODE.IDLE; return
  }
  if (mode.value===MODE.SELECT&&selBox.value) {
    const b=selBox.value
    const x1=Math.min(b.x1,b.x2),x2=Math.max(b.x1,b.x2)
    const y1=Math.min(b.y1,b.y2),y2=Math.max(b.y1,b.y2)
    if (x2-x1>5||y2-y1>5)
      selected.value=new Set(shapes.value.filter(s=>{
        if (s.type==='line') {
          // Select line if either endpoint is inside the box
          return (s.data.x1>=x1&&s.data.x1<=x2&&s.data.y1>=y1&&s.data.y1<=y2)||
                 (s.data.x2>=x1&&s.data.x2<=x2&&s.data.y2>=y1&&s.data.y2<=y2)
        }
        return s.x<x2&&s.x+s.w>x1&&s.y<y2&&s.y+s.h>y1
      }).map(s=>s.id))
    selBox.value=null
  }
  if (mode.value===MODE.DRAG) {
    if (hasDragged.value) commit(); else { history.value.pop(); histIdx.value=Math.max(0,histIdx.value-1) }
  }
  if (mode.value===MODE.RESIZE||mode.value===MODE.WAYPT||mode.value===MODE.ROTATE||mode.value===MODE.LINE_EP) commit()
  if (mode.value!==MODE.CONNECT) mode.value=MODE.IDLE
}

function onSvgWheel(e) {
  e.preventDefault()
  const r=svgEl.value.getBoundingClientRect()
  const mx=e.clientX-r.left, my=e.clientY-r.top
  const factor=e.deltaY<0?1.1:0.9
  const nz=Math.max(0.1,Math.min(8,zoom.value*factor))
  panX.value=mx-(mx-panX.value)*(nz/zoom.value)
  panY.value=my-(my-panY.value)*(nz/zoom.value)
  zoom.value=nz
}

// ─── Label editing ────────────────────────────────────────────────────────────
function onShapeDblclick(e,id) {
  e.stopPropagation()
  const s=shapes.value.find(s=>s.id===id); if(!s||s.type==='table')return
  editingId.value=id; editingText.value=s.label
  const tl=screenPt(s.x,s.y), br=screenPt(s.x+s.w,s.y+s.h)
  editPos.value={x:tl.x,y:tl.y,w:br.x-tl.x,h:br.y-tl.y}
  nextTick(()=>editTextArea.value?.focus())
}
function commitEdit() {
  if (!editingId.value)return
  const s=shapes.value.find(s=>s.id===editingId.value)
  if (s) { push(); s.label=editingText.value; commit() }
  editingId.value=null
}
function onConnDblclick(e,id) {
  e.stopPropagation()
  const c=conns.value.find(c=>c.id===id); if(!c)return
  const lbl=prompt('Libellé du lien :',c.label??'')
  if (lbl!==null) { push(); c.label=lbl; commit() }
}

function tableCellBounds(s,ri,ci) {
  const cw=s.w/(s.data.cols||3), rh=s.h/(s.data.rows||3)
  return {x:s.x+ci*cw,y:s.y+ri*rh,w:cw,h:rh}
}
function onCellDblclick(e,shapeId,ri,ci) {
  e.stopPropagation()
  const s=shapes.value.find(s=>s.id===shapeId); if(!s)return
  const b=tableCellBounds(s,ri,ci)
  const tl=screenPt(b.x,b.y), br=screenPt(b.x+b.w,b.y+b.h)
  cellPos.value={x:tl.x,y:tl.y,w:br.x-tl.x,h:br.y-tl.y}
  cellText.value=s.data.cells?.[ri]?.[ci]??''
  editingCell.value={shapeId,ri,ci}
  nextTick(()=>cellInput.value?.focus())
}
function commitCell() {
  if (!editingCell.value)return
  const {shapeId,ri,ci}=editingCell.value
  const s=shapes.value.find(s=>s.id===shapeId)
  if (s) { push(); s.data.cells[ri][ci]=cellText.value; commit() }
  editingCell.value=null
}

// ─── Keyboard ─────────────────────────────────────────────────────────────────
function onKeydown(e) {
  if (editingId.value||editingCell.value)return
  const tag=document.activeElement.tagName
  if (tag==='INPUT'||tag==='TEXTAREA'||tag==='SELECT')return
  if ((e.key==='Delete'||e.key==='Backspace')&&selected.value.size) {
    push()
    const del=new Set(selected.value)
    shapes.value=shapes.value.filter(s=>!del.has(s.id)||s.locked)  // les formes verrouillées sont protégées
    conns.value=conns.value.filter(c=>!del.has(c.id)&&!del.has(c.from)&&!del.has(c.to))
    groups.value=groups.value.filter(g=>!del.has(g.id))
    groups.value.forEach(g=>{g.shapeIds=g.shapeIds.filter(id=>!del.has(id))})
    clearSel(); commit()
  }
  if (e.ctrlKey&&e.key==='z') { e.preventDefault(); undo() }
  if (e.ctrlKey&&(e.key==='y'||(e.shiftKey&&e.key==='Z'))) { e.preventDefault(); redo() }
  if (e.ctrlKey&&e.key==='g') { e.preventDefault(); createGroup() }
  if (e.ctrlKey&&e.key==='a') { e.preventDefault(); shapes.value.forEach(s=>selected.value.add(s.id)) }
  if (e.ctrlKey&&e.key==='d') { e.preventDefault(); duplicateSelected() }
}

function duplicateSelected() {
  if (!selected.value.size)return
  push()
  const newIds=[]
  shapes.value.filter(s=>selected.value.has(s.id)).forEach(s=>{
    const n={...JSON.parse(JSON.stringify(s)),id:uid(),x:s.x+20,y:s.y+20}
    shapes.value.push(n); newIds.push(n.id)
  })
  selected.value=new Set(newIds); commit()
}

function createGroup() {
  const ids=[...selected.value].filter(id=>shapes.value.find(s=>s.id===id))
  if (ids.length<2)return; push()
  groups.value.push({id:uid(),label:'Groupe',shapeIds:ids,style:{fill:'rgba(99,102,241,0.06)',stroke:'#6366f1'}})
  commit()
}

// ─── Properties sync ──────────────────────────────────────────────────────────
watch(selected,()=>{
  const ss=selectedShapes.value, cc=selectedConns.value
  if (ss.length===1) {
    const s=ss[0]
    if (s.type==='line') {
      propLineColor.value=s.style.stroke||'#374151'; propLineW.value=s.style.strokeWidth||2
      propLineDash.value=s.style.dash||false; propLineOpacity.value=s.style.opacity||1
      propLineArrowS.value=s.data.arrowStart||false; propLineArrowE.value=s.data.arrowEnd||false
      showProps.value=true; return
    }
    propFill.value=s.style.fill; propStroke.value=s.style.stroke; propStrokeW.value=s.style.strokeWidth
    propFontColor.value=s.style.fontColor; propFontSize.value=s.style.fontSize
    propOpacity.value=s.style.opacity; propDash.value=s.style.dash; propBold.value=s.style.bold
    propRotation.value=s.style.rotation||0
    if (s.type==='arc') {
      arcStartAngle.value = s.data.startAngle??0
      arcSweep.value      = s.data.sweep??360
      arcInner.value      = s.data.innerRadius??0
      arcClosed.value     = s.data.closed??true
    }
    showProps.value=true
  } else if (cc.length===1) {
    const c=cc[0]
    propArrow.value=c.arrow; propStraight.value=c.straight
    propConnStroke.value=c.style.stroke; propConnW.value=c.style.strokeWidth; propDash.value=c.style.dash
    showProps.value=true
  } else { showProps.value=selected.value.size>0 }
})

function applyLineProps() {
  push()
  selectedShapes.value.filter(s=>s.type==='line').forEach(s=>{
    s.style.stroke=propLineColor.value; s.style.strokeWidth=propLineW.value
    s.style.dash=propLineDash.value; s.style.opacity=propLineOpacity.value
    s.data.arrowStart=propLineArrowS.value; s.data.arrowEnd=propLineArrowE.value
  })
  commit()
}

function applyArcProps() {
  push()
  selectedShapes.value.filter(s=>s.type==='arc').forEach(s=>{
    s.data.startAngle  = arcStartAngle.value
    s.data.sweep       = arcSweep.value
    s.data.innerRadius = arcInner.value
    s.data.closed      = arcClosed.value
  })
  commit()
}
function setArcPreset(sw, sa, cl, ir) {
  arcSweep.value=sw; arcStartAngle.value=sa; arcClosed.value=cl; arcInner.value=ir
  applyArcProps()
}

function applyShapeProps() {
  push()
  selectedShapes.value.forEach(s=>{
    s.style.fill=propFill.value; s.style.stroke=propStroke.value; s.style.strokeWidth=propStrokeW.value
    s.style.fontColor=propFontColor.value; s.style.fontSize=propFontSize.value
    s.style.opacity=propOpacity.value; s.style.dash=propDash.value; s.style.bold=propBold.value
    s.style.rotation=propRotation.value
  })
  commit()
}
function applyConnProps() {
  push()
  selectedConns.value.forEach(c=>{
    c.arrow=propArrow.value; c.straight=propStraight.value
    c.style.stroke=propConnStroke.value; c.style.strokeWidth=propConnW.value; c.style.dash=propDash.value
  })
  commit()
}
function snapRotation(delta) {
  propRotation.value=((propRotation.value+delta)%360+360)%360; applyShapeProps()
}

// ─── Diagram CRUD ─────────────────────────────────────────────────────────────
function serializeCanvas() { return {shapes:shapes.value,conns:conns.value,groups:groups.value} }

function loadCanvas(data) {
  shapes.value=data?.shapes??[]; conns.value=data?.conns??[]; groups.value=data?.groups??[]
  conns.value.forEach(c=>{ if(!c.waypoints)c.waypoints=[]; if(!('fromConnId' in c))c.fromConnId=null; if(!('fromWpId' in c))c.fromWpId=null })
  shapes.value.forEach(s=>{
    if (!('rotation' in (s.style||{}))) s.style.rotation=0
    if (s.type==='line') syncLineBBox(s)
  })
  const allIds=[...shapes.value,...conns.value,...groups.value].map(x=>parseInt((x.id||'').replace('n',''))||0)
  idCounter=allIds.length?Math.max(...allIds)+1:1
  clearSel(); initHistory(); panX.value=0; panY.value=0; zoom.value=1
}

function selectDiagram(d) {
  currentDiagram.value=d; diagTitle.value=d.name; diagDesc.value=d.description??''; loadCanvas(d.data??{})
}

async function saveDiagram() {
  if (!currentDiagram.value)return
  saving.value=true
  try {
    await axios.put(route('diagrams.update',currentDiagram.value.id),{
      name:diagTitle.value, description:diagDesc.value, data:serializeCanvas()
    })
    const idx=localDiagrams.value.findIndex(d=>d.id===currentDiagram.value.id)
    if (idx!==-1) { localDiagrams.value[idx]={...localDiagrams.value[idx],name:diagTitle.value}; currentDiagram.value={...currentDiagram.value,name:diagTitle.value} }
  } finally { saving.value=false }
}

async function createDiagram() {
  if (!newName.value.trim())return; saving.value=true
  try {
    const res=await axios.post(route('diagrams.store'),{name:newName.value.trim(),description:newDesc.value,data:{shapes:[],conns:[],groups:[]}})
    localDiagrams.value.unshift(res.data); selectDiagram(res.data)
    showNewDiagram.value=false; newName.value=''; newDesc.value=''
  } finally { saving.value=false }
}

async function deleteDiagram(d) {
  if (!confirm(`Supprimer "${d.name}" ?`))return
  await axios.delete(route('diagrams.destroy',d.id))
  localDiagrams.value=localDiagrams.value.filter(x=>x.id!==d.id)
  if (currentDiagram.value?.id===d.id) { currentDiagram.value=null; shapes.value=[]; conns.value=[]; groups.value=[] }
}

async function linkEvent() {
  if (!selectedEventId.value||!currentDiagram.value)return
  await axios.post(route('diagrams.attach-event',currentDiagram.value.id),{event_id:selectedEventId.value})
  showEventModal.value=false; selectedEventId.value=null; alert('Diagramme lié à l\'événement !')
}

// ─── Export ───────────────────────────────────────────────────────────────────
const showExportModal = ref(false)
const exportFmt       = ref('png')      // png | jpeg | svg | pdf
const exportScale     = ref(2)          // 1 | 2 | 3
const exportPdfSize   = ref('a4')       // a4 | a3 | a5 | letter | legal
const exportPdfOrient = ref('landscape') // portrait | landscape
const exportFit       = ref(true)       // ajuster au contenu avant export
const exportWorking   = ref(false)

function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a'); a.href=url; a.download=filename; a.click()
  setTimeout(()=>URL.revokeObjectURL(url), 2000)
}

// Sérialise le SVG courant en canvas (résolution = taille écran × scale)
async function svgToCanvas() {
  const el = svgEl.value
  const rect = el.getBoundingClientRect()
  const scale = exportScale.value
  const W = Math.round(rect.width * scale)
  const H = Math.round(rect.height * scale)

  const clone = el.cloneNode(true)
  clone.setAttribute('width',  W)
  clone.setAttribute('height', H)
  clone.setAttribute('xmlns',  'http://www.w3.org/2000/svg')
  clone.removeAttribute('class')

  const svgStr = new XMLSerializer().serializeToString(clone)
  const blob   = new Blob([svgStr], { type:'image/svg+xml;charset=utf-8' })
  const url    = URL.createObjectURL(blob)
  try {
    const img = new Image()
    await new Promise((res, rej) => { img.onload=res; img.onerror=rej; img.src=url })
    const canvas = document.createElement('canvas')
    canvas.width = W; canvas.height = H
    const ctx = canvas.getContext('2d')
    ctx.fillStyle = '#ffffff'; ctx.fillRect(0,0,W,H)
    ctx.drawImage(img, 0, 0, W, H)
    return canvas
  } finally { URL.revokeObjectURL(url) }
}

// Applique un zoom "fit-to-content" temporaire avant l'export raster
async function withFitView(fn) {
  if (!exportFit.value || !shapes.value.length) return fn()
  const el = svgEl.value
  const rect = el.getBoundingClientRect()
  const pad = 40
  const xs = shapes.value.flatMap(s=> s.type==='line'?[s.data.x1,s.data.x2]:[s.x,s.x+s.w])
  const ys = shapes.value.flatMap(s=> s.type==='line'?[s.data.y1,s.data.y2]:[s.y,s.y+s.h])
  const minX=Math.min(...xs)-pad, maxX=Math.max(...xs)+pad
  const minY=Math.min(...ys)-pad, maxY=Math.max(...ys)+pad
  const cW=maxX-minX, cH=maxY-minY
  const fitZ = Math.min(rect.width/cW, rect.height/cH, 8)
  const oldZ=zoom.value, oldPX=panX.value, oldPY=panY.value
  zoom.value = fitZ
  panX.value = (rect.width  - cW*fitZ)/2 - minX*fitZ
  panY.value = (rect.height - cH*fitZ)/2 - minY*fitZ
  await nextTick()
  try { return await fn() }
  finally { zoom.value=oldZ; panX.value=oldPX; panY.value=oldPY }
}

async function doExport() {
  if (!svgEl.value || !currentDiagram.value) return
  exportWorking.value = true
  const name = currentDiagram.value.name ?? 'diagramme'
  try {
    // ── SVG ──────────────────────────────────────────────────────────────────
    if (exportFmt.value === 'svg') {
      const clone = svgEl.value.cloneNode(true)
      clone.setAttribute('xmlns','http://www.w3.org/2000/svg')
      const str  = new XMLSerializer().serializeToString(clone)
      downloadBlob(new Blob([str],{type:'image/svg+xml;charset=utf-8'}), name+'.svg')
      showExportModal.value = false; return
    }

    // ── PNG / JPEG ────────────────────────────────────────────────────────────
    if (exportFmt.value === 'png' || exportFmt.value === 'jpeg') {
      const canvas = await withFitView(svgToCanvas)
      const mime   = exportFmt.value === 'jpeg' ? 'image/jpeg' : 'image/png'
      const ext    = exportFmt.value === 'jpeg' ? '.jpg' : '.png'
      canvas.toBlob(b => { downloadBlob(b, name+ext); showExportModal.value=false }, mime, 0.95)
      return
    }

    // ── PDF ───────────────────────────────────────────────────────────────────
    if (exportFmt.value === 'pdf') {
      const canvas = await withFitView(svgToCanvas)
      const { jsPDF } = await import('jspdf')
      const doc = new jsPDF({ orientation: exportPdfOrient.value, unit:'mm', format: exportPdfSize.value })
      const pageW = doc.internal.pageSize.getWidth()
      const pageH = doc.internal.pageSize.getHeight()
      const margin = 10
      const availW = pageW - margin*2, availH = pageH - margin*2
      const ratio  = canvas.width / canvas.height
      let dW = availW, dH = dW / ratio
      if (dH > availH) { dH = availH; dW = dH * ratio }
      const x = margin + (availW - dW)/2
      const y = margin + (availH - dH)/2
      doc.addImage(canvas.toDataURL('image/png'), 'PNG', x, y, dW, dH)
      doc.save(name+'.pdf')
      showExportModal.value = false
    }
  } catch(err) {
    alert('Export impossible : ' + (err?.message ?? String(err)))
  } finally { exportWorking.value = false }
}

// ─── Auto-save ────────────────────────────────────────────────────────────────
let autoSaveTimer=null
watch([shapes,conns,groups],()=>{
  if (!currentDiagram.value)return
  clearTimeout(autoSaveTimer); autoSaveTimer=setTimeout(saveDiagram,3000)
},{deep:true})

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(()=>{ document.addEventListener('keydown',onKeydown); initHistory(); loadLib() })
onUnmounted(()=>{ document.removeEventListener('keydown',onKeydown); clearTimeout(autoSaveTimer) })

// ─── Template helpers ─────────────────────────────────────────────────────────
const STD_ANCHORS=['N','E','S','W']

const selBoxRect=computed(()=>{
  if (!selBox.value)return null
  const b=selBox.value
  return {x:Math.min(b.x1,b.x2),y:Math.min(b.y1,b.y2),w:Math.abs(b.x2-b.x1),h:Math.abs(b.y2-b.y1)}
})

const cursorStyle=computed(()=>{
  if (mode.value===MODE.PAN||mode.value===MODE.DRAG)return 'grabbing'
  if (mode.value===MODE.CONNECT)return 'crosshair'
  if (mode.value===MODE.ROTATE)return 'crosshair'
  return 'default'
})

function computeShapePath(s) {
  const {x,y,w,h,type}=s
  if (type==='diamond') { const cx=x+w/2,cy=y+h/2; return `M ${cx} ${y} L ${x+w} ${cy} L ${cx} ${y+h} L ${x} ${cy} Z` }
  if (type==='hexagon') { const r=Math.min(w,h)/2,cx=x+w/2,cy=y+h/2; return `M ${Array.from({length:6},(_,i)=>{const a=Math.PI/180*(60*i-30);return `${cx+r*Math.cos(a)},${cy+r*Math.sin(a)}`}).join(' L ')} Z` }
  if (type==='parallelogram') { const off=w*0.18; return `M ${x+off} ${y} L ${x+w} ${y} L ${x+w-off} ${y+h} L ${x} ${y+h} Z` }
  return null
}

function resizeHandles(s) {
  return [{key:'nw',x:s.x-4,y:s.y-4},{key:'ne',x:s.x+s.w-4,y:s.y-4},{key:'sw',x:s.x-4,y:s.y+s.h-4},{key:'se',x:s.x+s.w-4,y:s.y+s.h-4}]
}

// Rotation handle: circle positioned above shape center, rotated with shape
function rotateHandlePos(s) {
  const cx=s.x+s.w/2, cy=s.y+s.h/2
  const raw={x:cx, y:s.y-28}
  return (s.style?.rotation) ? rotatePoint(raw.x,raw.y,cx,cy,s.style.rotation) : raw
}

function shapeTransform(s) {
  const rot=s.style?.rotation||0
  if (!rot)return ''
  const cx=s.x+s.w/2, cy=s.y+s.h/2
  return `rotate(${rot} ${cx} ${cy})`
}

function tableRows(s) { return Array.from({length:s.data?.rows??3},(_,i)=>i) }
function tableCols(s) { return Array.from({length:s.data?.cols??3},(_,i)=>i) }
function updateTableSize(s) {
  push()
  const rows=s.data.rows||3,cols=s.data.cols||3
  s.data.cells=Array.from({length:rows},(_,ri)=>Array.from({length:cols},(_,ci)=>s.data.cells?.[ri]?.[ci]??''))
  commit()
}

const hoveredConn=ref(null)

// ─── Calques (Layers) ────────────────────────────────────────────────────────
const showLayers  = ref(false)
const layerDragId = ref(null)

const LAYER_ICONS = { rect:'▭', rounded:'▢', circle:'●', diamond:'◆', hexagon:'⬡', parallelogram:'▱', text:'T', table:'⊞', line:'╱', arc:'◑', av:'🎭', image:'🖼' }
function layerIcon(s)  { return LAYER_ICONS[s.type] ?? '■' }
function layerLabel(s) { return s.label?.trim() || s.type }

function moveLayerUp(id) {
  const i=shapes.value.findIndex(s=>s.id===id); if(i>=shapes.value.length-1)return
  push(); const a=[...shapes.value]; [a[i],a[i+1]]=[a[i+1],a[i]]; shapes.value=a; commit()
}
function moveLayerDown(id) {
  const i=shapes.value.findIndex(s=>s.id===id); if(i<=0)return
  push(); const a=[...shapes.value]; [a[i],a[i-1]]=[a[i-1],a[i]]; shapes.value=a; commit()
}
function moveToFront(id) {
  const i=shapes.value.findIndex(s=>s.id===id); if(i<0||i===shapes.value.length-1)return
  push(); const [s]=shapes.value.splice(i,1); shapes.value.push(s); commit()
}
function moveToBack(id) {
  const i=shapes.value.findIndex(s=>s.id===id); if(i<=0)return
  push(); const [s]=shapes.value.splice(i,1); shapes.value.unshift(s); commit()
}
function toggleHidden(id) {
  const s=shapes.value.find(s=>s.id===id); if(!s)return; push(); s.hidden=!s.hidden; commit()
}
function toggleLocked(id) {
  const s=shapes.value.find(s=>s.id===id); if(!s)return; push(); s.locked=!s.locked; commit()
}
function onLayerDragStart(e,id) { e.dataTransfer.effectAllowed='move'; layerDragId.value=id }
function onLayerDragOver(e)     { e.preventDefault(); e.dataTransfer.dropEffect='move' }
function onLayerDrop(targetId) {
  if(!layerDragId.value||layerDragId.value===targetId){layerDragId.value=null;return}
  push()
  const arr=[...shapes.value]
  const fi=arr.findIndex(s=>s.id===layerDragId.value)
  const ti=arr.findIndex(s=>s.id===targetId)
  if(fi<0||ti<0){layerDragId.value=null;return}
  const [rem]=arr.splice(fi,1); arr.splice(ti,0,rem)
  shapes.value=arr; commit(); layerDragId.value=null
}

// ─── Grille / Magnétisme ─────────────────────────────────────────────────────
const showGrid    = ref(true)
const snapEnabled = ref(false)
const gridSize    = ref(20)
function snap(v) { return snapEnabled.value ? Math.round(v/gridSize.value)*gridSize.value : v }
// Décalage de grille — modulo positif pour éviter les valeurs négatives JS
const gridOffX = computed(()=>{ const step=gridSize.value*zoom.value; return ((panX.value%step)+step)%step })
const gridOffY = computed(()=>{ const step=gridSize.value*zoom.value; return ((panY.value%step)+step)%step })
</script>

<template>
  <AppLayout title="Diagrammes">
    <div class="flex h-[calc(100vh-64px)] overflow-hidden bg-gray-100" style="margin:-1.5rem">

      <!-- ── LEFT SIDEBAR ── -->
      <div class="w-60 bg-white border-r border-gray-200 flex flex-col flex-shrink-0 overflow-hidden">
        <div class="p-3 border-b border-gray-100 flex items-center justify-between">
          <span class="font-semibold text-sm text-gray-800">Diagrammes</span>
          <button @click="showNewDiagram=!showNewDiagram"
            class="text-indigo-600 text-xl font-bold w-6 h-6 flex items-center justify-center rounded hover:bg-indigo-50">+</button>
        </div>

        <div v-if="showNewDiagram" class="p-3 border-b border-gray-100 bg-indigo-50">
          <input v-model="newName" type="text" placeholder="Nom…"
            class="w-full border rounded px-2 py-1.5 text-xs mb-2 outline-none focus:ring-1 focus:ring-indigo-400"/>
          <input v-model="newDesc" type="text" placeholder="Description (opt.)…"
            class="w-full border rounded px-2 py-1.5 text-xs mb-2 outline-none focus:ring-1 focus:ring-indigo-400"/>
          <div class="flex gap-2">
            <button @click="createDiagram" :disabled="saving||!newName.trim()"
              class="flex-1 bg-indigo-600 text-white text-xs py-1 rounded hover:bg-indigo-700 disabled:opacity-50">Créer</button>
            <button @click="showNewDiagram=false" class="text-xs text-gray-500 px-2">✕</button>
          </div>
        </div>

        <div class="h-36 overflow-y-auto border-b border-gray-100 flex-shrink-0">
          <div v-if="!localDiagrams.length" class="text-xs text-gray-400 text-center p-4">Aucun diagramme</div>
          <div v-for="d in localDiagrams" :key="d.id"
            @click="selectDiagram(d)"
            class="px-3 py-2 border-b border-gray-50 cursor-pointer group flex items-start justify-between hover:bg-indigo-50"
            :class="currentDiagram?.id===d.id?'bg-indigo-100':''">
            <div class="min-w-0 flex-1">
              <p class="text-xs font-semibold text-gray-800 truncate">{{ d.name }}</p>
              <p v-if="d.description" class="text-xs text-gray-400 truncate">{{ d.description }}</p>
            </div>
            <button @click.stop="deleteDiagram(d)"
              class="text-red-400 hover:text-red-600 opacity-0 group-hover:opacity-100 text-xs ml-1 mt-0.5">✕</button>
          </div>
        </div>

        <!-- Palette tabs -->
        <div class="flex border-b border-gray-100 flex-shrink-0">
          <button v-for="tab in [{k:'basic',l:'Formes'},{k:'av',l:'AV/Ciné'},{k:'library',l:'Bib.'}]" :key="tab.k"
            @click="sidebarTab=tab.k"
            class="flex-1 text-xs py-1.5 font-medium transition"
            :class="sidebarTab===tab.k?'border-b-2 border-indigo-600 text-indigo-600':'text-gray-500 hover:text-gray-700'">
            {{ tab.l }}
          </button>
        </div>

        <div class="flex-1 overflow-y-auto min-h-0 p-2">
          <!-- Basic shapes -->
          <div v-if="sidebarTab==='basic'" class="space-y-1">
            <div class="grid grid-cols-2 gap-1">
              <button v-for="sh in BASIC_SHAPES" :key="sh.type"
                @click="addShape(sh.type)" :disabled="!currentDiagram"
                class="text-xs border border-gray-200 rounded px-1 py-1.5 text-left hover:bg-indigo-50 hover:border-indigo-300 transition disabled:opacity-30 bg-white">
                {{ sh.label }}
              </button>
            </div>
            <hr class="my-2 border-gray-100"/>
            <p class="text-xs text-gray-400 font-semibold px-1 mb-1">Image</p>
            <button @click="triggerImgUpload" :disabled="!currentDiagram"
              class="w-full text-xs border border-dashed border-gray-300 rounded px-2 py-2 text-center hover:bg-blue-50 hover:border-blue-400 transition disabled:opacity-30 bg-white text-gray-600">
              📁 Importer une image
            </button>
            <input ref="imgInput" type="file" accept="image/*" class="hidden" @change="onImgUpload"/>
            <p class="text-xs text-gray-400 mt-1 px-1 leading-tight">Shift+clic sur image = ancrage libre · Clic droit ancrage = supprimer</p>
          </div>

          <!-- AV shapes -->
          <div v-if="sidebarTab==='av'">
            <p class="text-xs text-gray-400 font-semibold px-1 mb-2">Audiovisuel / Scénographie</p>
            <div class="space-y-1">
              <button v-for="av in AV_SHAPES" :key="av.key"
                @click="addAvShape(av.key)" :disabled="!currentDiagram"
                class="w-full text-left text-xs border border-gray-200 rounded px-2 py-1.5 hover:bg-slate-50 hover:border-slate-400 transition disabled:opacity-30 bg-white">
                {{ av.label }}
              </button>
            </div>
          </div>

          <!-- PNG library (serveur partagé) -->
          <div v-if="sidebarTab==='library'">
            <div class="flex items-center justify-between mb-2">
              <p class="text-xs text-gray-500 font-semibold">
                Bibliothèque partagée
                <span v-if="libLoading" class="text-gray-300 font-normal">…</span>
              </p>
              <button @click="libInput?.click()" :disabled="libUploading"
                class="text-xs bg-indigo-600 text-white px-2 py-1 rounded hover:bg-indigo-700 disabled:opacity-50">
                {{ libUploading ? '…' : '+ Import' }}
              </button>
            </div>
            <input ref="libInput" type="file" accept="image/*" multiple class="hidden" @change="importLibPng"/>

            <!-- En cours de chargement -->
            <div v-if="libLoading" class="text-xs text-gray-400 text-center py-6">Chargement…</div>

            <!-- Vide -->
            <div v-else-if="!shapeLib.length" class="text-xs text-gray-400 text-center py-6">
              Aucune forme.<br/>
              <span class="text-indigo-400">Importez des PNG/SVG.</span><br/>
              <span class="text-gray-300 text-xs">Partagé avec tous les postes.</span>
            </div>

            <!-- Grille d'images -->
            <div v-else class="grid grid-cols-2 gap-2">
              <div v-for="lib in shapeLib" :key="lib.id"
                class="relative group cursor-pointer border border-gray-200 rounded-lg overflow-hidden hover:border-indigo-400 transition bg-gray-50"
                @click="addImageShape(lib.src,lib.name)">
                <img :src="lib.src" :alt="lib.name" class="w-full h-14 object-contain p-1"/>
                <p class="text-xs text-center text-gray-600 pb-1 px-1 truncate">{{ lib.name }}</p>
                <button @click.stop="removeFromLib(lib.id)"
                  class="absolute top-0.5 right-0.5 text-red-400 hover:text-red-600 opacity-0 group-hover:opacity-100 text-xs bg-white rounded px-0.5">✕</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── MAIN CANVAS AREA ── -->
      <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        <!-- Toolbar -->
        <div class="h-10 bg-white border-b border-gray-200 flex items-center px-3 gap-2 flex-shrink-0 overflow-x-auto">
          <span class="text-sm font-semibold text-gray-700 truncate max-w-[150px] flex-shrink-0">
            {{ currentDiagram?.name ?? '— Choisir —' }}
          </span>
          <template v-if="currentDiagram">
            <button @click="saveDiagram" :disabled="saving"
              class="text-xs bg-indigo-600 text-white px-3 py-1 rounded hover:bg-indigo-700 disabled:opacity-50 flex-shrink-0">
              {{ saving?'…':'💾 Sauver' }}
            </button>
            <button @click="undo" title="Ctrl+Z" class="text-xs border bg-white px-2 py-1 rounded hover:bg-gray-50 flex-shrink-0">↩</button>
            <button @click="redo" title="Ctrl+Y" class="text-xs border bg-white px-2 py-1 rounded hover:bg-gray-50 flex-shrink-0">↪</button>
            <button @click="createGroup" title="Ctrl+G" class="text-xs border bg-white px-2 py-1 rounded hover:bg-gray-50 flex-shrink-0">⊡ Grouper</button>
            <button @click="duplicateSelected" title="Ctrl+D" class="text-xs border bg-white px-2 py-1 rounded hover:bg-gray-50 flex-shrink-0">⧉</button>
            <button @click="showExportModal=true" class="text-xs border bg-white px-2 py-1 rounded hover:bg-gray-50 flex-shrink-0">📥 Exporter…</button>
            <button @click="showEventModal=true"
              class="text-xs border border-indigo-200 text-indigo-600 bg-white px-2 py-1 rounded hover:bg-indigo-50 flex-shrink-0">🔗 Événement</button>
            <div class="h-4 border-l border-gray-200 mx-1 flex-shrink-0"/>
            <!-- Grille -->
            <button @click="showGrid=!showGrid" :title="showGrid?'Masquer la grille':'Afficher la grille'"
              class="text-xs border px-2 py-1 rounded flex-shrink-0 transition"
              :class="showGrid?'bg-gray-100 border-gray-400 text-gray-700':'bg-white border-gray-200 text-gray-400 hover:bg-gray-50'">⊞</button>
            <!-- Snap -->
            <button @click="snapEnabled=!snapEnabled" :title="snapEnabled?'Désactiver le magnétisme':'Activer le magnétisme'"
              class="text-xs border px-2 py-1 rounded flex-shrink-0 transition"
              :class="snapEnabled?'bg-indigo-600 text-white border-indigo-600':'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'">🧲 Snap</button>
            <input v-if="snapEnabled" v-model.number="gridSize" type="number" min="5" max="200" step="5"
              title="Taille de la grille (px)"
              class="w-14 text-xs border rounded px-1.5 py-1 outline-none flex-shrink-0 focus:ring-1 focus:ring-indigo-400"/>
            <div class="h-4 border-l border-gray-200 mx-1 flex-shrink-0"/>
            <!-- Calques -->
            <button @click="showLayers=!showLayers" :title="showLayers?'Fermer les calques':'Ouvrir le panneau des calques'"
              class="text-xs border px-2 py-1 rounded flex-shrink-0 transition"
              :class="showLayers?'bg-indigo-600 text-white border-indigo-600':'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'">≡ Calques</button>
          </template>
          <span class="ml-auto text-xs text-gray-400 flex-shrink-0 hidden xl:block">
            Alt+drag=pan · Molette=zoom · ⟳=rotation · 🧲=magnétisme · ≡=calques
          </span>
        </div>

        <!-- Canvas -->
        <div class="flex-1 relative overflow-hidden select-none bg-gray-50"
          :style="{cursor:cursorStyle}">

          <div v-if="!currentDiagram"
            class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 pointer-events-none">
            <span class="text-5xl mb-4">🗂</span>
            <p class="text-sm font-medium">Sélectionnez un diagramme</p>
          </div>

          <svg v-if="currentDiagram" ref="svgEl" class="w-full h-full"
            @mousedown="onSvgMousedown"
            @mousemove="onSvgMousemove"
            @mouseup="onSvgMouseup"
            @mouseleave="onSvgMouseup"
            @wheel.prevent="onSvgWheel">

            <defs>
              <marker id="arr-end"   markerWidth="10" markerHeight="7" refX="9"  refY="3.5" orient="auto">
                <polygon points="0 0,10 3.5,0 7" fill="#6B7280"/>
              </marker>
              <marker id="arr-start" markerWidth="10" markerHeight="7" refX="1"  refY="3.5" orient="auto-start-reverse">
                <polygon points="0 0,10 3.5,0 7" fill="#6B7280"/>
              </marker>
              <marker id="arr-line-end"   markerWidth="10" markerHeight="7" refX="9"  refY="3.5" orient="auto">
                <polygon points="0 0,10 3.5,0 7" fill="context-stroke"/>
              </marker>
              <marker id="arr-line-start" markerWidth="10" markerHeight="7" refX="1"  refY="3.5" orient="auto-start-reverse">
                <polygon points="0 0,10 3.5,0 7" fill="context-stroke"/>
              </marker>
              <pattern id="grid" :width="gridSize*zoom" :height="gridSize*zoom"
                patternUnits="userSpaceOnUse" :x="gridOffX" :y="gridOffY">
                <!-- Lignes de grille horizontale + verticale -->
                <line x1="0" :x2="gridSize*zoom" y1="0" y2="0" stroke="#cbd5e1" stroke-width="0.5"/>
                <line x1="0" x2="0" y1="0" :y2="gridSize*zoom" stroke="#cbd5e1" stroke-width="0.5"/>
              </pattern>
            </defs>

            <!-- Fond blanc + grille par-dessus -->
            <rect width="100%" height="100%" fill="#ffffff"/>
            <rect v-if="showGrid" width="100%" height="100%" fill="url(#grid)"/>

            <g :transform="`translate(${panX},${panY}) scale(${zoom})`">

              <!-- Groups -->
              <g v-for="grp in groups" :key="grp.id">
                <template v-if="groupBounds(grp).w>0">
                  <rect :x="groupBounds(grp).x" :y="groupBounds(grp).y"
                        :width="groupBounds(grp).w" :height="groupBounds(grp).h"
                        :fill="grp.style.fill" :stroke="isSelected(grp.id)?'#6366f1':grp.style.stroke"
                        stroke-dasharray="6,3" stroke-width="1.5" rx="8" class="cursor-move"
                        @mousedown.stop="onGroupMousedown($event,grp)"/>
                  <text :x="groupBounds(grp).x+8" :y="groupBounds(grp).y+14"
                        font-size="10" fill="#6366f1" font-weight="600" class="pointer-events-none">{{ grp.label }}</text>
                </template>
              </g>

              <!-- Connections -->
              <g v-for="c in conns" :key="c.id"
                @mouseenter="hoveredConn=c.id"
                @mouseleave="hoveredConn=null">
                <!-- Hitbox -->
                <path :d="connPathFull(c)" fill="none" stroke="transparent" stroke-width="14" class="cursor-pointer"
                      @mousedown.stop="onConnMousedown($event,c.id)"
                      @dblclick.stop="onConnDblclick($event,c.id)"/>
                <!-- Visible -->
                <path :d="connPathFull(c)" fill="none"
                      :stroke="isSelected(c.id)?'#6366f1':(c.style?.stroke||'#6B7280')"
                      :stroke-width="c.style?.strokeWidth||2"
                      :stroke-dasharray="c.style?.dash?'6,4':'none'"
                      :marker-end="(c.arrow==='end'||c.arrow==='both')?'url(#arr-end)':'none'"
                      :marker-start="(c.arrow==='start'||c.arrow==='both')?'url(#arr-start)':'none'"
                      class="pointer-events-none"/>
                <!-- Label -->
                <text v-if="c.label" :x="connMidpoint(c).x" :y="connMidpoint(c).y-6"
                      text-anchor="middle" font-size="10" fill="#4B5563" class="pointer-events-none select-none">
                  {{ c.label }}
                </text>

                <!-- Waypoint controls (hover/selected) -->
                <template v-if="hoveredConn===c.id||isSelected(c.id)">
                  <!-- Segment midpoints → insert waypoint -->
                  <circle v-for="(mid,i) in connSegmentMids(c)" :key="'mid'+i"
                          :cx="mid.x" :cy="mid.y" r="4"
                          fill="#6366f1" opacity="0.45" stroke="white" stroke-width="1"
                          style="cursor:copy" title="Cliquer pour ajouter un point de jonction"
                          @click.stop="onConnSegMidClick($event,c,mid)"/>
                  <!-- Existing waypoints -->
                  <g v-for="wp in (c.waypoints||[])" :key="wp.id">
                    <!-- Move handle (drag) -->
                    <circle :cx="wp.x" :cy="wp.y" r="6"
                            fill="white" stroke="#6366f1" stroke-width="2" style="cursor:move"
                            @mousedown.stop="onWayptMousedown($event,c,wp)"/>
                    <!-- Delete on right-click -->
                    <circle :cx="wp.x" :cy="wp.y" r="6" fill="transparent"
                            @contextmenu.prevent.stop="removeWaypoint(c,wp.id)"/>
                    <!-- New connection from this junction (green dot, top-right) -->
                    <circle :cx="wp.x+8" :cy="wp.y-8" r="5"
                            fill="#10b981" stroke="white" stroke-width="1.5" style="cursor:crosshair"
                            title="Démarrer une connexion depuis ce point"
                            @mousedown.stop="onWayptConnMousedown($event,c,wp)"/>
                    <text :x="wp.x+8" :y="wp.y-5" font-size="7" fill="white" text-anchor="middle"
                          class="pointer-events-none select-none">+</text>
                  </g>
                </template>
              </g>

              <!-- Connection preview -->
              <path v-if="mode==='connect'&&connectFrom"
                    :d="previewPath(connectFrom,connectCursor)"
                    fill="none" stroke="#6366f1" stroke-width="1.5" stroke-dasharray="5,3"
                    class="pointer-events-none"/>

              <!-- Shapes -->
              <g v-for="s in shapes" :key="s.id"
                 :opacity="s.hidden ? 0.07 : s.style.opacity"
                 :style="s.hidden ? 'pointer-events:none' : s.locked ? 'cursor:not-allowed' : 'cursor:move'"
                 @mousedown.stop="onShapeMousedown($event,s.id)"
                 @dblclick.stop="onShapeDblclick($event,s.id)"
                 @click="s.type==='image'?onImageClick($event,s):null"
                 @mouseenter="hovered=s.id"
                 @mouseleave="hovered=null">

                <!-- ── Line shape (no rotation transform, uses direct endpoints) ── -->
                <template v-if="s.type==='line'">
                  <!-- Invisible wide hitbox -->
                  <line :x1="s.data.x1" :y1="s.data.y1" :x2="s.data.x2" :y2="s.data.y2"
                        stroke="transparent" :stroke-width="Math.max(12,s.style.strokeWidth+8)"
                        stroke-linecap="round"/>
                  <!-- Visible line -->
                  <line :x1="s.data.x1" :y1="s.data.y1" :x2="s.data.x2" :y2="s.data.y2"
                        :stroke="isSelected(s.id)?'#6366f1':s.style.stroke"
                        :stroke-width="s.style.strokeWidth||2"
                        :stroke-dasharray="s.style.dash?'8,5':'none'"
                        stroke-linecap="round"
                        :marker-start="s.data.arrowStart?'url(#arr-line-start)':'none'"
                        :marker-end="s.data.arrowEnd?'url(#arr-line-end)':'none'"
                        class="pointer-events-none"/>
                  <!-- Endpoint handles (always shown for line, drag to reposition) -->
                  <circle :cx="s.data.x1" :cy="s.data.y1" r="5"
                          :fill="isSelected(s.id)?'#6366f1':'white'"
                          :stroke="isSelected(s.id)?'white':'#6366f1'"
                          stroke-width="2" style="cursor:crosshair"
                          @mousedown.stop="onLineEpMousedown($event,s.id,'start')"/>
                  <circle :cx="s.data.x2" :cy="s.data.y2" r="5"
                          :fill="isSelected(s.id)?'#6366f1':'white'"
                          :stroke="isSelected(s.id)?'white':'#6366f1'"
                          stroke-width="2" style="cursor:crosshair"
                          @mousedown.stop="onLineEpMousedown($event,s.id,'end')"/>
                  <!-- Connection anchors on hover -->
                  <template v-if="isHovered(s.id)||mode==='connect'">
                    <circle :cx="s.data.x1" :cy="s.data.y1" r="6"
                            fill="#6366f1" stroke="white" stroke-width="1.5" style="cursor:crosshair"
                            @mousedown.stop="onAnchorMousedown($event,s.id,'N')"/>
                    <circle :cx="(s.data.x1+s.data.x2)/2" :cy="(s.data.y1+s.data.y2)/2" r="5"
                            fill="#6366f1" stroke="white" stroke-width="1.5" style="cursor:crosshair"
                            @mousedown.stop="onAnchorMousedown($event,s.id,'E')"/>
                    <circle :cx="s.data.x2" :cy="s.data.y2" r="6"
                            fill="#6366f1" stroke="white" stroke-width="1.5" style="cursor:crosshair"
                            @mousedown.stop="onAnchorMousedown($event,s.id,'S')"/>
                  </template>
                </template>

                <!-- ── Shape body (with rotation transform) ── -->
                <g v-if="s.type!=='line'" :transform="shapeTransform(s)">
                  <!-- rect -->
                  <rect v-if="s.type==='rect'" :x="s.x" :y="s.y" :width="s.w" :height="s.h"
                        :fill="s.style.fill" :stroke="isSelected(s.id)?'#6366f1':s.style.stroke"
                        :stroke-width="isSelected(s.id)?2.5:s.style.strokeWidth"
                        :stroke-dasharray="s.style.dash?'6,4':'none'"/>
                  <!-- rounded -->
                  <rect v-else-if="s.type==='rounded'" :x="s.x" :y="s.y" :width="s.w" :height="s.h" rx="12" ry="12"
                        :fill="s.style.fill" :stroke="isSelected(s.id)?'#6366f1':s.style.stroke"
                        :stroke-width="isSelected(s.id)?2.5:s.style.strokeWidth"
                        :stroke-dasharray="s.style.dash?'6,4':'none'"/>
                  <!-- circle -->
                  <ellipse v-else-if="s.type==='circle'"
                        :cx="s.x+s.w/2" :cy="s.y+s.h/2" :rx="s.w/2" :ry="s.h/2"
                        :fill="s.style.fill" :stroke="isSelected(s.id)?'#6366f1':s.style.stroke"
                        :stroke-width="isSelected(s.id)?2.5:s.style.strokeWidth"
                        :stroke-dasharray="s.style.dash?'6,4':'none'"/>
                  <!-- text -->
                  <rect v-else-if="s.type==='text'" :x="s.x" :y="s.y" :width="s.w" :height="s.h"
                        fill="transparent" stroke="transparent"/>
                  <!-- path shapes -->
                  <path v-else-if="['diamond','hexagon','parallelogram'].includes(s.type)"
                        :d="computeShapePath(s)"
                        :fill="s.style.fill" :stroke="isSelected(s.id)?'#6366f1':s.style.stroke"
                        :stroke-width="isSelected(s.id)?2.5:s.style.strokeWidth"
                        :stroke-dasharray="s.style.dash?'6,4':'none'"/>
                  <!-- Arc / cercle / secteur -->
                  <path v-else-if="s.type==='arc'"
                        :d="buildArcPath(s)"
                        :fill="(s.data.sweep>=360||s.data.closed||s.data.innerRadius>0)?s.style.fill:'none'"
                        :stroke="isSelected(s.id)?'#6366f1':s.style.stroke"
                        :stroke-width="isSelected(s.id)?2.5:s.style.strokeWidth"
                        :stroke-dasharray="s.style.dash?'6,4':'none'"
                        stroke-linecap="round" stroke-linejoin="round"/>
                  <!-- AV shape -->
                  <g v-else-if="s.type==='av'">
                    <rect :x="s.x" :y="s.y" :width="s.w" :height="s.h"
                          :fill="s.style.fill" :stroke="isSelected(s.id)?'#6366f1':s.style.stroke"
                          :stroke-width="isSelected(s.id)?2:s.style.strokeWidth" rx="4"/>
                    <path :d="avPathScaled(s)" fill="none" :stroke="s.style.stroke"
                          stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                          class="pointer-events-none"/>
                  </g>
                  <!-- Image -->
                  <g v-else-if="s.type==='image'">
                    <image :x="s.x" :y="s.y" :width="s.w" :height="s.h"
                           :href="s.data.src" preserveAspectRatio="xMidYMid meet"/>
                    <rect :x="s.x" :y="s.y" :width="s.w" :height="s.h"
                          fill="transparent" :stroke="isSelected(s.id)?'#6366f1':'transparent'"
                          stroke-width="2" stroke-dasharray="4,3"/>
                  </g>
                  <!-- Table -->
                  <template v-if="s.type==='table'">
                    <rect :x="s.x" :y="s.y" :width="s.w" :height="s.h"
                          :fill="s.style.fill" :stroke="isSelected(s.id)?'#6366f1':s.style.stroke"
                          :stroke-width="isSelected(s.id)?2.5:s.style.strokeWidth"/>
                    <line v-for="ci in (s.data.cols||3)-1" :key="'cl'+ci"
                          :x1="s.x+ci*(s.w/(s.data.cols||3))" :y1="s.y"
                          :x2="s.x+ci*(s.w/(s.data.cols||3))" :y2="s.y+s.h"
                          :stroke="s.style.stroke" stroke-width="0.7"/>
                    <line v-for="ri in (s.data.rows||3)-1" :key="'rl'+ri"
                          :x1="s.x" :y1="s.y+ri*(s.h/(s.data.rows||3))"
                          :x2="s.x+s.w" :y2="s.y+ri*(s.h/(s.data.rows||3))"
                          :stroke="s.style.stroke" stroke-width="0.7"/>
                    <template v-for="ri in tableRows(s)" :key="'r'+ri">
                      <g v-for="ci in tableCols(s)" :key="'c'+ri+'-'+ci">
                        <rect :x="tableCellBounds(s,ri,ci).x" :y="tableCellBounds(s,ri,ci).y"
                              :width="tableCellBounds(s,ri,ci).w" :height="tableCellBounds(s,ri,ci).h"
                              fill="transparent" @dblclick.stop="onCellDblclick($event,s.id,ri,ci)"/>
                        <text v-if="s.data.cells?.[ri]?.[ci]"
                              :x="tableCellBounds(s,ri,ci).x+tableCellBounds(s,ri,ci).w/2"
                              :y="tableCellBounds(s,ri,ci).y+tableCellBounds(s,ri,ci).h/2+4"
                              text-anchor="middle" :font-size="s.style.fontSize||12"
                              :fill="s.style.fontColor" class="pointer-events-none select-none">
                          {{ s.data.cells[ri][ci] }}
                        </text>
                      </g>
                    </template>
                  </template>

                  <!-- Label -->
                  <text v-if="s.type!=='table'&&s.label"
                        :x="s.x+s.w/2"
                        :y="s.type==='image'?s.y+s.h+14:s.y+s.h/2+(s.style.fontSize||13)/3"
                        text-anchor="middle" :font-size="s.style.fontSize"
                        :fill="s.type==='image'?'#374151':s.style.fontColor"
                        :font-weight="s.style.bold?'bold':'normal'"
                        class="pointer-events-none select-none">
                    {{ s.label }}
                  </text>
                </g>

                <!-- ── Selection controls (NOT rotated — stay axis-aligned) ── -->
                <template v-if="isSelected(s.id)">
                  <!-- Resize handles -->
                  <rect v-for="h in resizeHandles(s)" :key="h.key"
                        :x="h.x" :y="h.y" width="8" height="8"
                        fill="white" stroke="#6366f1" stroke-width="1.5" rx="1"
                        style="cursor:nwse-resize"
                        @mousedown.stop="onResizeMousedown($event,s.id,h.key)"/>
                  <!-- Rotation handle + line -->
                  <line :x1="s.x+s.w/2" :y1="s.y" :x2="rotateHandlePos(s).x" :y2="rotateHandlePos(s).y"
                        stroke="#6366f1" stroke-width="1" stroke-dasharray="3,2" class="pointer-events-none"/>
                  <circle :cx="rotateHandlePos(s).x" :cy="rotateHandlePos(s).y" r="7"
                          fill="white" stroke="#6366f1" stroke-width="2" style="cursor:grab"
                          @mousedown.stop="onRotateMousedown($event,s.id)"/>
                  <text :x="rotateHandlePos(s).x" :y="rotateHandlePos(s).y+4"
                        text-anchor="middle" font-size="9" fill="#6366f1" class="pointer-events-none select-none">⟳</text>
                </template>

                <!-- ── Connection anchors (hover, anchors rotate with shape) ── -->
                <template v-if="(isHovered(s.id)||mode==='connect')&&!isSelected(s.id)||((isHovered(s.id)||mode==='connect')&&isSelected(s.id))">
                  <!-- Standard NSEW anchors (rotated with shape) -->
                  <circle v-for="dir in STD_ANCHORS" :key="dir"
                          :cx="getAnchor(s,dir).x" :cy="getAnchor(s,dir).y" r="5"
                          fill="#6366f1" stroke="white" stroke-width="1.5" style="cursor:crosshair"
                          @mousedown.stop="onAnchorMousedown($event,s.id,dir)"/>
                  <!-- Custom anchors on images -->
                  <template v-if="s.type==='image'">
                    <g v-for="ca in (s.data.customAnchors||[])" :key="ca.id">
                      <circle :cx="getAnchor(s,ca.id).x" :cy="getAnchor(s,ca.id).y" r="5"
                              fill="#10b981" stroke="white" stroke-width="1.5" style="cursor:crosshair"
                              @mousedown.stop="onAnchorMousedown($event,s.id,ca.id)"/>
                      <circle :cx="getAnchor(s,ca.id).x" :cy="getAnchor(s,ca.id).y" r="5"
                              fill="transparent"
                              @contextmenu.prevent.stop="removeCustomAnchor($event,s,ca.id)"/>
                    </g>
                  </template>
                </template>
              </g>

              <!-- Rubber-band -->
              <rect v-if="selBoxRect"
                    :x="selBoxRect.x" :y="selBoxRect.y"
                    :width="selBoxRect.w" :height="selBoxRect.h"
                    fill="rgba(99,102,241,0.08)" stroke="#6366f1"
                    stroke-width="1" stroke-dasharray="4,3" class="pointer-events-none"/>
            </g>
          </svg>

          <!-- Label editor overlay -->
          <div v-if="editingId" class="fixed z-50"
               :style="{left:editPos.x+'px',top:editPos.y+'px',width:editPos.w+'px',height:editPos.h+'px'}">
            <textarea ref="editTextArea" v-model="editingText"
              class="w-full h-full resize-none bg-white/90 border-2 border-indigo-400 rounded text-center text-sm outline-none p-1"
              @blur="commitEdit" @keydown.enter.exact.prevent="commitEdit" @keydown.escape="editingId=null"/>
          </div>

          <!-- Table cell editor overlay -->
          <div v-if="editingCell" class="fixed z-50"
               :style="{left:cellPos.x+'px',top:cellPos.y+'px',width:cellPos.w+'px',height:cellPos.h+'px'}">
            <input ref="cellInput" v-model="cellText"
              class="w-full h-full bg-white border-2 border-indigo-400 text-center text-xs outline-none px-1"
              @blur="commitCell" @keydown.enter.prevent="commitCell" @keydown.escape="editingCell=null"/>
          </div>
        </div>
      </div>

      <!-- ── LAYERS PANEL ── -->
      <div v-if="showLayers&&currentDiagram"
           class="w-52 bg-white border-l border-gray-200 flex-shrink-0 flex flex-col overflow-hidden">

        <!-- En-tête -->
        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between flex-shrink-0 bg-gray-50">
          <span class="text-xs font-semibold text-gray-700 uppercase tracking-wider">Calques</span>
          <span class="text-xs text-gray-400 font-mono">{{ shapes.length }}</span>
        </div>

        <!-- Actions sur la sélection -->
        <div v-if="selectedShapes.length===1" class="px-2 py-1.5 border-b border-gray-100 flex gap-1 flex-shrink-0">
          <button @click="moveToFront(selectedShapes[0].id)" title="Premier plan (dessus tout)"
            class="flex-1 text-xs border rounded py-0.5 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-600 transition">⬆⬆</button>
          <button @click="moveLayerUp(selectedShapes[0].id)" title="Monter d'un cran"
            class="flex-1 text-xs border rounded py-0.5 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-600 transition">↑</button>
          <button @click="moveLayerDown(selectedShapes[0].id)" title="Descendre d'un cran"
            class="flex-1 text-xs border rounded py-0.5 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-600 transition">↓</button>
          <button @click="moveToBack(selectedShapes[0].id)" title="Arrière-plan (dessous tout)"
            class="flex-1 text-xs border rounded py-0.5 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-600 transition">⬇⬇</button>
        </div>

        <!-- Liste des calques — du plus haut (premier plan) au plus bas -->
        <div class="flex-1 overflow-y-auto min-h-0">
          <div v-if="!shapes.length" class="text-xs text-gray-400 text-center py-10 px-3">
            Aucun élément.<br/>Ajoutez des formes depuis la palette.
          </div>

          <div v-for="s in [...shapes].reverse()" :key="s.id"
               class="flex items-center gap-1.5 px-2 py-1.5 border-b border-gray-50 group select-none transition-colors"
               :class="[
                 isSelected(s.id) ? 'bg-indigo-50 border-l-2 border-l-indigo-500' : 'hover:bg-gray-50',
                 s.locked ? 'bg-amber-50/60' : '',
                 layerDragId===s.id ? 'opacity-30' : '',
               ]"
               draggable="true"
               @dragstart="onLayerDragStart($event,s.id)"
               @dragover="onLayerDragOver"
               @drop.prevent="onLayerDrop(s.id)"
               @dragend="layerDragId=null"
               @click="setSel(s.id)">

            <!-- Poignée de glissement -->
            <span class="text-gray-300 cursor-grab text-base leading-none flex-shrink-0 hover:text-gray-500">⠿</span>

            <!-- Icône type -->
            <span class="text-sm leading-none flex-shrink-0 w-4 text-center">{{ layerIcon(s) }}</span>

            <!-- Nom / label -->
            <span class="flex-1 text-xs truncate min-w-0"
                  :class="[s.hidden ? 'text-gray-300 line-through italic' : s.locked ? 'text-amber-700 font-medium' : 'text-gray-700']"
                  :title="layerLabel(s)">
              {{ layerLabel(s) }}
            </span>

            <!-- Bouton afficher/masquer -->
            <button @click.stop="toggleHidden(s.id)"
                    :title="s.hidden ? 'Afficher' : 'Masquer'"
                    class="flex-shrink-0 w-5 h-5 flex items-center justify-center rounded text-xs transition"
                    :class="s.hidden
                      ? 'text-gray-300 hover:text-gray-500'
                      : 'text-gray-400 opacity-0 group-hover:opacity-100 hover:text-gray-600'">
              {{ s.hidden ? '🙈' : '👁' }}
            </button>

            <!-- Bouton verrouiller/déverrouiller -->
            <button @click.stop="toggleLocked(s.id)"
                    :title="s.locked ? 'Déverrouiller' : 'Verrouiller'"
                    class="flex-shrink-0 w-5 h-5 flex items-center justify-center rounded text-xs transition"
                    :class="s.locked
                      ? 'text-amber-500'
                      : 'text-gray-400 opacity-0 group-hover:opacity-100 hover:text-gray-600'">
              {{ s.locked ? '🔒' : '🔓' }}
            </button>
          </div>
        </div>

        <!-- Pied — légende -->
        <div class="px-3 py-2 border-t border-gray-100 bg-gray-50 flex-shrink-0">
          <p class="text-xs text-gray-400 leading-relaxed">
            Glisser pour réordonner · Clic = sélectionner<br/>
            👁 masquer · 🔒 verrouiller
          </p>
        </div>
      </div>

      <!-- ── RIGHT PROPERTIES PANEL ── -->
      <div v-if="showProps&&currentDiagram"
           class="w-52 bg-white border-l border-gray-200 flex-shrink-0 overflow-y-auto">
        <div class="p-3 space-y-3">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Propriétés</p>

          <!-- Line props (when only lines are selected) -->
          <template v-if="selectedShapes.length>=1&&selectedShapes.every(s=>s.type==='line')">
            <div><label class="block text-xs text-gray-500 mb-1">Couleur</label>
              <input type="color" v-model="propLineColor" @change="applyLineProps" class="w-full h-8 rounded cursor-pointer border border-gray-200"/></div>
            <div><label class="block text-xs text-gray-500 mb-1">Épaisseur ({{ propLineW }})</label>
              <input type="range" v-model.number="propLineW" min="0.5" max="16" step="0.5" @change="applyLineProps" class="w-full"/></div>
            <div><label class="block text-xs text-gray-500 mb-1">Opacité ({{ Math.round(propLineOpacity*100) }}%)</label>
              <input type="range" v-model.number="propLineOpacity" min="0.1" max="1" step="0.05" @change="applyLineProps" class="w-full"/></div>
            <div class="flex gap-3">
              <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                <input type="checkbox" v-model="propLineDash" @change="applyLineProps" class="rounded"/> Pointillés</label>
            </div>
            <hr class="border-gray-100"/>
            <p class="text-xs text-gray-500 font-medium">Flèches</p>
            <div class="flex gap-3">
              <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                <input type="checkbox" v-model="propLineArrowS" @change="applyLineProps" class="rounded"/> Début</label>
              <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                <input type="checkbox" v-model="propLineArrowE" @change="applyLineProps" class="rounded"/> Fin</label>
            </div>
          </template>

          <!-- Shape props (non-line shapes) -->
          <template v-if="selectedShapes.length>=1&&selectedShapes.some(s=>s.type!=='line')">
            <div><label class="block text-xs text-gray-500 mb-1">Remplissage</label>
              <input type="color" v-model="propFill" @change="applyShapeProps" class="w-full h-8 rounded cursor-pointer border border-gray-200"/></div>
            <div><label class="block text-xs text-gray-500 mb-1">Bordure</label>
              <input type="color" v-model="propStroke" @change="applyShapeProps" class="w-full h-8 rounded cursor-pointer border border-gray-200"/></div>
            <div><label class="block text-xs text-gray-500 mb-1">Épaisseur ({{ propStrokeW }})</label>
              <input type="range" v-model.number="propStrokeW" min="0.5" max="8" step="0.5" @change="applyShapeProps" class="w-full"/></div>
            <div><label class="block text-xs text-gray-500 mb-1">Couleur texte</label>
              <input type="color" v-model="propFontColor" @change="applyShapeProps" class="w-full h-8 rounded cursor-pointer border border-gray-200"/></div>
            <div><label class="block text-xs text-gray-500 mb-1">Police ({{ propFontSize }}px)</label>
              <input type="range" v-model.number="propFontSize" min="8" max="36" step="1" @change="applyShapeProps" class="w-full"/></div>
            <div><label class="block text-xs text-gray-500 mb-1">Opacité ({{ Math.round(propOpacity*100) }}%)</label>
              <input type="range" v-model.number="propOpacity" min="0.1" max="1" step="0.05" @change="applyShapeProps" class="w-full"/></div>
            <div class="flex gap-3">
              <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                <input type="checkbox" v-model="propDash" @change="applyShapeProps" class="rounded"/> Pointillés</label>
              <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                <input type="checkbox" v-model="propBold" @change="applyShapeProps" class="rounded"/> Gras</label>
            </div>

            <!-- Rotation -->
            <hr class="border-gray-100"/>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Rotation ({{ propRotation }}°)</label>
              <input type="range" v-model.number="propRotation" min="0" max="359" step="1" @input="applyShapeProps" class="w-full mb-1"/>
              <div class="flex gap-1">
                <button @click="snapRotation(-90)" class="flex-1 text-xs border rounded py-0.5 hover:bg-gray-50">-90°</button>
                <button @click="snapRotation(-15)" class="flex-1 text-xs border rounded py-0.5 hover:bg-gray-50">-15°</button>
                <button @click="propRotation=0;applyShapeProps()" class="flex-1 text-xs border rounded py-0.5 hover:bg-gray-50 text-indigo-600">0°</button>
                <button @click="snapRotation(15)" class="flex-1 text-xs border rounded py-0.5 hover:bg-gray-50">+15°</button>
                <button @click="snapRotation(90)" class="flex-1 text-xs border rounded py-0.5 hover:bg-gray-50">+90°</button>
              </div>
            </div>

            <!-- Arc geometry -->
            <template v-if="selectedShapes.length===1&&selectedShapes[0].type==='arc'">
              <hr class="border-gray-100"/>
              <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Géométrie</p>
              <!-- Présets -->
              <div>
                <label class="block text-xs text-gray-500 mb-1">Présets</label>
                <div class="grid grid-cols-2 gap-1">
                  <button v-for="p in ARC_PRESETS" :key="p.l"
                    @click="setArcPreset(p.sw,p.sa,p.cl,p.ir)"
                    class="text-xs border rounded px-1 py-1 text-left transition hover:bg-indigo-50 hover:border-indigo-300"
                    :class="arcSweep===p.sw&&arcStartAngle===p.sa&&arcInner===p.ir&&arcClosed===p.cl?'bg-indigo-100 border-indigo-400 text-indigo-700':''">
                    {{ p.l }}
                  </button>
                </div>
              </div>
              <!-- Angle de départ -->
              <div>
                <label class="block text-xs text-gray-500 mb-1">Début ({{ arcStartAngle }}°)</label>
                <input type="range" v-model.number="arcStartAngle" min="0" max="359" step="1" @input="applyArcProps" class="w-full"/>
                <div class="flex gap-1 mt-1">
                  <button v-for="a in [0,90,180,270]" :key="a" @click="arcStartAngle=a;applyArcProps()"
                    class="flex-1 text-xs border rounded py-0.5 hover:bg-gray-50"
                    :class="arcStartAngle===a?'bg-gray-100 font-medium':''">{{ a }}°</button>
                </div>
              </div>
              <!-- Étendue -->
              <div>
                <label class="block text-xs text-gray-500 mb-1">Étendue ({{ arcSweep }}°)</label>
                <input type="range" v-model.number="arcSweep" min="1" max="360" step="1" @input="applyArcProps" class="w-full"/>
                <div class="flex gap-1 mt-1">
                  <button v-for="sw in [90,180,270,360]" :key="sw" @click="arcSweep=sw;applyArcProps()"
                    class="flex-1 text-xs border rounded py-0.5 hover:bg-gray-50"
                    :class="arcSweep===sw?'bg-gray-100 font-medium':''">{{ sw }}°</button>
                </div>
              </div>
              <!-- Rayon intérieur (donut) -->
              <div>
                <label class="block text-xs text-gray-500 mb-1">Rayon int. ({{ arcInner }}%)</label>
                <input type="range" v-model.number="arcInner" min="0" max="95" step="5" @input="applyArcProps" class="w-full"/>
                <div class="flex gap-1 mt-1">
                  <button v-for="ir in [0,25,50,75]" :key="ir" @click="arcInner=ir;applyArcProps()"
                    class="flex-1 text-xs border rounded py-0.5 hover:bg-gray-50"
                    :class="arcInner===ir?'bg-gray-100 font-medium':''">{{ ir }}%</button>
                </div>
              </div>
              <!-- Fermé → secteur -->
              <div v-if="arcSweep<360&&arcInner===0">
                <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none">
                  <input type="checkbox" v-model="arcClosed" @change="applyArcProps" class="rounded"/>
                  Secteur (fermé vers centre)
                </label>
              </div>
            </template>

            <!-- Table size -->
            <template v-if="selectedShapes.length===1&&selectedShapes[0].type==='table'">
              <hr class="border-gray-100"/>
              <p class="text-xs font-semibold text-gray-500">Tableau</p>
              <div><label class="block text-xs text-gray-500 mb-1">Lignes</label>
                <input type="number" :value="selectedShapes[0].data.rows" min="1" max="20"
                  @change="e=>{selectedShapes[0].data.rows=+e.target.value;updateTableSize(selectedShapes[0])}"
                  class="w-full border rounded px-2 py-1 text-xs outline-none"/></div>
              <div><label class="block text-xs text-gray-500 mb-1">Colonnes</label>
                <input type="number" :value="selectedShapes[0].data.cols" min="1" max="20"
                  @change="e=>{selectedShapes[0].data.cols=+e.target.value;updateTableSize(selectedShapes[0])}"
                  class="w-full border rounded px-2 py-1 text-xs outline-none"/></div>
            </template>
          </template>

          <!-- Connection props -->
          <template v-if="selectedConns.length>=1">
            <div><label class="block text-xs text-gray-500 mb-1">Couleur du lien</label>
              <input type="color" v-model="propConnStroke" @change="applyConnProps" class="w-full h-8 rounded cursor-pointer border border-gray-200"/></div>
            <div><label class="block text-xs text-gray-500 mb-1">Épaisseur ({{ propConnW }})</label>
              <input type="range" v-model.number="propConnW" min="0.5" max="8" step="0.5" @change="applyConnProps" class="w-full"/></div>
            <div>
              <label class="block text-xs text-gray-500 mb-2">Sens de la flèche</label>
              <div class="grid grid-cols-2 gap-1">
                <button v-for="opt in [{v:'none',l:'— Sans'},{v:'end',l:'→ Fin'},{v:'start',l:'← Début'},{v:'both',l:'↔ Deux sens'}]"
                  :key="opt.v" @click="propArrow=opt.v;applyConnProps()"
                  class="text-xs border rounded px-1 py-1 transition"
                  :class="propArrow===opt.v?'bg-indigo-600 text-white border-indigo-600':'bg-white hover:bg-gray-50'">
                  {{ opt.l }}
                </button>
              </div>
            </div>
            <div class="flex gap-3">
              <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                <input type="checkbox" v-model="propDash" @change="applyConnProps" class="rounded"/> Pointillés</label>
              <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                <input type="checkbox" v-model="propStraight" @change="applyConnProps" class="rounded"/> Droit</label>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Event modal -->
    <Teleport to="body">
      <div v-if="showEventModal"
           class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
           @click.self="showEventModal=false">
        <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
          <h2 class="font-semibold text-gray-900 mb-4">Lier à un événement</h2>
          <select v-model="selectedEventId"
            class="w-full border rounded-lg px-3 py-2 text-sm mb-4 outline-none focus:ring-2 focus:ring-indigo-400">
            <option :value="null">— Choisir un événement —</option>
            <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.title }}</option>
          </select>
          <div class="flex gap-3 justify-end">
            <button @click="showEventModal=false"
              class="border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
            <button @click="linkEvent" :disabled="!selectedEventId"
              class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-40">Lier</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Export modal ─────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showExportModal"
           class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
           @click.self="showExportModal=false">
        <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-sm">
          <h2 class="font-semibold text-gray-900 mb-4">Exporter le diagramme</h2>

          <!-- Format selector -->
          <div class="flex gap-2 mb-4">
            <button v-for="fmt in [{v:'png',l:'PNG'},{v:'jpeg',l:'JPEG'},{v:'svg',l:'SVG'},{v:'pdf',l:'PDF'}]"
              :key="fmt.v"
              @click="exportFmt=fmt.v"
              class="flex-1 text-sm py-1.5 rounded border transition"
              :class="exportFmt===fmt.v ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'">
              {{ fmt.l }}
            </button>
          </div>

          <!-- Raster-only options -->
          <template v-if="exportFmt==='png'||exportFmt==='jpeg'">
            <label class="block text-xs font-medium text-gray-600 mb-1">Résolution</label>
            <div class="flex gap-2 mb-4">
              <button v-for="s in [1,2,3]" :key="s"
                @click="exportScale=s"
                class="flex-1 text-sm py-1.5 rounded border transition"
                :class="exportScale===s ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'">
                {{ s }}×
              </button>
            </div>
          </template>

          <!-- PDF-only options -->
          <template v-if="exportFmt==='pdf'">
            <label class="block text-xs font-medium text-gray-600 mb-1">Format de page</label>
            <select v-model="exportPdfSize" class="w-full border rounded-lg px-3 py-2 text-sm mb-3 outline-none focus:ring-2 focus:ring-indigo-400">
              <option value="a3">A3</option>
              <option value="a4">A4</option>
              <option value="a5">A5</option>
              <option value="letter">Letter</option>
              <option value="legal">Legal</option>
            </select>
            <label class="block text-xs font-medium text-gray-600 mb-1">Orientation</label>
            <div class="flex gap-2 mb-4">
              <button @click="exportPdfOrient='portrait'"
                class="flex-1 text-sm py-1.5 rounded border transition"
                :class="exportPdfOrient==='portrait' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'">Portrait</button>
              <button @click="exportPdfOrient='landscape'"
                class="flex-1 text-sm py-1.5 rounded border transition"
                :class="exportPdfOrient==='landscape' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'">Paysage</button>
            </div>
          </template>

          <!-- Fit-to-content toggle (all formats) -->
          <label class="flex items-center gap-2 text-sm text-gray-700 mb-5 cursor-pointer select-none">
            <input type="checkbox" v-model="exportFit" class="accent-indigo-600"/>
            Ajuster au contenu
          </label>

          <!-- Actions -->
          <div class="flex gap-3 justify-end">
            <button @click="showExportModal=false"
              class="border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
            <button @click="doExport" :disabled="exportWorking"
              class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-40 flex items-center gap-2">
              <svg v-if="exportWorking" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
              </svg>
              {{ exportWorking ? 'Export…' : '📥 Exporter' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

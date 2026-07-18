/**
 * NeuralCore — R3F port of design_files/landing/core.js.
 * Rotating neural sphere (34 fibonacci nodes + proximity links + halo)
 * over a drifting, linked particle field. Reacts to mouse (lerped) and
 * scroll (shrinks/dims through the hero). Fixed fullscreen, DPR ≤ 2,
 * static single frame under prefers-reduced-motion.
 */
import { useMemo, useRef } from "react";
import { Canvas, useFrame, useThree } from "@react-three/fiber";
import * as THREE from "three";

/* Shared mutable state, written by the page (Lenis scroll + pointer). */
export interface CoreState {
  mouse: { x: number; y: number; tx: number; ty: number };
  scrollN: number; // 0..1 through the hero region
}

const EMERALD_500 = new THREE.Color("#10b981");
const EMERALD_400 = new THREE.Color("#34d399");
const NODE_WHITE = new THREE.Color("#e6fff5");

const NODES = 34;
const PARTICLES = 110;

/** Soft radial sprite texture (replaces canvas radial gradients). */
function makeGlowTexture(): THREE.Texture {
  const size = 128;
  const canvas = document.createElement("canvas");
  canvas.width = canvas.height = size;
  const ctx = canvas.getContext("2d")!;
  const g = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
  g.addColorStop(0, "rgba(255,255,255,1)");
  g.addColorStop(0.35, "rgba(255,255,255,0.55)");
  g.addColorStop(1, "rgba(255,255,255,0)");
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, size, size);
  const tex = new THREE.CanvasTexture(canvas);
  tex.needsUpdate = true;
  return tex;
}

/* ---------------- neural core ---------------- */
function Core({ state, reduce }: { state: CoreState; reduce: boolean }) {
  const { viewport } = useThree();
  const group = useRef<THREE.Group>(null!);
  const linePos = useRef<THREE.BufferAttribute>(null!);
  const lines = useRef<THREE.LineSegments>(null!);
  const halo = useRef<THREE.Sprite>(null!);
  const glowTex = useMemo(makeGlowTexture, []);

  // fibonacci sphere, exactly as core.js
  const nodes = useMemo(
    () =>
      Array.from({ length: NODES }, (_, i) => {
        const phi = Math.acos(1 - (2 * (i + 0.5)) / NODES);
        const theta = Math.PI * (1 + Math.sqrt(5)) * i;
        return {
          base: new THREE.Vector3(
            Math.sin(phi) * Math.cos(theta),
            Math.sin(phi) * Math.sin(theta),
            Math.cos(phi)
          ),
          phase: Math.random() * Math.PI * 2,
        };
      }),
    []
  );

  const sprites = useRef<THREE.Sprite[]>([]);
  const maxSegments = (NODES * (NODES - 1)) / 2;
  const linePositions = useMemo(() => new Float32Array(maxSegments * 6), [maxSegments]);
  const world = useMemo(() => nodes.map(() => new THREE.Vector3()), [nodes]);

  useFrame(({ clock }) => {
    const t = clock.elapsedTime * 1000;
    const m = state.mouse;
    m.x += (m.tx - m.x) * 0.06;
    m.y += (m.ty - m.y) * 0.06;
    const s = state.scrollN;

    // radius & rotation — same formulas as core.js
    const R = Math.min(viewport.width, viewport.height) * (0.2 - s * 0.06);
    const rotY = t * 0.00022 + m.x * 0.8;
    const rotX = 0.5 + m.y * 0.5 + s * 0.6;

    group.current.position.set(
      (m.x - 0.5) * viewport.width * 0.05,
      -(m.y - 0.5) * viewport.height * 0.05 + viewport.height * 0.04,
      0
    );
    group.current.rotation.set(rotX, rotY, 0);

    // nodes: pulse size/opacity, collect world positions for links
    nodes.forEach((n, i) => {
      const sp = sprites.current[i];
      if (!sp) return;
      sp.position.copy(n.base).multiplyScalar(R);
      const pulse = 0.5 + 0.5 * Math.sin(t * 0.002 + n.phase);
      const rad = (1.4 + pulse * 2.4) * 0.02 * R; // pinpoint nodes, like core.js
      sp.scale.setScalar(rad);
      (sp.material as THREE.SpriteMaterial).opacity = (0.4 + pulse * 0.6) * (1 - s * 0.5);
      world[i].copy(sp.position);
    });

    // proximity connections (in rotated local space, like the 2D projection)
    let seg = 0;
    const max = (R * 0.82) ** 2;
    for (let i = 0; i < NODES; i++) {
      for (let j = i + 1; j < NODES; j++) {
        if (world[i].distanceToSquared(world[j]) < max) {
          linePositions.set([...world[i].toArray(), ...world[j].toArray()], seg * 6);
          seg++;
        }
      }
    }
    lines.current.geometry.setDrawRange(0, seg * 2);
    linePos.current.needsUpdate = true;
    (lines.current.material as THREE.LineBasicMaterial).opacity = 0.34 * (1 - s * 0.6);

    // halo
    halo.current.scale.setScalar(R * 5);
    (halo.current.material as THREE.SpriteMaterial).opacity = 0.14 * (1 - s * 0.7);
  });

  // static frame under reduced motion: R3F frameloop="demand" renders once.
  void reduce;

  return (
    <group ref={group}>
      <sprite ref={halo}>
        <spriteMaterial
          map={glowTex}
          color={EMERALD_500}
          transparent
          opacity={0.14}
          depthWrite={false}
          blending={THREE.AdditiveBlending}
        />
      </sprite>
      <lineSegments ref={lines}>
        <bufferGeometry>
          <bufferAttribute
            ref={linePos}
            attach="attributes-position"
            args={[linePositions, 3]}
          />
        </bufferGeometry>
        <lineBasicMaterial
          color={EMERALD_400}
          transparent
          opacity={0.34}
          depthWrite={false}
          blending={THREE.AdditiveBlending}
        />
      </lineSegments>
      {nodes.map((_, i) => (
        <sprite key={i} ref={(el) => el && (sprites.current[i] = el)}>
          <spriteMaterial
            map={glowTex}
            color={NODE_WHITE}
            transparent
            depthWrite={false}
            blending={THREE.AdditiveBlending}
          />
        </sprite>
      ))}
    </group>
  );
}

/* ---------------- drifting particle field ---------------- */
function ParticleField({ state }: { state: CoreState }) {
  const { viewport } = useThree();
  const points = useRef<THREE.Points>(null!);
  const linkLines = useRef<THREE.LineSegments>(null!);
  const linkPos = useRef<THREE.BufferAttribute>(null!);
  const glowTex = useMemo(makeGlowTexture, []);

  const particles = useMemo(
    () =>
      Array.from({ length: PARTICLES }, () => ({
        x: (Math.random() - 0.5), // normalized -0.5..0.5, scaled to viewport later
        y: (Math.random() - 0.5),
        z: Math.random() * 0.8 + 0.2, // depth factor (speed + alpha)
        vx: (Math.random() - 0.5) * 0.00012,
        vy: (Math.random() - 0.5) * 0.00012,
      })),
    []
  );
  const positions = useMemo(() => new Float32Array(PARTICLES * 3), []);
  const maxLinks = 400;
  const linkPositions = useMemo(() => new Float32Array(maxLinks * 6), []);

  useFrame(() => {
    const m = state.mouse;
    const W = viewport.width;
    const H = viewport.height;
    for (let i = 0; i < PARTICLES; i++) {
      const p = particles[i];
      p.x += p.vx * p.z + (m.x - 0.5) * 0.00025 * p.z;
      p.y -= p.vy * p.z + (m.y - 0.5) * 0.00025 * p.z;
      if (p.x < -0.55) p.x += 1.1;
      if (p.x > 0.55) p.x -= 1.1;
      if (p.y < -0.55) p.y += 1.1;
      if (p.y > 0.55) p.y -= 1.1;
      positions[i * 3] = p.x * W;
      positions[i * 3 + 1] = p.y * H;
      positions[i * 3 + 2] = -2 + p.z; // slight depth spread behind the core
    }
    (points.current.geometry.attributes.position as THREE.BufferAttribute).needsUpdate = true;

    // subtle links between near particles
    const maxD2 = (W * 0.11) ** 2;
    let seg = 0;
    for (let i = 0; i < PARTICLES && seg < maxLinks; i++) {
      for (let j = i + 1; j < PARTICLES && seg < maxLinks; j++) {
        const dx = positions[i * 3] - positions[j * 3];
        const dy = positions[i * 3 + 1] - positions[j * 3 + 1];
        if (dx * dx + dy * dy < maxD2) {
          linkPositions.set(
            [
              positions[i * 3], positions[i * 3 + 1], positions[i * 3 + 2],
              positions[j * 3], positions[j * 3 + 1], positions[j * 3 + 2],
            ],
            seg * 6
          );
          seg++;
        }
      }
    }
    linkLines.current.geometry.setDrawRange(0, seg * 2);
    linkPos.current.needsUpdate = true;
  });

  return (
    <>
      <points ref={points}>
        <bufferGeometry>
          <bufferAttribute attach="attributes-position" args={[positions, 3]} />
        </bufferGeometry>
        <pointsMaterial
          map={glowTex}
          color={EMERALD_400}
          size={0.09}
          transparent
          opacity={0.22}
          depthWrite={false}
          sizeAttenuation
          blending={THREE.AdditiveBlending}
        />
      </points>
      <lineSegments ref={linkLines}>
        <bufferGeometry>
          <bufferAttribute ref={linkPos} attach="attributes-position" args={[linkPositions, 3]} />
        </bufferGeometry>
        <lineBasicMaterial
          color={EMERALD_500}
          transparent
          opacity={0.08}
          depthWrite={false}
          blending={THREE.AdditiveBlending}
        />
      </lineSegments>
    </>
  );
}

export function NeuralCore({ state }: { state: CoreState }) {
  const reduce =
    typeof window !== "undefined" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  return (
    <div className="core-canvas" aria-hidden>
      <Canvas
        dpr={[1, 2]}
        frameloop={reduce ? "demand" : "always"}
        camera={{ position: [0, 0, 8], fov: 50 }}
        gl={{ antialias: true, alpha: true }}
      >
        <ParticleField state={state} />
        <Core state={state} reduce={reduce} />
      </Canvas>
    </div>
  );
}

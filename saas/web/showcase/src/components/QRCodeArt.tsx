interface QRCodeArtProps {
  size?: number;
  seed?: number;
  className?: string;
  dark?: boolean;
}

// Deterministic pseudo-random generator
function seededRandom(seed: number) {
  let s = seed;
  return () => {
    s = (s * 9301 + 49297) % 233280;
    return s / 233280;
  };
}

// Finder pattern positions for a grid of gridSize x gridSize
const FINDER_AREAS = [
  { r: 0, c: 0 },
  { r: 0, c: -7 },
  { r: -7, c: 0 },
];

function isInFinderArea(row: number, col: number, gridSize: number): boolean {
  return FINDER_AREAS.some(({ r, c }) => {
    const baseR = r < 0 ? gridSize + r : r;
    const baseC = c < 0 ? gridSize + c : c;
    return (
      row >= baseR && row < baseR + 7 && col >= baseC && col < baseC + 7
    );
  });
}

function isFinderPattern(row: number, col: number, gridSize: number): boolean {
  for (const { r, c } of FINDER_AREAS) {
    const baseR = r < 0 ? gridSize + r : r;
    const baseC = c < 0 ? gridSize + c : c;
    if (row >= baseR && row < baseR + 7 && col >= baseC && col < baseC + 7) {
      const lr = row - baseR;
      const lc = col - baseC;
      // Outer ring (7x7 border)
      if (lr === 0 || lr === 6 || lc === 0 || lc === 6) return true;
      // Inner 3x3
      if (lr >= 2 && lr <= 4 && lc >= 2 && lc <= 4) return true;
      return false;
    }
  }
  return false;
}

export function QRCodeArt({
  size = 200,
  seed = 42,
  className = '',
  dark = false,
}: QRCodeArtProps) {
  const gridSize = 29;
  const cellSize = size / gridSize;
  const rand = seededRandom(seed);
  const cells: boolean[][] = [];

  for (let r = 0; r < gridSize; r++) {
    cells[r] = [];
    for (let c = 0; c < gridSize; c++) {
      if (isInFinderArea(r, c, gridSize)) {
        cells[r][c] = isFinderPattern(r, c, gridSize);
      } else {
        // Add some structure: alignment pattern near bottom-right
        const alignR = gridSize - 9;
        const alignC = gridSize - 9;
        const inAlign =
          r >= alignR && r < alignR + 5 && c >= alignC && c < alignC + 5;
        if (inAlign) {
          const lr = r - alignR;
          const lc = c - alignC;
          cells[r][c] =
            lr === 0 || lr === 4 || lc === 0 || lc === 4 || (lr === 2 && lc === 2);
        } else {
          // Timing patterns
          if (r === 6 || c === 6) {
            cells[r][c] = (r + c) % 2 === 0;
          } else {
            cells[r][c] = rand() > 0.5;
          }
        }
      }
    }
  }

  const color = dark ? '#ffffff' : '#0f172a';
  const bg = dark ? 'transparent' : '#ffffff';

  return (
    <div
      className={className}
      style={{ width: size, height: size, background: bg, position: 'relative' }}
    >
      <svg
        width={size}
        height={size}
        viewBox={`0 0 ${size} ${size}`}
        style={{ display: 'block' }}
      >
        {cells.map((row, r) =>
          row.map((on, c) =>
            on ? (
              <rect
                key={`${r}-${c}`}
                x={c * cellSize}
                y={r * cellSize}
                width={cellSize}
                height={cellSize}
                fill={color}
                rx={cellSize * 0.12}
              />
            ) : null
          )
        )}
      </svg>
    </div>
  );
}

function buildIconMap(context) {
  const map = {};
  context.keys().forEach((key) => {
    const normalized = key.replace('./', '').replace(/\.png$/i, '');
    map[normalized] = context(key);
  });
  return map;
}

const materialContext = require.context('@/assets/icons/materials', false, /\.png$/);
const materialIcons = buildIconMap(materialContext);

export function getMaterialIconSrc(code) {
  const normalized = String(code || '').replace(/_raw$/i, '');
  return materialIcons[normalized] || null;
}

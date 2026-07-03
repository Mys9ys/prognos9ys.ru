function buildIconMap(context) {
  const map = {};
  context.keys().forEach((key) => {
    const normalized = key.replace('./', '').replace(/\.png$/i, '');
    map[normalized] = context(key);
  });
  return map;
}

const productContext = require.context('@/assets/icons/craft/products', false, /\.png$/);
const recipeContext = require.context('@/assets/icons/craft/recipes', false, /\.png$/);

const productIcons = buildIconMap(productContext);
const recipeIcons = buildIconMap(recipeContext);

function normalizeProductCode(code) {
  return String(code || '').replace(/_raw$/i, '');
}

export function getCraftProductIconSrc(code) {
  const normalized = normalizeProductCode(code);
  return productIcons[normalized] || null;
}

export function getCraftRecipeIconSrc(recipeCode) {
  const normalized = String(recipeCode || '');
  return recipeIcons[normalized] || null;
}

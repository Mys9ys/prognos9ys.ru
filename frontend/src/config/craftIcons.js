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
  if (productIcons[normalized]) {
    return productIcons[normalized];
  }

  const caftanMatch = normalized.match(/^caftan_(basic|embroidered|grand)_/);
  if (caftanMatch) {
    return productIcons[`caftan_${caftanMatch[1]}`] || null;
  }

  return null;
}

export function getCraftRecipeIconSrc(recipeCode) {
  const normalized = String(recipeCode || '');
  if (recipeIcons[normalized]) {
    return recipeIcons[normalized];
  }

  const caftanRecipeMatch = normalized.match(/^recipe_caftan_(basic|embroidered|grand)_/);
  if (caftanRecipeMatch) {
    return recipeIcons[`recipe_caftan_${caftanRecipeMatch[1]}`] || null;
  }

  const craftStageMatch = normalized.match(/^recipe_craft_(craft_[a-z_]+)$/);
  if (craftStageMatch) {
    return productIcons[craftStageMatch[1]] || null;
  }

  const refineMatch = normalized.match(/^recipe_refine_fine_/);
  if (refineMatch) {
    const fineCode = normalized.replace(/^recipe_refine_/, '');
    return productIcons[fineCode] || null;
  }

  return null;
}

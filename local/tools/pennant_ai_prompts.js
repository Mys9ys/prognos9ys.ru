/**
 * Промпты для AI-генерации вымпелов сборных ЧМ-26.
 * Генерация: Cursor GenerateImage с reference_image_paths:
 *   - frontend/src/assets/icons/pennants/pennant_chm2026.png
 *   - frontend/src/assets/icons/pennants/pennant_site.png
 *
 * Постобработка (опционально, если нужен trim без Photoshop):
 *   node local/tools/process_pennant_ai_png.mjs --pilot
 *
 * Импорт сырых файлов (шахматка, полный размер):
 *   node local/tools/import_pennant_ai_raw.mjs
 */

export const PENNANT_AI_REFERENCES = [
  'frontend/src/assets/icons/pennants/pennant_chm2026.png',
  'frontend/src/assets/icons/pennants/pennant_site.png',
];

const FRAME_PROMPT = `Premium 3D mobile game collectible soccer pennant banner. EXACT same frame style as reference images: dark green horizontal hanging rod with gold spherical end caps and center gold ring, ornate double gold metallic border, classic shield pennant shape with pointed bottom tip and small center notch.`;

const CENTER_PROMPT = `Center only: one simplified golden FIFA World Cup trophy, small clean 3D gold trophy without text, no ribbon, no confetti, no extra symbols, no letters. Trophy height is exactly one-third of inner pennant field height, centered. No country name, no FIFA text.`;

const TAIL_PROMPT = `Soft studio lighting, crisp game asset. Isolated on transparent checkerboard background (light gray and white squares pattern). Single object, front view, high detail.`;

/** @type {Record<string, {label:string, flagPrompt:string}>} */
export const WC26_PENNANT_FLAG_PROMPTS = {
  aus: { label: 'Australia', flagPrompt: 'Australian flag as realistic fabric canvas (blue field, Union Jack canton, Commonwealth Star, Southern Cross)' },
  aut: { label: 'Austria', flagPrompt: 'Austrian flag as realistic fabric canvas (red, white, red horizontal stripes)' },
  alg: { label: 'Algeria', flagPrompt: 'Algerian flag as realistic fabric canvas (green and white vertical halves with red crescent and star)' },
  eng: { label: 'England', flagPrompt: 'England flag as realistic fabric canvas (white field with red Saint George cross)' },
  arg: { label: 'Argentina', flagPrompt: 'Argentine flag as realistic fabric canvas (light blue, white, light blue stripes with sun emblem)' },
  bel: { label: 'Belgium', flagPrompt: 'Belgian flag as realistic fabric canvas (black, yellow, red vertical stripes)' },
  bih: { label: 'Bosnia and Herzegovina', flagPrompt: 'Bosnia flag as realistic fabric canvas (blue field with yellow triangle and white stars)' },
  bra: { label: 'Brazil', flagPrompt: 'Brazilian flag as realistic fabric canvas (green field, yellow diamond, blue globe with stars)' },
  hai: { label: 'Haiti', flagPrompt: 'Haiti flag as realistic fabric canvas (blue and red horizontal halves with coat of arms)' },
  gha: { label: 'Ghana', flagPrompt: 'Ghana flag as realistic fabric canvas (red, yellow, green stripes with black star)' },
  ger: { label: 'Germany', flagPrompt: 'German flag as realistic fabric canvas (black, red, gold horizontal stripes)' },
  cod: { label: 'DR Congo', flagPrompt: 'DR Congo flag as realistic fabric canvas (sky blue field with yellow star and red diagonal stripe)' },
  egy: { label: 'Egypt', flagPrompt: 'Egyptian flag as realistic fabric canvas (red, white, black horizontal stripes with eagle emblem)' },
  jor: { label: 'Jordan', flagPrompt: 'Jordan flag as realistic fabric canvas (black, white, green horizontal stripes with red triangle and star)' },
  irq: { label: 'Iraq', flagPrompt: 'Iraq flag as realistic fabric canvas (red, white, black horizontal stripes with green Kufic script)' },
  irn: { label: 'Iran', flagPrompt: 'Iran flag as realistic fabric canvas (green, white, red horizontal stripes with emblem)' },
  esp: { label: 'Spain', flagPrompt: 'Spanish flag as realistic fabric canvas (red, yellow, red horizontal stripes with coat of arms)' },
  cpv: { label: 'Cape Verde', flagPrompt: 'Cape Verde flag as realistic fabric canvas (blue field with white and red stripes and stars)' },
  can: { label: 'Canada', flagPrompt: 'Canadian flag as realistic fabric canvas (red sides, white center with red maple leaf)' },
  qat: { label: 'Qatar', flagPrompt: 'Qatar flag as realistic fabric canvas (maroon field with white serrated band)' },
  col: { label: 'Colombia', flagPrompt: 'Colombian flag as realistic fabric canvas (yellow, blue, red horizontal stripes)' },
  civ: { label: "Côte d'Ivoire", flagPrompt: "Ivory Coast flag as realistic fabric canvas (orange, white, green vertical stripes)" },
  cuw: { label: 'Curaçao', flagPrompt: 'Curaçao flag as realistic fabric canvas (blue field with yellow stripe and two white stars)' },
  mar: { label: 'Morocco', flagPrompt: 'Morocco flag as realistic fabric canvas (red field with green pentagram)' },
  mex: { label: 'Mexico', flagPrompt: 'Mexican flag as realistic fabric canvas (green, white, red vertical stripes with eagle emblem)' },
  ned: { label: 'Netherlands', flagPrompt: 'Dutch flag as realistic fabric canvas (red, white, blue horizontal stripes)' },
  nzl: { label: 'New Zealand', flagPrompt: 'New Zealand flag as realistic fabric canvas (blue field with Union Jack and Southern Cross)' },
  nor: { label: 'Norway', flagPrompt: 'Norwegian flag as realistic fabric canvas (red field with blue cross outlined in white)' },
  pan: { label: 'Panama', flagPrompt: 'Panama flag as realistic fabric canvas (quartered white and red with blue and red stars)' },
  par: { label: 'Paraguay', flagPrompt: 'Paraguay flag as realistic fabric canvas (red, white, blue horizontal stripes with emblem)' },
  por: { label: 'Portugal', flagPrompt: 'Portuguese flag as realistic fabric canvas (green and red vertical halves with coat of arms)' },
  ksa: { label: 'Saudi Arabia', flagPrompt: 'Saudi Arabia flag as realistic fabric canvas (green field with white shahada and sword)' },
  usa: { label: 'United States', flagPrompt: 'United States flag as realistic fabric canvas (stars and stripes)' },
  sen: { label: 'Senegal', flagPrompt: 'Senegal flag as realistic fabric canvas (green, yellow, red vertical stripes with green star)' },
  tun: { label: 'Tunisia', flagPrompt: 'Tunisia flag as realistic fabric canvas (red field with white circle, crescent and star)' },
  tur: { label: 'Turkey', flagPrompt: 'Turkish flag as realistic fabric canvas (red field with white crescent and star)' },
  uzb: { label: 'Uzbekistan', flagPrompt: 'Uzbekistan flag as realistic fabric canvas (blue, white, green stripes with crescent and stars)' },
  uru: { label: 'Uruguay', flagPrompt: 'Uruguay flag as realistic fabric canvas (white and blue stripes with sun emblem)' },
  fra: { label: 'France', flagPrompt: 'French flag as realistic fabric canvas (vertical blue, white, red tricolor stripes)' },
  cro: { label: 'Croatia', flagPrompt: 'Croatian flag as realistic fabric canvas (red, white, blue horizontal stripes with coat of arms)' },
  cze: { label: 'Czechia', flagPrompt: 'Czech flag as realistic fabric canvas (white and red horizontal halves with blue triangle)' },
  sui: { label: 'Switzerland', flagPrompt: 'Swiss flag as realistic fabric canvas (red field with white cross)' },
  swe: { label: 'Sweden', flagPrompt: 'Swedish flag as realistic fabric canvas (blue field with yellow cross)' },
  sco: { label: 'Scotland', flagPrompt: 'Scotland flag as realistic fabric canvas (blue field with white saltire cross)' },
  ecu: { label: 'Ecuador', flagPrompt: 'Ecuador flag as realistic fabric canvas (yellow, blue, red horizontal stripes with coat of arms)' },
  kor: { label: 'South Korea', flagPrompt: 'South Korea flag as realistic fabric canvas (white field with red and blue yin-yang and trigrams)' },
  rsa: { label: 'South Africa', flagPrompt: 'South Africa flag as realistic fabric canvas (green Y-shape with black, yellow, red, blue, white)' },
  jpn: { label: 'Japan', flagPrompt: 'Japan flag as realistic fabric canvas (white field with red hinomaru circle)' },
};

export function buildPennantAiPrompt(slug) {
  const row = WC26_PENNANT_FLAG_PROMPTS[slug];
  if (!row) throw new Error(`unknown slug: ${slug}`);
  return [
    FRAME_PROMPT,
    `Inner field: ${row.flagPrompt}, subtle cloth folds.`,
    CENTER_PROMPT,
    TAIL_PROMPT,
    `Country: ${row.label}.`,
  ].join(' ');
}

export const PILOT_SLUGS = ['bra', 'fra', 'usa', 'jpn', 'sco'];

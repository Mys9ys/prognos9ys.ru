/**
 * Промпты для AI-генерации шарфов сборных ЧМ-26 (Premium Matchday v2).
 * Генерация: Cursor GenerateImage с reference_image_paths:
 *   - local/tools/output/scarfs/pilot/scarf_wc26_arg_premium_final_v1.png
 *
 * v2: центр — только EN название страны, БЕЗ кубка/эмблемы под текстом.
 * Фон: шахматка для вырезания в Photoshop.
 *
 * Выход: local/tools/output/scarfs/wc26_v2/scarf_wc26_{slug}_v2.png
 */

export const SCARF_AI_REFERENCE =
  'local/tools/output/scarfs/pilot/scarf_wc26_arg_premium_final_v1.png';

const SCARF_STYLE = `Premium Matchday football scarf, high-end collectible render, isolated on transparent-checkerboard background (light gray and white squares pattern, for Photoshop cutout), no crop, full scarf visible.

Style and composition (strict, match reference scarf layout):
- elegant upward arc scarf shape with two hanging ends
- thick ornate braided gold border along all edges
- realistic knitted fabric texture, premium 3D lighting, sharp details
- long tassels/fringe on both ends in team colors
- center: full ENGLISH team name in large embossed metallic gold serif letters
- IMPORTANT: do NOT place any World Cup symbol/trophy/logo in the center under the team name (no center cup, no center emblem duplication)
- left end: national symbol/emblem of the team (clean premium interpretation)
- right end: stylized "26" in team flag colors + ONE small golden world cup trophy integrated only here
- no FIFA text anywhere
- no extra labels, no watermarks, no mockup background, no stadium
- keep checkerboard background visible around entire scarf
- deliver as single PNG image look (full object, not trimmed)`;

/** @type {Record<string, {nameEn:string, styleCues:string, leftSymbol:string, right26:string}>} */
export const WC26_SCARF_PROMPTS = {
  aus: { nameEn: 'AUSTRALIA', styleCues: 'green and gold with Southern Cross stars motif', leftSymbol: 'kangaroo / Socceroos crest inspired emblem', right26: 'green and gold' },
  aut: { nameEn: 'AUSTRIA', styleCues: 'red, white, red horizontal bands', leftSymbol: 'Austrian eagle emblem', right26: 'red and white' },
  alg: { nameEn: 'ALGERIA', styleCues: 'green and white halves with red crescent and star', leftSymbol: 'crescent and star national emblem', right26: 'green, white, red' },
  eng: { nameEn: 'ENGLAND', styleCues: 'white with red Saint George cross', leftSymbol: 'Three Lions crest', right26: 'white and red' },
  arg: { nameEn: 'ARGENTINA', styleCues: 'sky blue / white / sky blue bands, subtle Sun of May motif', leftSymbol: 'Sun of May / AFA-inspired motif', right26: 'sky blue and white with gold accents' },
  bel: { nameEn: 'BELGIUM', styleCues: 'black, yellow, red vertical tricolor', leftSymbol: 'Belgian lion / Red Devils crest', right26: 'black, yellow, red' },
  bih: { nameEn: 'BOSNIA', styleCues: 'blue field with yellow triangle and white stars', leftSymbol: 'Bosnia national crest with stars', right26: 'blue and yellow' },
  bra: { nameEn: 'BRAZIL', styleCues: 'green base with yellow diamond and blue circle references', leftSymbol: 'CBF-inspired crest with five stars', right26: 'green, yellow, blue' },
  hai: { nameEn: 'HAITI', styleCues: 'blue and red horizontal halves with coat of arms', leftSymbol: 'Haiti coat of arms palm tree and cannons', right26: 'blue and red' },
  gha: { nameEn: 'GHANA', styleCues: 'red, yellow, green stripes with black star', leftSymbol: 'Black Star of Ghana', right26: 'red, yellow, green' },
  ger: { nameEn: 'GERMANY', styleCues: 'black, red, gold horizontal stripes', leftSymbol: 'German eagle / DFB crest', right26: 'black, red, gold' },
  cod: { nameEn: 'DR CONGO', styleCues: 'sky blue with yellow star and red diagonal stripe', leftSymbol: 'leopard head national emblem', right26: 'blue, yellow, red' },
  egy: { nameEn: 'EGYPT', styleCues: 'red, white, black horizontal stripes with eagle', leftSymbol: 'Egyptian golden eagle', right26: 'red, white, black' },
  jor: { nameEn: 'JORDAN', styleCues: 'black, white, green stripes with red triangle and star', leftSymbol: 'seven-pointed star and crown emblem', right26: 'black, white, green, red' },
  irq: { nameEn: 'IRAQ', styleCues: 'red, white, black horizontal stripes with green script', leftSymbol: 'Iraq eagle emblem', right26: 'red, white, black, green' },
  irn: { nameEn: 'IRAN', styleCues: 'green, white, red horizontal stripes with emblem', leftSymbol: 'Iran national emblem lion and sun stylized', right26: 'green, white, red' },
  esp: { nameEn: 'SPAIN', styleCues: 'red, yellow, red horizontal stripes', leftSymbol: 'Spanish coat of arms / RFEF crest', right26: 'red and yellow' },
  cpv: { nameEn: 'CAPE VERDE', styleCues: 'blue with white and red stripes and stars', leftSymbol: 'ten stars circle emblem', right26: 'blue, white, red' },
  can: { nameEn: 'CANADA', styleCues: 'red sides, white center with maple leaf', leftSymbol: 'red maple leaf emblem', right26: 'red and white' },
  qat: { nameEn: 'QATAR', styleCues: 'maroon field with white serrated band', leftSymbol: 'Qatar national emblem dhow and swords', right26: 'maroon and white' },
  col: { nameEn: 'COLOMBIA', styleCues: 'yellow, blue, red horizontal stripes', leftSymbol: 'Colombia coat of arms condor', right26: 'yellow, blue, red' },
  civ: { nameEn: "COTE D'IVOIRE", styleCues: 'orange, white, green vertical stripes', leftSymbol: 'Ivory Coast elephant national emblem', right26: 'orange, white, green' },
  cuw: { nameEn: 'CURACAO', styleCues: 'blue with yellow stripe and two white stars', leftSymbol: 'two stars island emblem', right26: 'blue and yellow' },
  mar: { nameEn: 'MOROCCO', styleCues: 'red field with green pentagram', leftSymbol: 'Morocco pentagram seal', right26: 'red and green' },
  mex: { nameEn: 'MEXICO', styleCues: 'green, white, red vertical tricolor', leftSymbol: 'Mexican golden eagle on cactus crest', right26: 'green, white, red' },
  ned: { nameEn: 'NETHERLANDS', styleCues: 'red, white, blue horizontal stripes', leftSymbol: 'Dutch lion / KNVB crest', right26: 'red, white, blue' },
  nzl: { nameEn: 'NEW ZEALAND', styleCues: 'blue with Union Jack and Southern Cross', leftSymbol: 'silver fern emblem', right26: 'blue and white' },
  nor: { nameEn: 'NORWAY', styleCues: 'red with blue cross outlined in white', leftSymbol: 'Norwegian lion crest', right26: 'red, white, blue' },
  pan: { nameEn: 'PANAMA', styleCues: 'quartered white and red with blue and red stars', leftSymbol: 'Panama national coat of arms', right26: 'red, white, blue' },
  par: { nameEn: 'PARAGUAY', styleCues: 'red, white, blue horizontal stripes', leftSymbol: 'Paraguay coat of arms lion and star', right26: 'red, white, blue' },
  por: { nameEn: 'PORTUGAL', styleCues: 'green and red vertical halves with coat of arms', leftSymbol: 'Portuguese armillary sphere crest', right26: 'green and red' },
  ksa: { nameEn: 'SAUDI ARABIA', styleCues: 'green field with white shahada and sword', leftSymbol: 'palm tree and crossed swords emblem', right26: 'green and white' },
  usa: { nameEn: 'UNITED STATES', styleCues: 'stars and stripes red white blue', leftSymbol: 'US Soccer crest with stars', right26: 'red, white, blue' },
  sen: { nameEn: 'SENEGAL', styleCues: 'green, yellow, red vertical stripes with green star', leftSymbol: 'Senegal lion national emblem', right26: 'green, yellow, red' },
  tun: { nameEn: 'TUNISIA', styleCues: 'red with white circle, crescent and star', leftSymbol: 'Tunisia crescent star and shield', right26: 'red and white' },
  tur: { nameEn: 'TURKEY', styleCues: 'red with white crescent and star', leftSymbol: 'Turkish crescent and star emblem', right26: 'red and white' },
  uzb: { nameEn: 'UZBEKISTAN', styleCues: 'blue, white, green stripes with crescent and stars', leftSymbol: 'Uzbekistan bird sun emblem', right26: 'blue, white, green' },
  uru: { nameEn: 'URUGUAY', styleCues: 'white and blue stripes with sun emblem', leftSymbol: 'Sun of May Uruguay crest', right26: 'white and blue' },
  fra: { nameEn: 'FRANCE', styleCues: 'vertical tricolor blue / white / red', leftSymbol: 'Gallic rooster', right26: 'blue, white, red' },
  cro: { nameEn: 'CROATIA', styleCues: 'red, white, blue horizontal stripes with checkerboard crest', leftSymbol: 'Croatian checkerboard šahovnica', right26: 'red, white, blue' },
  cze: { nameEn: 'CZECHIA', styleCues: 'white and red with blue triangle', leftSymbol: 'Czech lion emblem', right26: 'white, red, blue' },
  sui: { nameEn: 'SWITZERLAND', styleCues: 'red field with white cross', leftSymbol: 'Swiss cross shield emblem', right26: 'red and white' },
  swe: { nameEn: 'SWEDEN', styleCues: 'blue field with yellow cross', leftSymbol: 'Swedish three crowns emblem', right26: 'blue and yellow' },
  sco: { nameEn: 'SCOTLAND', styleCues: 'navy blue with white saltire cross', leftSymbol: 'Scottish lion rampant / Tartan Army crest', right26: 'navy blue and white' },
  ecu: { nameEn: 'ECUADOR', styleCues: 'yellow, blue, red horizontal stripes', leftSymbol: 'Ecuador condor coat of arms', right26: 'yellow, blue, red' },
  kor: { nameEn: 'SOUTH KOREA', styleCues: 'white with red and blue yin-yang and trigrams', leftSymbol: 'Taegeuk national emblem', right26: 'red, white, blue' },
  rsa: { nameEn: 'SOUTH AFRICA', styleCues: 'green Y-shape with black, yellow, red, blue, white', leftSymbol: 'Protea flower national emblem', right26: 'green, yellow, red, blue' },
  jpn: { nameEn: 'JAPAN', styleCues: 'white base with red sun disc motif', leftSymbol: 'chrysanthemum / imperial seal inspired motif', right26: 'white and red' },
};

export const WC26_SCARF_SLUGS = Object.keys(WC26_SCARF_PROMPTS);

export function buildScarfAiPrompt(slug) {
  const row = WC26_SCARF_PROMPTS[slug];
  if (!row) throw new Error(`unknown slug: ${slug}`);
  return [
    SCARF_STYLE,
    `Team name (center text): ${row.nameEn}`,
    `Country style cues: ${row.styleCues}`,
    `Left symbol: ${row.leftSymbol}`,
    `Right 26 colors: ${row.right26}`,
    `Match reference scarf composition but use this team's colors and symbols. v2: NO trophy in center.`,
  ].join('\n');
}

export function scarfOutputFilename(slug) {
  return `scarf_wc26_${slug}_v2.png`;
}

/** Вкладки инвентаря (синхрон с ExchangeCatalogConfig на бэкенде). */
export const INVENTORY_TABS = [
  { id: 'all', label: 'Все' },
  { id: 'chest', label: 'Сундуки' },
  { id: 'premium_scroll', label: 'Премиум' },
  { id: 'loot', label: 'ККИ' },
  { id: 'souvenir', label: 'Сувениры' },
  { id: 'cups', label: 'Кубки' },
  { id: 'recipe', label: 'Рецепты' },
  { id: 'xp_bank', label: 'Банки XP' },
  { id: 'cert', label: 'Лицензии' },
  { id: 'material', label: 'Материалы' },
  { id: 'equipment', label: 'Экип' },
];

export function resolveInventoryTab(item, tabId = 'all') {
  if (!item || tabId === 'all') {
    return true;
  }

  const code = String(item.code || item.packCode || '');
  const category = String(item.category || '');
  const pool = String(item.pool || '');

  if (tabId === 'chest') {
    return Boolean(item.icon?.includes('chest') || pool === 'wc26' || pool === 'rpl' || pool === 'achievement' || pool === 'level' || category === 'chest');
  }
  if (tabId === 'premium_scroll') {
    return category === 'premium' || String(item.field || '').startsWith('premium_');
  }
  if (tabId === 'loot') {
    return category === 'pack' && !code.includes('pennant') && !code.includes('scarf');
  }
  if (tabId === 'souvenir') {
    return category === 'pennant' || category === 'scarf'
      || String(item.id || '').startsWith('pennant_')
      || (category === 'pack' && (code.includes('pennant') || code.includes('scarf')));
  }
  if (tabId === 'cups') {
    return category === 'season_cup' || String(item.id || '').startsWith('season_cup_');
  }
  if (tabId === 'recipe') {
    return category === 'recipe';
  }
  if (tabId === 'xp_bank') {
    return category === 'xp_bank';
  }
  if (tabId === 'cert') {
    return category === 'cert';
  }
  if (tabId === 'material') {
    return category === 'material';
  }
  if (tabId === 'equipment') {
    return category === 'equipment';
  }

  return true;
}

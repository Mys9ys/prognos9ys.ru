export function countNewTreasuryOffers(shop) {
  if (!shop?.shop_open) {
    return 0;
  }

  const raw = shop.offers || {};
  let count = 0;

  for (const offer of Object.values(raw)) {
    if (offer?.available && !offer?.bought) {
      count += 1;
    }
  }

  return count;
}

export function treasuryShopMatchesEvent(shop, eventId) {
  if (!shop?.event_id || eventId == null || eventId === '') {
    return false;
  }

  return String(shop.event_id) === String(eventId);
}

export function listTreasuryOffers(shop) {
  const raw = shop?.offers || {};

  return Object.values(raw).sort((a, b) => {
    const milestoneDiff = Number(a?.milestone || 0) - Number(b?.milestone || 0);
    if (milestoneDiff !== 0) {
      return milestoneDiff;
    }

    const order = ['prognobaks_chest', 'rublius_chest', 'premium_1d'];
    const aIndex = order.indexOf(a?.base_key || a?.key || '');
    const bIndex = order.indexOf(b?.base_key || b?.key || '');

    return (aIndex === -1 ? 99 : aIndex) - (bIndex === -1 ? 99 : bIndex);
  });
}

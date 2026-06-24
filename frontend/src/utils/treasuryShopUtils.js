const OFFER_KEYS = ['prognobaks_chest', 'rublius_chest', 'premium_1d'];

export function countNewTreasuryOffers(shop) {
  if (!shop?.shop_open || !shop?.active_milestone) {
    return 0;
  }

  const raw = shop.offers || {};
  let count = 0;

  for (const key of OFFER_KEYS) {
    const offer = raw[key];
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

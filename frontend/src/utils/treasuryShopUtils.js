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

export function countNewTreasuryOffersForMilestone(shop, milestone) {
  if (!shop?.shop_open || !milestone) {
    return 0;
  }

  const target = Number(milestone);
  let count = 0;

  for (const offer of Object.values(shop.offers || {})) {
    if (Number(offer?.milestone || 0) !== target) {
      continue;
    }
    if (offer?.available && !offer?.bought) {
      count += 1;
    }
  }

  return count;
}

export function listTreasuryMilestones(shop) {
  if (Array.isArray(shop?.milestones) && shop.milestones.length) {
    return shop.milestones.map(Number).filter((n) => n > 0);
  }

  const set = new Set();
  for (const offer of Object.values(shop?.offers || {})) {
    const m = Number(offer?.milestone || 0);
    if (m > 0) {
      set.add(m);
    }
  }

  return Array.from(set).sort((a, b) => a - b);
}

export function listTreasuryOffersForMilestone(shop, milestone) {
  const target = Number(milestone || 0);

  return listTreasuryOffers(shop).filter((offer) => Number(offer?.milestone || 0) === target);
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

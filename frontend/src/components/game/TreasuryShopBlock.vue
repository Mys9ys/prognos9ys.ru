<template>
  <div class="treasury_shop" v-if="visible">
    <div class="shop_header" @click="expanded = !expanded">
      <span class="title">
        <AppIcon name="chest_wc2026" :size="18" />
        Лавка казны
        <span class="new_badge" v-if="newOffersCount > 0 && !expanded">{{ newOffersCount }}</span>
      </span>
      <span class="toggle">{{ expanded ? '−' : '+' }}</span>
    </div>

    <div class="shop_body" v-if="expanded">
      <PreLoader v-if="loading" />
      <template v-else>
        <div class="msg error" v-if="error">{{ error }}</div>
        <div class="msg ok" v-if="message">{{ message }}</div>

        <div class="shop_meta" v-if="shop">
          <span>Матчей с результатом: <strong>{{ shop.current_tour || '—' }}</strong></span>
        </div>

        <div class="shop_closed" v-if="shop && !shop.shop_open">
          Лавка откроется с {{ firstMilestone }}-го матча с результатом.
        </div>

        <template v-else-if="shop && milestones.length">
          <div class="milestone_tabs">
            <button
                v-for="milestone in milestones"
                :key="milestone"
                type="button"
                class="milestone_tab"
                :class="{ active: selectedMilestone === milestone }"
                @click="selectedMilestone = milestone"
            >
              №{{ milestone }}
              <span
                  v-if="countNewTreasuryOffersForMilestone(shop, milestone) > 0"
                  class="tab_badge"
              >{{ countNewTreasuryOffersForMilestone(shop, milestone) }}</span>
            </button>
          </div>

          <div class="shop_closed" v-if="!activeOffers.length">
            На матче №{{ selectedMilestone }} всё куплено.
          </div>

          <div class="offers" v-else>
            <button
                v-for="offer in activeOffers"
                :key="offer.key"
                type="button"
                class="offer_btn"
                :class="{ bought: offer.bought, disabled: offer.bought || !offer.available }"
                :disabled="buying || offer.bought || !offer.available"
                @click="onBuy(offer)"
            >
              <span v-if="offer.emoji" class="offer_emoji">{{ offer.emoji }}</span>
              <AppIcon v-else name="chest_wc2026" :size="20" />
              <span class="offer_title">{{ offer.label }}</span>
              <span class="offer_price">
                {{ offer.price }}
                <AppIcon :name="offer.currency === 'rublius' ? 'rublius' : 'prognobak'" :size="14" />
              </span>
              <span class="offer_state">{{ offerLabel(offer) }}</span>
            </button>
          </div>
        </template>

        <div class="shop_closed" v-else-if="shop">
          Сейчас нет предложений на открытых этапах.
        </div>

        <div class="shop_hint" v-if="shop?.next_milestone">
          Следующая волна — с {{ shop.next_milestone }} матча с результатом.
        </div>
      </template>
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import PreLoader from '@/components/main/PreLoader';
import AppIcon from '@/components/ui/AppIcon.vue';
import { apiActions } from '@/api/bitrixClient';
import {
  countNewTreasuryOffers,
  countNewTreasuryOffersForMilestone,
  listTreasuryMilestones,
  listTreasuryOffersForMilestone,
  treasuryShopMatchesEvent,
} from '@/utils/treasuryShopUtils';

const FIRST_MILESTONE = 40;

export default {
  name: 'TreasuryShopBlock',
  components: { PreLoader, AppIcon },
  props: {
    eventId: {
      type: [String, Number],
      default: null,
    },
    defaultExpanded: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      expanded: this.defaultExpanded,
      loading: false,
      buying: false,
      loaded: false,
      shop: null,
      selectedMilestone: 0,
      error: '',
      message: '',
      firstMilestone: FIRST_MILESTONE,
      countNewTreasuryOffersForMilestone,
    };
  },
  computed: {
    ...mapState('auth', ['authData', 'userInfo']),
    visible() {
      if (!this.authData?.token) {
        return false;
      }

      if (this.eventId == null || this.eventId === '') {
        return true;
      }

      if (!this.loaded) {
        return true;
      }

      return treasuryShopMatchesEvent(this.shop, this.eventId);
    },
    newOffersCount() {
      return countNewTreasuryOffers(this.shop);
    },
    milestones() {
      return listTreasuryMilestones(this.shop);
    },
    activeOffers() {
      if (!this.selectedMilestone) {
        return [];
      }

      return listTreasuryOffersForMilestone(this.shop, this.selectedMilestone);
    },
  },
  watch: {
    visible: {
      immediate: true,
      handler(val) {
        if (val && !this.loaded) {
          this.loadShop();
        }
      },
    },
    shop: {
      deep: true,
      handler() {
        this.ensureSelectedMilestone();
      },
    },
  },
  methods: {
    ...mapActions({
      refreshGameInfo: 'auth/refreshGameInfo',
      showBulkLevelBanner: 'game/showBulkLevelBanner',
    }),

    ensureSelectedMilestone() {
      const list = this.milestones;
      if (!list.length) {
        this.selectedMilestone = 0;
        return;
      }

      if (list.includes(this.selectedMilestone)) {
        return;
      }

      const active = Number(this.shop?.active_milestone || 0);
      if (active && list.includes(active)) {
        this.selectedMilestone = active;
        return;
      }

      const withNew = list.filter((m) => countNewTreasuryOffersForMilestone(this.shop, m) > 0);
      this.selectedMilestone = withNew.length ? withNew[withNew.length - 1] : list[list.length - 1];
    },

    async loadShop() {
      if (!this.authData?.token) {
        return;
      }

      this.loading = true;
      this.error = '';
      try {
        const data = await apiActions.game.getTreasuryShop(this.authData.token);
        if (data?.status === 'ok') {
          this.shop = data.shop || null;
          this.loaded = true;
          this.ensureSelectedMilestone();
        }
      } catch (e) {
        this.error = e.message || 'Не удалось загрузить лавку';
      } finally {
        this.loading = false;
      }
    },

    offerLabel(offer) {
      if (offer.bought) {
        return 'куплено';
      }
      if (!offer.available) {
        return 'недоступно';
      }
      return 'купить';
    },

    isPremiumOffer(offer) {
      const key = String(offer?.base_key || offer?.key || '');
      return key === 'premium_1d' || key.endsWith('_premium_1d') || key.includes('premium');
    },

    async onBuy(offer) {
      if (!offer?.available || offer.bought) {
        return;
      }

      this.buying = true;
      this.error = '';
      this.message = '';
      try {
        const data = this.isPremiumOffer(offer)
          ? await apiActions.game.buyTreasuryPremium(
            this.authData.token,
            offer.key,
            0,
            offer.milestone || 0,
          )
          : await apiActions.game.buyTreasuryChest(
            this.authData.token,
            offer.currency,
            0,
            offer.milestone || 0,
          );

        if (data?.status === 'ok') {
          this.shop = data.shop || this.shop;
          this.ensureSelectedMilestone();
          this.message = this.isPremiumOffer(offer)
            ? `${offer.label} добавлен в инвентарь`
            : 'Сундук ЧМ-26 добавлен в сокровищницу';
          await this.refreshGameInfo();
          if (data?.level_up) {
            this.showBulkLevelBanner({
              oldLevel: data.old_level,
              newLevel: data.new_level,
              levelRewards: data.level_rewards || [],
            });
          }
        }
      } catch (e) {
        this.error = e.message || 'Не удалось совершить покупку';
      } finally {
        this.buying = false;
      }
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.treasury_shop {
  background: @DarkColorBG;
  color: @colorText;
  border-radius: 5px;
  margin: 8px 0;
  padding: 4px;
}

.shop_header {
  .shadow_inset;
  padding: 6px 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  user-select: none;

  .title {
    font-weight: 700;
    font-size: 14px;
    color: @orange;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .new_badge {
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 9px;
    background: @orange;
    color: @DarkColorBG;
    font-size: 11px;
    font-weight: 700;
    line-height: 18px;
    text-align: center;
  }

  .toggle {
    font-size: 18px;
    color: @orange;
  }
}

.shop_body {
  padding: 6px 8px 8px;
}

.shop_meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  font-size: 12px;
  margin-bottom: 8px;
  color: @colorBlur;

  strong {
    color: @yellow;
  }
}

.milestone_tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 8px;
}

.milestone_tab {
  position: relative;
  flex: 1 1 0;
  min-width: 52px;
  padding: 6px 8px;
  border: 1px solid fade(@colorBlur, 35%);
  border-radius: 4px;
  background: rgba(0, 0, 0, 0.2);
  color: @colorText;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;

  &.active {
    border-color: @orange;
    background: fade(@orange, 18%);
    color: @yellow;
  }

  .tab_badge {
    position: absolute;
    top: -5px;
    right: -4px;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 8px;
    background: @orange;
    color: @DarkColorBG;
    font-size: 10px;
    line-height: 16px;
    text-align: center;
  }
}

.shop_closed {
  font-size: 13px;
  color: @colorBlur;
  text-align: center;
  padding: 10px 4px;
}

.offers {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: center;
}

.offer_btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: 88px;
  padding: 8px 10px;
  border: 2px solid @orange;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.25);
  color: @colorText;
  cursor: pointer;

  &.bought,
  &.disabled {
    border-color: @colorBlur;
    opacity: 0.42;
    cursor: default;
    pointer-events: none;
    background: rgba(0, 0, 0, 0.12);
    filter: grayscale(0.35);

    .offer_title,
    .offer_state {
      color: fade(@colorBlur, 80%);
    }

    .offer_price {
      color: @colorBlur;
      font-weight: 500;
    }
  }

  &.bought {
    border-color: fade(@YesWrite, 45%);
  }

  &.disabled:not(.bought) {
    opacity: 0.45;
  }

  .offer_emoji {
    font-size: 22px;
    line-height: 1;
  }

  .offer_title {
    font-size: 10px;
    color: @colorBlur;
    text-align: center;
    line-height: 1.2;
    max-width: 88px;
  }

  .offer_price {
    font-weight: 700;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    color: @yellow;
  }

  .offer_state {
    font-size: 10px;
    color: @colorBlur;
    text-transform: uppercase;
  }
}

.shop_hint {
  margin-top: 8px;
  font-size: 10px;
  color: @colorBlur;
  text-align: center;
}

.msg {
  font-size: 12px;
  margin-bottom: 6px;
  &.error { color: #ff8a8a; }
  &.ok { color: @YesWrite; }
}
</style>

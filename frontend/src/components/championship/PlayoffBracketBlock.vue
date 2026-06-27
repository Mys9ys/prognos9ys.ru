<template>
  <div class="playoff_block" v-if="hasBracket">
    <div class="title_wrapper" v-if="!hideTitle">
      <span class="title">Плей-офф</span>
      <span class="scroll_hint" v-if="columns.length > 2">⟷ листайте</span>
    </div>
    <div class="scroll_hint_only" v-else-if="columns.length > 2">
      <span class="scroll_hint">⟷ листайте</span>
    </div>

    <div class="bracket_viewport">
      <div class="bracket_board" :style="{ minHeight: trackHeight + 'px' }">
        <template v-for="(column, colIndex) in columns" :key="column.key">
          <div class="bracket_column">
            <div class="col_header">{{ column.label }}</div>
            <div class="col_track" :style="{ height: trackHeight + 'px' }">
              <div
                v-if="column.thirdPlace"
                class="slot_wrap third_slot"
                :style="{ top: thirdPlaceTop(colIndex) + 'px' }"
              >
                <EventMatch
                  :match="normalizeMatch(column.thirdPlace)"
                  compact
                  bracket
                />
              </div>
              <div
                v-for="(slot, slotIndex) in column.slots"
                :key="`${column.key}-${slotIndex}`"
                class="slot_wrap"
                :style="{ top: slotTop(colIndex, slotIndex) + 'px' }"
              >
                <EventMatch
                  :match="normalizeMatch(slot)"
                  compact
                  bracket
                />
              </div>
            </div>
          </div>

          <div
            v-if="colIndex < columns.length - 1"
            class="bracket_wires"
            :style="{ height: trackHeight + 'px' }"
          >
            <svg
              class="wires_svg"
              :width="WIRE_WIDTH"
              :height="trackHeight"
              :viewBox="`0 0 ${WIRE_WIDTH} ${trackHeight}`"
            >
              <path
                v-for="(path, pathIndex) in connectorPaths(colIndex)"
                :key="`wire-${colIndex}-${pathIndex}`"
                :d="path"
                class="wire_path"
              />
            </svg>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script>
import EventMatch from '@/components/football/EventMatch.vue';

const MATCH_H = 56;
const UNIT = 60;
const WIRE_WIDTH = 20;
const THIRD_GAP = 10;

export default {
  name: 'PlayoffBracketBlock',
  components: { EventMatch },
  props: {
    bracket: {
      type: Object,
      default: null,
    },
    rounds: {
      type: Array,
      default: () => [],
    },
    eventId: {
      type: [String, Number],
      required: true,
    },
    hideTitle: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      WIRE_WIDTH,
    };
  },
  computed: {
    bracketData() {
      if (this.bracket?.columns?.length) {
        return this.bracket;
      }
      return this.bracketFromRounds;
    },
    parsedBracket() {
      const cols = (this.bracketData?.columns || []).map((col) => ({
        ...col,
        slots: [...(col.slots || [])],
      }));

      let thirdPlace = this.bracketData?.thirdPlace || null;

      cols.forEach((col) => {
        col.slots = col.slots.filter((slot) => {
          if (this.isThirdPlaceMatch(slot)) {
            if (!thirdPlace) {
              thirdPlace = slot;
            }
            return false;
          }
          return true;
        });
      });

      const columns = cols.filter((col) => col.slots.length > 0 || thirdPlace);

      columns.forEach((col, index) => {
        const isFinalCol = index === columns.length - 1;
        if (isFinalCol && thirdPlace) {
          col.label = 'Финал';
          col.thirdPlace = thirdPlace;
        }
        col.slots = this.sortBracketSlots(col.slots);
      });

      return {
        columns: columns.filter((col) => col.slots.length > 0),
      };
    },
    columns() {
      return this.parsedBracket.columns;
    },
    trackHeight() {
      let height = MATCH_H;

      this.columns.forEach((col, colIndex) => {
        if (!col.slots?.length) {
          return;
        }
        const lastIdx = col.slots.length - 1;
        height = Math.max(height, this.slotTop(colIndex, lastIdx) + MATCH_H + 4);

        if (col.thirdPlace) {
          height = Math.max(height, this.thirdPlaceTop(colIndex) + MATCH_H + 4);
        }
      });

      return height;
    },
    hasBracket() {
      return this.columns.length > 0;
    },
    bracketFromRounds() {
      if (!this.rounds?.length) {
        return null;
      }

      const allMatches = this.sortMatches(
        this.rounds.flatMap((tab) => tab.matches || [])
      );

      if (!allMatches.length) {
        return null;
      }

      const stageGroups = this.resolveStageGroups(allMatches);
      const columns = [];
      let thirdPlace = null;
      const lastIndex = stageGroups.length - 1;

      stageGroups.forEach((stage, index) => {
        let matches = [...stage.matches];
        let label = stage.label;

        const third = stage.matches.find((m) => this.isThirdPlaceMatch(m));
        if (third) {
          thirdPlace = third;
        }
        matches = matches.filter((m) => !this.isThirdPlaceMatch(m));

        if (!matches.length && index !== lastIndex) {
          return;
        }

        columns.push({
          key: `stage_${index}`,
          label: matches.length === 1 && index === lastIndex ? 'Финал' : label,
          slots: matches.length ? this.sortBracketSlots(matches) : [],
        });
      });

      if (thirdPlace && columns.length) {
        const lastCol = columns[columns.length - 1];
        lastCol.label = 'Финал';
        lastCol.thirdPlace = thirdPlace;
      }

      const filledColumns = columns.filter((col) => col.slots.length > 0);
      if (!filledColumns.length) {
        return null;
      }

      const baseSlots = this.normalizeSlotCount(filledColumns[0].slots.length);
      filledColumns.forEach((col, index) => {
        col.slotCount = Math.max(1, baseSlots / (2 ** index));
      });

      return { baseSlots, columns: filledColumns };
    },
  },
  methods: {
    normalizeMatch(match) {
      if (!match) {
        return null;
      }

      return {
        ...match,
        event: match.event || this.eventId,
        teams: match.teams || { home: {}, guest: {} },
        ratio: match.ratio || [],
        send_info: match.send_info || {},
      };
    },
    sortMatches(matches) {
      return this.sortBracketSlots(matches);
    },
    isThirdPlaceMatch(match) {
      const code = String(match?.bracket_code || '').trim().toUpperCase();
      return code === 'F3' || match?.card_title === '3-е место';
    },
    bracketCodeOrder(code) {
      const value = String(code || '').trim().toUpperCase();
      if (!value) {
        return 9999;
      }
      let m = value.match(/^(?:A|B)(\d+)$/);
      if (m) {
        return Number(m[1]);
      }
      m = value.match(/^QF(\d+)$/);
      if (m) {
        return Number(m[1]);
      }
      m = value.match(/^SF(\d+)$/);
      if (m) {
        return Number(m[1]);
      }
      m = value.match(/^LSF(\d+)$/);
      if (m) {
        return Number(m[1]);
      }
      if (value === 'F1') {
        return 1;
      }
      if (value === 'F3') {
        return 2;
      }
      return 9999;
    },
    sortBracketSlots(matches) {
      return [...matches].sort((a, b) => {
        const codeDiff = this.bracketCodeOrder(a?.bracket_code) - this.bracketCodeOrder(b?.bracket_code);
        if (codeDiff !== 0) {
          return codeDiff;
        }
        const stepDiff = (a.step || 0) - (b.step || 0);
        if (stepDiff !== 0) {
          return stepDiff;
        }
        return (a.number || 0) - (b.number || 0);
      });
    },
    normalizeSlotCount(count) {
      let size = 1;
      while (size < count) {
        size *= 2;
      }
      return Math.min(32, Math.max(1, size));
    },
    resolveStageGroups(matches) {
      const byRound = {};
      matches.forEach((match) => {
        const round = Number(match.round) || 0;
        if (!byRound[round]) {
          byRound[round] = [];
        }
        byRound[round].push(match);
      });

      const roundKeys = Object.keys(byRound).map(Number).sort((a, b) => a - b);
      if (roundKeys.length > 1) {
        return roundKeys.map((round) => ({
          label: this.labelByCount(byRound[round].length),
          matches: this.sortMatches(byRound[round]),
        }));
      }

      return this.splitIntoStages(this.sortMatches(matches));
    },
    splitIntoStages(matches) {
      const count = matches.length;
      if (count <= 1) {
        return [{ label: 'Финал', matches }];
      }

      const sizes = [];
      let remaining = count;
      [16, 8, 4, 2, 1].forEach((size) => {
        if (remaining >= size) {
          sizes.push(size);
          remaining -= size;
        }
      });
      if (remaining > 0) {
        sizes[0] = (sizes[0] || 0) + remaining;
      }

      const groups = [];
      let offset = 0;
      sizes.forEach((size) => {
        const chunk = matches.slice(offset, offset + size);
        if (chunk.length) {
          groups.push({
            label: this.labelByCount(chunk.length),
            matches: chunk,
          });
          offset += size;
        }
      });
      return groups;
    },
    labelByCount(count) {
      if (count >= 16) return '1/16';
      if (count >= 8) return '1/8';
      if (count >= 4) return '1/4';
      if (count >= 2) return '1/2';
      return 'Финал';
    },
    thirdPlaceTop(colIndex) {
      return Math.max(0, this.slotTop(colIndex, 0) - MATCH_H - THIRD_GAP);
    },
    slotTop(round, index) {
      return ((index * (2 ** (round + 1))) + (2 ** round) - 1) * UNIT / 2;
    },
    slotCenterY(round, index) {
      return this.slotTop(round, index) + MATCH_H / 2;
    },
    connectorPaths(colIndex) {
      const slots = this.columns[colIndex]?.slots || [];
      const paths = [];
      const xMid = WIRE_WIDTH / 2;
      const nextColIndex = colIndex + 1;

      for (let i = 0; i < slots.length; i += 2) {
        const y1 = this.slotCenterY(colIndex, i);
        const hasPair = i + 1 < slots.length;
        const y2 = hasPair ? this.slotCenterY(colIndex, i + 1) : y1;
        const yMid = hasPair ? (y1 + y2) / 2 : y1;
        const nextIdx = Math.floor(i / 2);
        const nextSlots = this.columns[nextColIndex]?.slots || [];
        const yNext = nextIdx < nextSlots.length
          ? this.slotCenterY(nextColIndex, nextIdx)
          : yMid;

        paths.push(`M 0 ${y1} L ${xMid} ${y1}`);
        if (hasPair) {
          paths.push(`M 0 ${y2} L ${xMid} ${y2}`);
          paths.push(`M ${xMid} ${y1} L ${xMid} ${y2}`);
        }
        paths.push(`M ${xMid} ${yMid} L ${WIRE_WIDTH} ${yMid}`);
        if (Math.abs(yMid - yNext) > 0.5) {
          paths.push(`M ${WIRE_WIDTH} ${yMid} L ${WIRE_WIDTH} ${yNext}`);
        }
      }

      return paths;
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

@line: fade(@colorText, 38%);

.playoff_block {
  margin-top: 10px;
  background: @DarkColorBG;
  padding: 4px;
  border-radius: 5px;
  color: @colorText;
}

.title_wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;

  .title {
    flex: 1;
    text-align: left;
    .shadow_inset;
    padding: 4px 6px;
  }

  .scroll_hint {
    font-size: 10px;
    opacity: 0.7;
    white-space: nowrap;
    padding-right: 4px;
  }
}

.scroll_hint_only {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 4px;

  .scroll_hint {
    font-size: 10px;
    opacity: 0.7;
    white-space: nowrap;
    padding-right: 4px;
  }
}

.bracket_viewport {
  overflow-x: auto;
  overflow-y: hidden;
  -webkit-overflow-scrolling: touch;
  padding-bottom: 4px;
  width: 100%;
}

.bracket_board {
  display: flex;
  flex-direction: row;
  flex-wrap: nowrap;
  align-items: flex-start;
  width: max-content;
  min-width: 100%;
  padding: 0 2px 4px;
}

.bracket_column {
  flex: 0 0 auto;
  width: 200px;
}

.col_header {
  text-align: center;
  font-size: 10px;
  font-weight: 600;
  margin-bottom: 6px;
  padding: 3px 4px;
  color: @football;
  .shadow_inset;
  white-space: nowrap;
}

.col_track {
  position: relative;
}

.slot_wrap {
  position: absolute;
  left: 0;
  width: 200px;
}

.bracket_wires {
  position: relative;
  flex: 0 0 20px;
  width: 20px;
  align-self: stretch;
}

.wires_svg {
  display: block;
  overflow: visible;
  position: absolute;
  top: 0;
  left: 0;
}

.wire_path {
  fill: none;
  stroke: @line;
  stroke-width: 1;
  vector-effect: non-scaling-stroke;
}
</style>

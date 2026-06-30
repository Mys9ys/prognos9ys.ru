<template>
  <article class="guide_body">
    <header class="guide_header">
      <router-link class="guide_back" to="/faq">← Все статьи</router-link>
      <h1 class="guide_title">{{ article.title }}</h1>
      <p class="guide_lead">{{ article.summary }}</p>
    </header>

    <section
      v-for="(block, index) in article.blocks"
      :key="index"
      class="guide_section"
      :class="'guide_section_' + block.type"
    >
      <template v-if="block.type === 'intro'">
        <p class="guide_p">{{ block.text }}</p>
      </template>

      <template v-else-if="block.type === 'steps'">
        <h2 v-if="block.title" class="guide_h2">{{ block.title }}</h2>
        <ol class="guide_ol">
          <li v-for="(step, stepIndex) in block.items" :key="stepIndex">{{ step }}</li>
        </ol>
      </template>

      <template v-else-if="block.type === 'bullets'">
        <h2 v-if="block.title" class="guide_h2">{{ block.title }}</h2>
        <ul class="guide_ul">
          <li v-for="(item, itemIndex) in block.items" :key="itemIndex">{{ item }}</li>
        </ul>
      </template>

      <template v-else-if="block.type === 'important'">
        <div class="guide_important">
          <h2 v-if="block.title" class="guide_h2">{{ block.title }}</h2>
          <ul class="guide_ul">
            <li v-for="(item, itemIndex) in block.items" :key="itemIndex">{{ item }}</li>
          </ul>
        </div>
      </template>

      <template v-else-if="block.type === 'where'">
        <div class="guide_where">
          <div class="guide_where_label">Где в приложении</div>
          <div class="guide_where_path">{{ block.path }}</div>
          <p v-if="block.hint" class="guide_where_hint">{{ block.hint }}</p>
          <figure v-if="screenshotUrl(block)" class="guide_figure">
            <img
              class="guide_img"
              :src="screenshotUrl(block)"
              :alt="block.hint || block.path"
              loading="lazy"
              @error="onScreenshotError(block)"
            >
          </figure>
        </div>
      </template>

      <template v-else-if="block.type === 'figure'">
        <figure class="guide_figure">
          <img
            class="guide_img"
            :src="resolveFigureSrc(block.src)"
            :alt="block.caption || article.title"
            loading="lazy"
          >
          <figcaption v-if="block.caption" class="guide_caption">{{ block.caption }}</figcaption>
        </figure>
      </template>

      <template v-else-if="block.type === 'table'">
        <h2 v-if="block.title" class="guide_h2">{{ block.title }}</h2>
        <p v-if="block.hint" class="guide_p guide_table_hint">{{ block.hint }}</p>
        <div class="guide_table_wrap">
          <table class="guide_table">
            <thead v-if="block.headers?.length">
              <tr>
                <th v-for="(head, headIndex) in block.headers" :key="headIndex">{{ head }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, rowIndex) in block.rows" :key="rowIndex">
                <td v-for="(cell, cellIndex) in row" :key="cellIndex">{{ cell }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <template v-else-if="block.type === 'related'">
        <div class="guide_related" v-if="relatedArticles(block.slugs).length">
          <h2 class="guide_h2">Читай дальше</h2>
          <div class="guide_related_links">
            <router-link
              v-for="related in relatedArticles(block.slugs)"
              :key="related.slug"
              class="guide_related_link"
              :to="'/faq/' + related.slug"
            >
              {{ related.title }}
            </router-link>
          </div>
        </div>
      </template>
    </section>
  </article>
</template>

<script>
import { getGuideArticle, guideScreenshotUrl } from '@/config/guideArticles';

export default {
  name: 'GuideArticleBody',
  props: {
    article: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      brokenScreenshots: {},
    };
  },
  methods: {
    screenshotUrl(block) {
      const key = block.screenshotKey || '';
      if (!key || this.brokenScreenshots[key]) {
        return '';
      }
      return guideScreenshotUrl(key);
    },

    onScreenshotError(block) {
      const key = block.screenshotKey || '';
      if (key) {
        this.brokenScreenshots = { ...this.brokenScreenshots, [key]: true };
      }
    },

    resolveFigureSrc(src) {
      if (!src) {
        return '';
      }
      if (typeof src === 'string') {
        return src;
      }
      return src.default || '';
    },

    relatedArticles(slugs) {
      const list = Array.isArray(slugs) ? slugs : [];
      return list
        .map((slug) => getGuideArticle(slug))
        .filter(Boolean);
    },
  },
};
</script>

<style lang="less" scoped>
@import "src/assets/css/variables.less";

.guide_body {
  text-align: left;
}

.guide_back {
  display: inline-block;
  font-size: 12px;
  color: @colorBlur;
  text-decoration: none;
  margin-bottom: 8px;

  &:hover {
    color: @orange;
  }
}

.guide_title {
  font-size: 18px;
  font-weight: 700;
  color: @colorText;
  margin: 0 0 6px;
  line-height: 1.25;
}

.guide_lead {
  font-size: 13px;
  color: @colorBlur;
  line-height: 1.45;
  margin: 0 0 14px;
}

.guide_section {
  margin-bottom: 14px;
}

.guide_h2 {
  font-size: 13px;
  font-weight: 700;
  color: @orange;
  margin: 0 0 6px;
}

.guide_p {
  font-size: 13px;
  color: @colorText;
  line-height: 1.5;
  margin: 0;
}

.guide_ol,
.guide_ul {
  margin: 0;
  padding-left: 18px;
  font-size: 13px;
  color: @colorText;
  line-height: 1.45;

  li + li {
    margin-top: 6px;
  }
}

.guide_important {
  padding: 10px;
  border-radius: 6px;
  background: fade(@orange, 12%);
  border: 1px solid fade(@orange, 35%);
}

.guide_where {
  padding: 10px;
  border-radius: 6px;
  background: fade(@DarkColorBG, 75%);
  border: 1px dashed fade(@colorBlur, 35%);
}

.guide_where_label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: @orange;
  margin-bottom: 4px;
}

.guide_where_path {
  font-size: 13px;
  font-weight: 700;
  color: @colorText;
  margin-bottom: 4px;
}

.guide_where_hint {
  font-size: 12px;
  color: @colorBlur;
  line-height: 1.4;
  margin: 0 0 8px;
}

.guide_figure {
  margin: 0;
}

.guide_img {
  display: block;
  width: 100%;
  max-width: 360px;
  margin: 0 auto;
  border-radius: 6px;
  border: 1px solid fade(@colorBlur, 25%);
  background: @darkbg;
}

.guide_caption {
  margin-top: 6px;
  font-size: 11px;
  color: @colorBlur;
  line-height: 1.35;
  text-align: center;
}

.guide_related_links {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.guide_table_hint {
  margin-bottom: 8px;
}

.guide_table_wrap {
  overflow-x: auto;
}

.guide_table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
  color: @colorText;

  th,
  td {
    border: 1px solid fade(@colorBlur, 25%);
    padding: 6px 8px;
    text-align: left;
    vertical-align: top;
    line-height: 1.35;
  }

  th {
    background: fade(@DarkColorBG, 90%);
    color: @orange;
    font-weight: 700;
  }

  tr:nth-child(even) td {
    background: fade(@DarkColorBG, 45%);
  }
}

.guide_related_link {
  display: block;
  padding: 8px 10px;
  border-radius: 4px;
  background: fade(@DarkColorBG, 80%);
  border: 1px solid fade(@colorBlur, 25%);
  color: @colorText;
  font-size: 12px;
  text-decoration: none;

  &:hover {
    border-color: fade(@orange, 50%);
    color: @YesWrite;
  }
}
</style>

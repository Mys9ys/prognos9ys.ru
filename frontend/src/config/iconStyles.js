export const ICON_STYLE_STORAGE_KEY = 'prognos9ys_icon_style';

export const ICON_STYLES = {
  flat: {
    id: 'flat',
    title: 'Плоский',
    description: 'Минимализм, без теней, чёткие формы',
    preview: require('@/assets/icons/previews/ball-flat.png'),
  },
  soft: {
    id: 'soft',
    title: 'Объёмный',
    description: 'Мягкие тени и лёгкий градиент',
    preview: require('@/assets/icons/previews/ball-soft.png'),
  },
  pixel: {
    id: 'pixel',
    title: 'Пиксель-арт',
    description: 'Ретро-спрайт, заметные пиксели',
    preview: require('@/assets/icons/previews/ball-pixel.png'),
  },
};

export function getStoredIconStyle() {
  const stored = localStorage.getItem(ICON_STYLE_STORAGE_KEY);
  if (stored && ICON_STYLES[stored]) {
    return stored;
  }
  return 'soft';
}

export function setStoredIconStyle(styleId) {
  if (!ICON_STYLES[styleId]) {
    return;
  }
  localStorage.setItem(ICON_STYLE_STORAGE_KEY, styleId);
}

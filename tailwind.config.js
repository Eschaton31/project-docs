/** @type {import('tailwindcss').Config} */
import colors from 'tailwindcss/colors'
import defaultTheme from 'tailwindcss/defaultTheme'

export default {
  content: ['./resources/views/**/*.blade.php', './resources/js/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['InterVariable', ...defaultTheme.fontFamily.sans],
        mono: ['Space Mono', ...defaultTheme.fontFamily.mono],
      },
    },
    colors: {
      bene: {
        teal: '#0b6374',
        gray: '#424242',
      },
      transparent: 'transparent',
      current: 'currentColor',
      black: colors.black,
      white: colors.white,
      gray: colors.gray,
      slate: colors.slate,
      emerald: colors.emerald,
      green: colors.green,
      indigo: colors.indigo,
      yellow: colors.yellow,
      teal: colors.teal,
      amber: colors.amber,
      rose: colors.rose,
      sky: colors.sky,
    },
  },
  plugins: [],
}

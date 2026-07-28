const defaultTheme = require('tailwindcss/defaultTheme');
const colors = require("tailwindcss/colors")

/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        "./node_modules/vue-tailwind-datepicker/**/*.js"
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Nunito', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                "vtd-primary": colors.sky, // Light mode Datepicker color
                "vtd-secondary": colors.gray, // Dark mode Datepicker color
              },
            keyframes: {
                'pin-shake': {
                    '0%, 100%': { transform: 'translateX(0)' },
                    '20%': { transform: 'translateX(-6px)' },
                    '40%': { transform: 'translateX(6px)' },
                    '60%': { transform: 'translateX(-4px)' },
                    '80%': { transform: 'translateX(4px)' },
                },
            },
            animation: {
                'pin-shake': 'pin-shake 0.4s ease-in-out',
            },
        },
    },

    plugins: [require('@tailwindcss/forms')],
};

import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    // PDF-Annotation + Stempel-Buttons nutzen dynamische Farbklassen
    // basierend auf der 'color'-Spalte. Damit JIT die nicht abschneidet,
    // explizit safelisten.
    safelist: [
        ...['slate','emerald','rose','amber','indigo','violet','sky'].flatMap(c => [
            `bg-${c}-50`, `bg-${c}-100`, `border-${c}-200`, `border-${c}-400`,
            `text-${c}-700`, `text-${c}-900`, `hover:bg-${c}-100`,
        ]),
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms, typography],
};

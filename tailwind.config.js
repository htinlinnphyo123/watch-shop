export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', 'sans-serif'],
            },
            colors: {
                gold: {
                    50: '#fbf8f3',
                    100: '#f5efe4',
                    200: '#e9ddc3',
                    300: '#dcc59c',
                    400: '#c69f58',
                    500: '#d4af37', // Base Gold
                    600: '#aa8c2c',
                    700: '#856b25',
                    800: '#6c5624',
                    900: '#5a4823',
                },
                dark: {
                    800: '#1f2937',
                    900: '#111827',
                }
            }
        },
    },

    plugins: [require('@tailwindcss/forms')],
};

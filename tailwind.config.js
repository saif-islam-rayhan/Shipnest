import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#F57C00',
                    50: '#FFF3E0',
                    100: '#FFE0B2',
                    200: '#FFCC80',
                    300: '#FFB74D',
                    400: '#FFA726',
                    500: '#F57C00',
                    600: '#EF6C00',
                    700: '#E65100',
                    800: '#BF360C',
                    900: '#E65100',
                },
                secondary: {
                    DEFAULT: '#1A237E',
                    50: '#E8EAF6',
                    100: '#C5CAE9',
                    200: '#9FA8DA',
                    300: '#7986CB',
                    400: '#5C6BC0',
                    500: '#3F51B5',
                    600: '#3949AB',
                    700: '#303F9F',
                    800: '#283593',
                    900: '#1A237E',
                },
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [],
};

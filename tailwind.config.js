/** @type {import('tailwindcss').Config} */
export default {
    safelist: [
        'bg-red-100', 'text-red-700',
        'bg-orange-100', 'text-orange-700',
        'bg-blue-50', 'text-blue-700',
        'bg-gray-100', 'text-gray-500',
        'bg-green-100', 'text-green-700',
        'btn-pulse-reminder',
        // add any others that appear missing
    ],
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
    ],
    theme: {
        extend: {},
    },
    plugins: [],
}

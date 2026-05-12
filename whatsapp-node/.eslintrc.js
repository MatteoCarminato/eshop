module.exports = {
    env: {
        commonjs: true,
        es2021: true,
        node: true,
        jest: true,
    },
    extends: ['airbnb-base', 'prettier'],
    plugins: ['prettier'],
    parserOptions: {
        ecmaVersion: 'latest',
    },
    rules: {
        'prettier/prettier': 'error',
        'no-console': 'warn',
        'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
        'consistent-return': 'off',
        'no-underscore-dangle': 'off',
        'class-methods-use-this': 'off',
        'import/no-dynamic-require': 'off',
        'global-require': 'off',
    },
};

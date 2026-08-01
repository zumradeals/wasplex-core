import eslint from '@eslint/js';
import stylistic from '@stylistic/eslint-plugin';
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import prettier from 'eslint-config-prettier/flat';
import importPlugin from 'eslint-plugin-import';
import vue from 'eslint-plugin-vue';

export default defineConfigWithVueTs(
    eslint.configs.recommended,
    vue.configs['flat/essential'],
    vueTsConfigs.recommended,
    {
        plugins: {
            '@stylistic': stylistic,
            import: importPlugin,
        },
        settings: {
            'import/resolver': {
                typescript: { project: './tsconfig.json' },
                node: true,
            },
        },
        rules: {
            '@typescript-eslint/consistent-type-imports': 'error',
            'import/order': [
                'error',
                {
                    alphabetize: { order: 'asc', caseInsensitive: true },
                    groups: ['builtin', 'external', 'internal', 'parent', 'sibling', 'index'],
                },
            ],
            'vue/multi-word-component-names': 'off',
        },
    },
    {
        ignores: ['node_modules', 'public', 'vendor', 'resources/js/actions/**', 'resources/js/routes/**'],
    },
    prettier,
);

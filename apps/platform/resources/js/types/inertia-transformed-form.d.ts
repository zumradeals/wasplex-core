import type { FormDataType } from '@inertiajs/core';
import type { InertiaForm } from '@inertiajs/vue3';

type TaxonomyOption = {
    value: string;
    label: string;
};

declare module '@inertiajs/vue3' {
    export function useForm<TForm extends FormDataType<TForm> & { options_text: string }>(
        data: TForm | (() => TForm),
    ): InertiaForm<TForm & { options: TaxonomyOption[] }>;
}

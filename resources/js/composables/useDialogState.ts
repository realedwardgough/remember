import type { InjectionKey } from 'vue';

export type DialogState = { dirty: boolean; busy: boolean };
export const dialogStateKey: InjectionKey<DialogState> = Symbol('dialogState');

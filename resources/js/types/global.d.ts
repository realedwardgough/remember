import type { Page, Router } from '@inertiajs/core';
import type { createHeadManager } from '@inertiajs/vue3';
import type { Auth } from '@/types/auth';

declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: { type: 'success' | 'error'; message: string };
            toasts?: Array<{
                type: 'success' | 'error';
                message: string;
            }>;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            remember: { emailNotificationsEnabled: boolean };
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}

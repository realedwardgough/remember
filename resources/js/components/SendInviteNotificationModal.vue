<template>
    <Modal
        :show="invite !== null"
        title="Send invite email"
        eyebrow="Account invitation"
        @close="emit('close')"
    >
        <Form
            v-if="invite"
            v-bind="notifyInvitation.form(invite.id)"
            reset-on-success
            class="grid gap-5"
            @success="emit('close')"
            v-slot="{ errors, processing }"
        >
            <p class="font-semibold text-zinc-700">
                Send the registration link for
                <strong>@{{ invite.username }}</strong
                >.
            </p>
            <label class="grid gap-2 text-sm font-semibold">
                Email address
                <input
                    name="email"
                    type="email"
                    required
                    maxlength="254"
                    autocomplete="email"
                    autofocus
                    placeholder="person@example.com"
                    class="rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-base font-semibold focus:bg-canvas"
                />
                <span
                    v-if="errors.email"
                    class="border-l border-red-600 pl-2 text-red-700"
                    >{{ errors.email }}</span
                >
            </label>
            <div class="flex flex-wrap justify-end gap-3">
                <button
                    type="button"
                    :disabled="processing"
                    class="cursor-pointer rounded-xl border border-stone-200 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-950 disabled:opacity-60"
                    @click="emit('close')"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="processing"
                    class="cursor-pointer rounded-xl border border-stone-200 bg-primary px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
                >
                    {{ processing ? 'Sending…' : 'Send notification' }}
                </button>
            </div>
        </Form>
    </Modal>
</template>

<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { notify as notifyInvitation } from '@/actions/App/Http/Controllers/TimelineInvitationController';
import Modal from '@/components/Modal.vue';

defineProps<{
    invite: { id: number; username: string } | null;
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

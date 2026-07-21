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
            <label class="grid gap-2 text-sm font-black">
                Email address
                <input
                    name="email"
                    type="email"
                    required
                    maxlength="254"
                    autocomplete="email"
                    autofocus
                    placeholder="person@example.com"
                    class="border-3 border-zinc-950 bg-white px-3 py-2.5 text-base font-semibold outline-none focus:bg-[#fff9e8] focus:shadow-[4px_4px_0_#e06573]"
                />
                <span
                    v-if="errors.email"
                    class="border-l-4 border-red-600 pl-2 text-red-700"
                    >{{ errors.email }}</span
                >
            </label>
            <div class="flex flex-wrap justify-end gap-3">
                <button
                    type="button"
                    :disabled="processing"
                    class="cursor-pointer border-3 border-zinc-950 bg-white px-4 py-2.5 text-sm font-black text-zinc-950 disabled:opacity-60"
                    @click="emit('close')"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="processing"
                    class="cursor-pointer border-3 border-zinc-950 bg-[#e06573] px-4 py-2.5 text-sm font-black text-white shadow-[4px_4px_0_#18181b] disabled:opacity-60"
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

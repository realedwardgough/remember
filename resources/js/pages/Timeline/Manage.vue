<template>
    <BaseLayout>
        <template #sidebar>
            <Sidebar
                @open-post-form="activeModal = 'post'"
                @open-search="activeModal = 'search'"
            />
        </template>

        <Head title="Manage timeline" />

        <section class="grid gap-6">
            <PageHeader
                title="Your timeline & family"
                eyebrow="Admin tools"
                description="Make this space your own and invite the people you love."
            >
                <Link :href="profile()" class="button-secondary"
                    >Back to account</Link
                >
            </PageHeader>

            <section
                class="grid items-start gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]"
            >
                <div class="grid gap-6">
                    <article class="surface overflow-hidden">
                        <div class="border-b border-stone-200 bg-canvas p-5">
                            <h2 class="text-xl font-semibold">
                                Timeline details
                            </h2>
                            <p class="mt-1 text-sm font-medium text-zinc-700">
                                Shown throughout the website and navigation.
                            </p>
                        </div>
                        <Form
                            v-bind="updateTimeline.form.put()"
                            set-defaults-on-success
                            v-slot="{ errors, processing, wasSuccessful }"
                            class="grid gap-5 p-5"
                        >
                            <label class="grid gap-2 text-sm font-semibold">
                                Timeline name
                                <input
                                    name="name"
                                    required
                                    maxlength="100"
                                    :value="managedTimeline.name"
                                    class="rounded-xl border border-stone-200 px-3 py-2.5 text-base font-semibold focus:bg-canvas"
                                />
                                <span
                                    v-if="errors.name"
                                    class="border-l border-red-600 pl-2 text-red-700"
                                    >{{ errors.name }}</span
                                >
                            </label>
                            <label class="grid gap-2 text-sm font-semibold">
                                Description
                                <textarea
                                    name="description"
                                    maxlength="280"
                                    rows="4"
                                    :value="managedTimeline.description ?? ''"
                                    class="resize-y rounded-xl border border-stone-200 px-3 py-2.5 text-base font-semibold focus:bg-canvas"
                                ></textarea>
                                <span
                                    v-if="errors.description"
                                    class="border-l border-red-600 pl-2 text-red-700"
                                    >{{ errors.description }}</span
                                >
                            </label>
                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    type="submit"
                                    :disabled="processing"
                                    class="cursor-pointer rounded-xl border border-stone-200 bg-primary px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
                                >
                                    {{
                                        processing ? 'Saving…' : 'Save timeline'
                                    }}
                                </button>
                                <span
                                    v-if="wasSuccessful"
                                    class="rounded-xl border border-stone-200 bg-mint px-3 py-1.5 text-sm font-semibold"
                                    >Saved.</span
                                >
                            </div>
                        </Form>
                    </article>

                    <article class="surface overflow-hidden">
                        <div class="border-b border-stone-200 bg-canvas p-5">
                            <h2 class="text-xl font-semibold">
                                Invite someone
                            </h2>
                            <p class="mt-1 text-sm font-medium text-zinc-700">
                                Create a single-use link for their reserved
                                username.
                            </p>
                        </div>
                        <Form
                            v-bind="storeInvitation.form()"
                            reset-on-success
                            v-slot="{ errors, processing }"
                            class="grid gap-4 p-5"
                        >
                            <label class="grid gap-2 text-sm font-semibold">
                                Username
                                <input
                                    name="username"
                                    required
                                    maxlength="50"
                                    autocomplete="off"
                                    placeholder="family-member"
                                    class="rounded-xl border border-stone-200 px-3 py-2.5 text-base font-semibold lowercase focus:bg-canvas"
                                />
                                <span
                                    v-if="errors.username"
                                    class="border-l border-red-600 pl-2 text-red-700"
                                    >{{ errors.username }}</span
                                >
                            </label>
                            <button
                                type="submit"
                                :disabled="processing"
                                class="w-fit cursor-pointer rounded-xl border border-stone-200 bg-butter px-4 py-2.5 text-sm font-semibold disabled:opacity-60"
                            >
                                {{
                                    processing
                                        ? 'Creating…'
                                        : 'Create invite link'
                                }}
                            </button>
                        </Form>
                        <div
                            v-if="flash?.inviteUrl"
                            class="m-5 mt-0 grid gap-2 rounded-xl border border-stone-200 bg-canvas p-4"
                        >
                            <strong class="text-sm font-semibold"
                                >Invite ready — copy this link now</strong
                            >
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <input
                                    readonly
                                    :value="flash.inviteUrl"
                                    class="min-w-0 flex-1 rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm font-semibold"
                                />
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-xl border border-stone-200 bg-mint px-3 py-2 text-sm font-semibold"
                                    @click="copyInvite"
                                >
                                    Copy
                                </button>
                            </div>
                        </div>
                        <div
                            v-if="pendingInvites.length"
                            class="border-t border-stone-200 p-5"
                        >
                            <h3 class="text-sm font-semibold">
                                Pending invitations
                            </h3>
                            <ul class="mt-3 grid gap-2">
                                <li
                                    v-for="invite in pendingInvites"
                                    :key="invite.id"
                                    class="flex flex-col gap-3 rounded-xl border border-stone-200 bg-canvas px-3 py-3 text-sm font-bold sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div class="min-w-0">
                                        <span class="block"
                                            >@{{ invite.username }}</span
                                        >
                                        <span class="text-xs text-zinc-600"
                                            >Awaiting signup</span
                                        >
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-if="invite.url"
                                            type="button"
                                            class="cursor-pointer rounded-xl border border-stone-200 bg-mint px-3 py-1.5 text-xs font-semibold"
                                            @click="copyUrl(invite.url)"
                                        >
                                            Copy invite URL
                                        </button>
                                        <button
                                            v-if="
                                                $page.props.remember
                                                    .emailNotificationsEnabled &&
                                                invite.url
                                            "
                                            type="button"
                                            class="cursor-pointer rounded-xl border border-stone-200 bg-primary px-3 py-1.5 text-xs font-semibold text-white"
                                            @click="
                                                invitePendingNotification =
                                                    invite
                                            "
                                        >
                                            Send email notification
                                        </button>
                                        <Form
                                            v-if="!invite.url"
                                            v-bind="
                                                refreshInvitation.form.put(
                                                    invite.id,
                                                )
                                            "
                                            v-slot="{ processing }"
                                        >
                                            <button
                                                type="submit"
                                                :disabled="processing"
                                                class="cursor-pointer rounded-xl border border-stone-200 bg-butter px-3 py-1.5 text-xs font-semibold disabled:opacity-60"
                                            >
                                                {{
                                                    processing
                                                        ? 'Generating…'
                                                        : 'Generate replacement URL'
                                                }}
                                            </button>
                                        </Form>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </article>
                </div>

                <article class="surface overflow-hidden">
                    <div class="border-b border-stone-200 bg-canvas p-5">
                        <h2 class="text-xl font-semibold">Timeline members</h2>
                        <p class="mt-1 text-sm font-medium text-zinc-700">
                            Removing an account keeps its timeline posts and
                            comments as anonymous history.
                        </p>
                    </div>
                    <div class="divide-y-3 divide-zinc-950">
                        <div
                            v-for="member in users"
                            :key="member.id"
                            class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold break-words">
                                        {{ member.name }}
                                    </h3>
                                    <span
                                        v-if="member.isCurrentUser"
                                        class="rounded-xl border border-stone-200 bg-butter px-2 py-0.5 text-xs font-semibold"
                                        >You</span
                                    >
                                </div>
                                <p
                                    class="mt-1 truncate text-sm font-semibold text-zinc-600"
                                >
                                    @{{ member.username }} · {{ member.email }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-start gap-3">
                                <Form
                                    v-bind="updateRole.form.put(member.id)"
                                    v-slot="{ errors, processing }"
                                    class="grid gap-1"
                                >
                                    <div class="flex gap-2">
                                        <select
                                            name="role"
                                            :value="member.role"
                                            class="rounded-xl border border-stone-200 bg-white px-2 py-2 text-sm font-semibold"
                                        >
                                            <option
                                                v-for="role in roles"
                                                :key="role"
                                                :value="role"
                                            >
                                                {{ roleLabel(role) }}
                                            </option>
                                        </select>
                                        <button
                                            type="submit"
                                            :disabled="processing"
                                            class="cursor-pointer rounded-xl border border-stone-200 bg-mint px-3 py-2 text-sm font-semibold disabled:opacity-60"
                                        >
                                            Save role
                                        </button>
                                    </div>
                                    <span
                                        v-if="errors.role"
                                        class="max-w-72 text-xs font-bold text-red-700"
                                        >{{ errors.role }}</span
                                    >
                                </Form>
                                <button
                                    v-if="!member.isCurrentUser"
                                    type="button"
                                    class="cursor-pointer rounded-xl border border-stone-200 bg-primary px-3 py-2 text-sm font-semibold text-white"
                                    @click="removeMember(member)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>
                    <p
                        v-if="$page.props.errors.user"
                        class="border-t border-stone-200 bg-red-50 p-4 text-sm font-semibold text-red-700"
                    >
                        {{ $page.props.errors.user }}
                    </p>
                </article>
            </section>
        </section>

        <Modal
            :show="activeModal === 'post'"
            mobile-fullscreen
            title="Add a memory"
            @close="activeModal = null"
            ><PostForm @close="activeModal = null"
        /></Modal>
        <Modal
            :show="activeModal === 'search'"
            title="Find a moment"
            @close="activeModal = null"
            ><Search @close="activeModal = null"
        /></Modal>
        <SendInviteNotificationModal
            :invite="invitePendingNotification"
            @close="invitePendingNotification = null"
        />

        <ConfirmModal
            :show="memberPendingRemoval !== null"
            title="Remove timeline member?"
            eyebrow="Account removal"
            :message="memberRemovalMessage"
            confirm-label="Remove member"
            processing-label="Removing…"
            :processing="removeProcessing"
            @close="closeRemoveConfirmation"
            @confirm="confirmMemberRemoval"
        />
    </BaseLayout>
</template>

<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { update as updateTimeline } from '@/actions/App/Http/Controllers/TimelineController';
import {
    store as storeInvitation,
    update as refreshInvitation,
} from '@/actions/App/Http/Controllers/TimelineInvitationController';
import { destroy } from '@/actions/App/Http/Controllers/TimelineUserController';
import { update as updateRole } from '@/actions/App/Http/Controllers/TimelineUserRoleController';
import ConfirmModal from '@/components/ConfirmModal.vue';
import Modal from '@/components/Modal.vue';
import PageHeader from '@/components/PageHeader.vue';
import PostForm from '@/components/PostForm.vue';
import Search from '@/components/Search.vue';
import SendInviteNotificationModal from '@/components/SendInviteNotificationModal.vue';
import Sidebar from '@/components/Sidebar.vue';
import BaseLayout from '@/layouts/BaseLayout.vue';
import { edit as profile } from '@/routes/profile';

type Member = {
    id: number;
    name: string;
    username: string;
    email: string;
    role: string;
    isCurrentUser: boolean;
};
type Invite = {
    id: number;
    username: string;
    created_at: string;
    url: string | null;
};

const activeModal = ref<'post' | 'search' | null>(null);

const props = defineProps<{
    managedTimeline: { name: string; description: string | null };
    users: Member[];
    roles: string[];
    pendingInvites: Invite[];
    flash?: {
        inviteUrl?: string | null;
    };
}>();

const memberPendingRemoval = ref<Member | null>(null);
const invitePendingNotification = ref<Invite | null>(null);
const removeProcessing = ref(false);
const memberRemovalMessage = computed(() =>
    memberPendingRemoval.value
        ? `Remove ${memberPendingRemoval.value.name} from this timeline? Their posts and comments will be kept anonymously, but they will no longer be able to sign in.`
        : '',
);

function roleLabel(role: string): string {
    return role === 'admin' ? 'Administrator' : 'User';
}

function removeMember(member: Member): void {
    memberPendingRemoval.value = member;
}

function closeRemoveConfirmation(): void {
    if (!removeProcessing.value) {
        memberPendingRemoval.value = null;
    }
}

function confirmMemberRemoval(): void {
    if (!memberPendingRemoval.value) {
        return;
    }

    router.visit(destroy(memberPendingRemoval.value.id), {
        preserveScroll: true,
        onStart: () => {
            removeProcessing.value = true;
        },
        onSuccess: () => {
            memberPendingRemoval.value = null;
        },
        onFinish: () => {
            removeProcessing.value = false;
        },
    });
}

async function copyInvite(): Promise<void> {
    if (props.flash?.inviteUrl) {
        await copyUrl(props.flash.inviteUrl);
    }
}

async function copyUrl(url: string): Promise<void> {
    await navigator.clipboard.writeText(url);
}
</script>

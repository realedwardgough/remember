<template>
    <BaseLayout>
        <template #sidebar>
            <Sidebar />
        </template>

        <Head title="Manage timeline" />

        <main class="grid gap-8 pb-24 md:pb-0">
            <header
                class="relative overflow-hidden border-4 border-zinc-950 bg-[#77c8b5] p-5 shadow-[8px_8px_0_#18181b] sm:p-8"
            >
                <div
                    class="absolute -right-8 -bottom-12 size-40 rotate-12 border-4 border-zinc-950 bg-[#f4ce63]"
                    aria-hidden="true"
                ></div>
                <p
                    class="relative z-10 inline-flex border-3 border-zinc-950 bg-white px-3 py-1 text-xs font-black tracking-[0.2em] uppercase shadow-[3px_3px_0_#18181b]"
                >
                    Admin tools
                </p>
                <div
                    class="relative z-10 mt-5 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <h1
                            class="text-3xl leading-none font-black tracking-tight text-zinc-950 sm:text-5xl"
                        >
                            Manage your timeline
                        </h1>
                        <p
                            class="mt-3 max-w-2xl text-base font-bold text-zinc-800"
                        >
                            Update its identity, invite family members, and
                            control who can administer it.
                        </p>
                    </div>
                    <Link
                        :href="profile()"
                        class="w-fit border-3 border-zinc-950 bg-white px-4 py-2.5 text-sm font-black shadow-[4px_4px_0_#18181b] transition hover:-translate-y-0.5"
                        >Back to account</Link
                    >
                </div>
            </header>

            <section
                class="grid items-start gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]"
            >
                <div class="grid gap-6">
                    <article
                        class="border-4 border-zinc-950 bg-white shadow-[7px_7px_0_#e06573]"
                    >
                        <div
                            class="border-b-3 border-zinc-950 bg-[#f7f1e7] p-5"
                        >
                            <h2 class="text-xl font-black">Timeline details</h2>
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
                            <label class="grid gap-2 text-sm font-black">
                                Timeline name
                                <input
                                    name="name"
                                    required
                                    maxlength="100"
                                    :value="managedTimeline.name"
                                    class="border-3 border-zinc-950 px-3 py-2.5 text-base font-semibold outline-none focus:bg-[#fff9e8] focus:shadow-[4px_4px_0_#e06573]"
                                />
                                <span
                                    v-if="errors.name"
                                    class="border-l-4 border-red-600 pl-2 text-red-700"
                                    >{{ errors.name }}</span
                                >
                            </label>
                            <label class="grid gap-2 text-sm font-black">
                                Description
                                <textarea
                                    name="description"
                                    maxlength="280"
                                    rows="4"
                                    :value="managedTimeline.description ?? ''"
                                    class="resize-y border-3 border-zinc-950 px-3 py-2.5 text-base font-semibold outline-none focus:bg-[#fff9e8] focus:shadow-[4px_4px_0_#e06573]"
                                ></textarea>
                                <span
                                    v-if="errors.description"
                                    class="border-l-4 border-red-600 pl-2 text-red-700"
                                    >{{ errors.description }}</span
                                >
                            </label>
                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    type="submit"
                                    :disabled="processing"
                                    class="cursor-pointer border-3 border-zinc-950 bg-[#e06573] px-4 py-2.5 text-sm font-black text-white shadow-[4px_4px_0_#18181b] disabled:opacity-60"
                                >
                                    {{
                                        processing ? 'Saving…' : 'Save timeline'
                                    }}
                                </button>
                                <span
                                    v-if="wasSuccessful"
                                    class="border-2 border-zinc-950 bg-[#77c8b5] px-3 py-1.5 text-sm font-black"
                                    >Saved.</span
                                >
                            </div>
                        </Form>
                    </article>

                    <article
                        class="border-4 border-zinc-950 bg-white shadow-[7px_7px_0_#f4ce63]"
                    >
                        <div
                            class="border-b-3 border-zinc-950 bg-[#f7f1e7] p-5"
                        >
                            <h2 class="text-xl font-black">Invite someone</h2>
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
                            <label class="grid gap-2 text-sm font-black">
                                Username
                                <input
                                    name="username"
                                    required
                                    maxlength="50"
                                    autocomplete="off"
                                    placeholder="family-member"
                                    class="border-3 border-zinc-950 px-3 py-2.5 text-base font-semibold lowercase outline-none focus:bg-[#fff9e8] focus:shadow-[4px_4px_0_#f4ce63]"
                                />
                                <span
                                    v-if="errors.username"
                                    class="border-l-4 border-red-600 pl-2 text-red-700"
                                    >{{ errors.username }}</span
                                >
                            </label>
                            <button
                                type="submit"
                                :disabled="processing"
                                class="w-fit cursor-pointer border-3 border-zinc-950 bg-[#f4ce63] px-4 py-2.5 text-sm font-black shadow-[4px_4px_0_#18181b] disabled:opacity-60"
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
                            class="m-5 mt-0 grid gap-2 border-3 border-zinc-950 bg-[#fff9e8] p-4"
                        >
                            <strong class="text-sm font-black"
                                >Invite ready — copy this link now</strong
                            >
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <input
                                    readonly
                                    :value="flash.inviteUrl"
                                    class="min-w-0 flex-1 border-2 border-zinc-950 bg-white px-3 py-2 text-sm font-semibold"
                                />
                                <button
                                    type="button"
                                    class="cursor-pointer border-2 border-zinc-950 bg-[#77c8b5] px-3 py-2 text-sm font-black"
                                    @click="copyInvite"
                                >
                                    Copy
                                </button>
                            </div>
                        </div>
                        <p
                            v-if="flash?.inviteNotificationSent"
                            class="m-5 mt-0 border-3 border-zinc-950 bg-[#77c8b5] p-3 text-sm font-black"
                        >
                            Email notification sent.
                        </p>
                        <div
                            v-if="pendingInvites.length"
                            class="border-t-3 border-zinc-950 p-5"
                        >
                            <h3 class="text-sm font-black uppercase">
                                Pending invitations
                            </h3>
                            <ul class="mt-3 grid gap-2">
                                <li
                                    v-for="invite in pendingInvites"
                                    :key="invite.id"
                                    class="flex flex-col gap-3 border-2 border-zinc-950 bg-[#f7f1e7] px-3 py-3 text-sm font-bold sm:flex-row sm:items-center sm:justify-between"
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
                                            class="cursor-pointer border-2 border-zinc-950 bg-[#77c8b5] px-3 py-1.5 text-xs font-black"
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
                                            class="cursor-pointer border-2 border-zinc-950 bg-[#e06573] px-3 py-1.5 text-xs font-black text-white"
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
                                                class="cursor-pointer border-2 border-zinc-950 bg-[#f4ce63] px-3 py-1.5 text-xs font-black disabled:opacity-60"
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

                <article
                    class="border-4 border-zinc-950 bg-white shadow-[7px_7px_0_#77c8b5]"
                >
                    <div class="border-b-3 border-zinc-950 bg-[#f7f1e7] p-5">
                        <h2 class="text-xl font-black">Timeline members</h2>
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
                                    <h3 class="font-black break-words">
                                        {{ member.name }}
                                    </h3>
                                    <span
                                        v-if="member.isCurrentUser"
                                        class="border-2 border-zinc-950 bg-[#f4ce63] px-2 py-0.5 text-xs font-black uppercase"
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
                                            class="border-3 border-zinc-950 bg-white px-2 py-2 text-sm font-black"
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
                                            class="cursor-pointer border-3 border-zinc-950 bg-[#77c8b5] px-3 py-2 text-sm font-black disabled:opacity-60"
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
                                    class="cursor-pointer border-3 border-zinc-950 bg-[#e06573] px-3 py-2 text-sm font-black text-white"
                                    @click="removeMember(member)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>
                    <p
                        v-if="$page.props.errors.user"
                        class="border-t-3 border-zinc-950 bg-red-50 p-4 text-sm font-black text-red-700"
                    >
                        {{ $page.props.errors.user }}
                    </p>
                </article>
            </section>
        </main>

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

const props = defineProps<{
    managedTimeline: { name: string; description: string | null };
    users: Member[];
    roles: string[];
    pendingInvites: Invite[];
    flash?: {
        inviteUrl?: string | null;
        inviteNotificationSent?: boolean | null;
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

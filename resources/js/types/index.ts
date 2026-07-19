export * from './auth';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export type GalleryImage = {
    id: number;
    name: string;
    url: string;
    downloadUrl: string;
    width: number | null;
    height: number | null;
    mimeType: string;
    size: number;
    postTitle: string | null;
    postDate: string | null;
};

export type ImageViewerMedia = {
    id: number;
    name: string;
    mimeType: string;
    size: number;
    url: string;
    downloadUrl: string;
    width: number | null;
    height: number | null;
};

export type TimelineMedia = ImageViewerMedia;

export type TimelinePostType = 'Memory' | 'Milestone' | 'Event' | 'Letter';

export type TimelineFilters = {
    search: string;
    tag: string;
    type: TimelinePostType | '';
    author: string;
};

export type TimelineComment = {
    id: number;
    content: string;
    author_fullname: string;
    author: string;
    createdAt: string;
    heartsCount: number;
    heartedByViewer: boolean;
};

export type TimelinePost = {
    id: number;
    type: TimelinePostType;
    title: string;
    content: string;
    date: string;
    datetime: string;
    author: string;
    initial: string;
    images: TimelineMedia[];
    files: TimelineMedia[];
    tags: string[];
    comments: TimelineComment[];
    commentsCount: number;
    heartsCount: number;
    heartedByViewer: boolean;
    canEdit: boolean;
    badgeClass: string;
    typeClass: string;
};

export type PaginatedTimelinePosts = {
    data: TimelinePost[];
};

export interface Family {
    name: string;
    username: string;
    photo: string | undefined;
}

export interface PageProps extends InertiaPageProps {
    family: Record<string, Family>;
    timeline: { name: string; description: string | null };
    filters: TimelineFilters;
    postTypes: TimelinePostType[];
    tags: string[];
    family_count: number;
    media_count: number;
    memories_count: number;
    flash?: { inviteUrl?: string | null };
}

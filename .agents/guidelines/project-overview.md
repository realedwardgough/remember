# Emily Timeline

## Project Overview

Emily Timeline is a private family memory platform built for documenting the life journey of our daughter, Emily.

The application acts as a secure, invite-only digital timeline where parents and trusted family members can create memories, upload photographs, write updates, and preserve milestones.

The long-term goal is to create a living timeline that Emily can eventually browse herself and experience moments from before her birth through childhood and beyond.

This is not intended to be a public social media platform.

The application should feel closer to:

- A private family journal
- A digital scrapbook
- A timeline-based memory book

than a traditional social network.

---

# Core Goals

The application should prioritise:

1. Privacy
2. Simplicity
3. Longevity
4. Ease of use
5. Family memories
6. Data ownership

The project should avoid unnecessary complexity and should remain maintainable for many years.

---

# Primary Users

## Parent

Full administrative access.

Permissions:

- Create posts
- Edit posts
- Delete posts
- Upload media
- Manage users
- Manage invitations
- Manage visibility
- Export data

Examples:

- Edward
- Charl

---

## Family Member

Trusted invited account.

Permissions:

- View timeline
- Create posts
- Upload media
- Leave comments

Cannot:

- Manage users
- Delete other users' content

---

## Viewer

Read-only account.

Permissions:

- Browse timeline
- View photos
- Read memories

Cannot create content.

---

# Core Features

## Timeline

The homepage should display memories in chronological order.

Each timeline item contains:

- Title
- Content
- Date
- Author
- Media attachments
- Optional tags

Examples:

- First scan
- Chosen name
- Nursery finished
- First birthday
- First day of school

---

## Posts

Posts are the primary content type.

Fields:

- title
- content
- author_id
- published_at
- visibility

Posts should support rich text formatting.

---

## Media

Users should be able to upload:

- Images
- Videos (future)

Images are a first-class feature of the application.

Each media item should:

- Belong to a post
- Store metadata
- Generate thumbnails

---

## Comments

Family members may leave comments on memories.

Examples:

- Messages to Emily
- Reactions to milestones
- Shared memories

---

## Milestones

Milestones are special timeline entries.

Examples:

- Birth
- First smile
- First steps
- First word
- First birthday

Milestones may later have dedicated UI treatment.

---

## Letters to Emily

A special content type.

Parents can write letters directly to Emily.

Examples:

"Dear Emily..."

These should be preserved permanently and may later be compiled into printable books.

---

# Privacy Requirements

The application must be private by default.

Requirements:

- No public registration
- Invite-only accounts
- Authentication required
- Search engines blocked
- No public timeline access
- No public media URLs where possible

Privacy is more important than social features.

---

# Data Preservation

Long-term preservation is a major project goal.

Future features should support:

- Full database export
- Media export
- PDF generation
- Printed memory books
- Backup tooling

Features that make data extraction difficult should be avoided.

---

# Technical Stack

Preferred stack:

- Laravel
- MySQL
- Vue.js
- Tailwind CSS

Avoid introducing React, Vue, or other SPA frameworks unless there is a strong reason.

The project should remain server-rendered wherever possible.

---

# Development Philosophy

Prefer:

- Laravel conventions
- Simple architecture
- Readable code
- Eloquent relationships
- Form Requests
- Service classes when appropriate

Avoid:

- Premature optimisation
- Over-engineering
- Microservices
- Complex event-driven systems
- Unnecessary abstractions

---

# Initial Database Concepts

## users

Stores authenticated users.

## posts

Stores timeline memories.

## media

Stores uploaded files.

## comments

Stores discussion on memories.

## tags

Stores categorisation.

## invitations

Stores invite links and pending users.

---

# Future Features

Potential future additions:

- Memory books
- Yearly summaries
- "On This Day" memories
- Reactions
- Video uploads
- Family tree view
- Timeline filtering
- Search
- AI-generated yearly recaps
- Mobile application

These are lower priority than the core timeline experience.

---

# Design Direction

The UI should feel:

- Warm
- Personal
- Minimal
- Family-focused

Avoid designs that resemble public social media platforms.

The application should feel timeless and suitable for long-term memory preservation.

---

# Success Criteria

A successful version of the application allows Emily, years from now, to:

- Browse memories from before her birth
- View photographs and milestones
- Read messages from her parents
- See how family members interacted throughout her childhood
- Experience a complete digital history of her life

---

# Vue Standards

- Script tag should always be below the template tag
- Don't use unnecessary semicolons

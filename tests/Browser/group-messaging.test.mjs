import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';

const source = readFileSync(
    new URL('../../resources/js/lib/conversations.ts', import.meta.url),
    'utf8',
);
const compiled = ts.transpileModule(source, {
    compilerOptions: {
        module: ts.ModuleKind.CommonJS,
        target: ts.ScriptTarget.ES2022,
    },
}).outputText;
const exports = {};
vm.runInNewContext(compiled, { exports });
const { conversationName, messageReceipt, typingLabel } = exports;

test('inbox labels show a group name and preserve the recipient name for direct messages', () => {
    assert.equal(
        conversationName({ is_group: true, name: 'Family plans' }),
        'Family plans',
    );
    assert.equal(
        conversationName({
            is_group: false,
            participants: [
                { name: 'Me', is_self: true },
                { name: 'Alex', is_self: false },
            ],
        }),
        'Alex',
    );
    assert.equal(
        conversationName({
            is_group: false,
            participants: [{ name: 'Deleted User', is_self: false }],
        }),
        'Deleted User',
    );
});

test('group receipts distinguish no readers, some readers, and everyone for each message', () => {
    const conversation = {
        is_group: true,
        read_receipts: [
            { participant_id: 2, last_read_message_id: 3 },
            { participant_id: 3, last_read_message_id: 8 },
            { participant_id: 4, last_read_message_id: null },
        ],
    };
    assert.equal(messageReceipt({ id: 9 }, conversation), 'Sent');
    assert.equal(messageReceipt({ id: 7 }, conversation), 'Seen by 1 of 3');
    assert.equal(messageReceipt({ id: 2 }, conversation), 'Seen by 2 of 3');
    conversation.read_receipts[2].last_read_message_id = 8;
    assert.equal(messageReceipt({ id: 2 }, conversation), 'Seen by 3 of 3');
    assert.equal(
        messageReceipt({ id: 9 }, { is_group: true, read_receipts: [] }),
        'Sent',
    );
});

test('direct message receipts remain Sent and Seen', () => {
    const conversation = { is_group: false, recipient_last_read_message_id: 7 };
    assert.equal(messageReceipt({ id: 8 }, conversation), 'Sent');
    assert.equal(messageReceipt({ id: 7 }, conversation), 'Seen');
});

test('typing feedback names the active members and handles multiple people', () => {
    assert.equal(typingLabel([]), '');
    assert.equal(typingLabel(['Alex']), 'Alex is typing…');
    assert.equal(typingLabel(['Alex', 'Sam']), 'Alex and Sam are typing…');
    assert.equal(
        typingLabel(['Alex', 'Sam', 'Jo']),
        'Alex and 2 others are typing…',
    );
});

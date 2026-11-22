import test from 'node:test';
import assert from 'node:assert/strict';
import { filterTeacherGroups } from '../../resources/js/lib/teacherGroupFilters.js';

const entries = [
    { id: 1, source: 'group', name: 'مجموعة الفيزياء', is_private: false, grade: { id: 10, name: 'الحادي عشر', stage: 'secondary', track: 'science' }, subject: { id: 1, name: 'الفيزياء' } },
    { id: 2, source: 'group', name: 'مجموعة الأدب', is_private: false, grade: { id: 11, name: 'الحادي عشر', stage: 'secondary', track: 'arts' }, subject: { id: 2, name: 'الأدب' } },
    { id: 1, source: 'private_slot', name: 'حصة برايفت', is_private: true, grade: { id: 10, name: 'الحادي عشر', stage: 'secondary', track: 'science' }, subject: { id: 1, name: 'الفيزياء' } },
    { id: 3, source: 'group', name: 'مجموعة ابتدائي', is_private: false, grade: { id: 3, name: 'الثالث', stage: 'primary', track: null }, subject: { id: 3, name: 'الرياضيات' } },
];

test('filters by type including independent private sessions', () => {
    assert.deepEqual(filterTeacherGroups(entries, { type: 'private' }).map(item => item.source), ['private_slot']);
    assert.equal(filterTeacherGroups(entries, { type: 'group' }).length, 3);
});

test('applies stage, grade, track, subject and type together', () => {
    const filters = { type: 'private', stage: 'secondary', grade: '10', track: 'science', subject: '1' };
    assert.deepEqual(filterTeacherGroups(entries, filters).map(item => item.source), ['private_slot']);
    assert.deepEqual(filterTeacherGroups(entries, { ...filters, track: 'arts' }), []);
    assert.deepEqual(filterTeacherGroups(entries, { stage: 'primary' }).map(item => item.id), [3]);
    assert.deepEqual(filterTeacherGroups(entries, { subject: '2' }).map(item => item.id), [2]);
    assert.deepEqual(filterTeacherGroups(entries, { grade: '11' }).map(item => item.id), [2]);
});

test('combines search with other filters', () => {
    assert.deepEqual(filterTeacherGroups(entries, { search: 'الفيزياء', type: 'group' }).map(item => item.id), [1]);
});

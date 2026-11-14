import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeSearchText } from '../../resources/js/lib/arabicSearch.js';

const matches = (text, query) => normalizeSearchText(text).includes(normalizeSearchText(query));

test('matches teacher, subject and grade spelling variations', () => {
    assert.ok(matches('أحمد محمد', 'احمد'));
    assert.ok(matches('اللغة العربية', 'اللغه العربيه'));
    assert.ok(matches('الصف الأول الإعدادي', 'الاول الاعدادي'));
    assert.ok(matches('التَّربية الإِسلامية', 'التربيه الاسلاميه'));
    assert.ok(matches('قراءة وأسئلة', 'قراءه واسيله'));
    assert.equal(matches('الرياضيات', 'فيزياء'), false);
});

test('keeps Latin case-insensitive search and whitespace behavior', () => {
    assert.ok(matches('English Teacher - Grade 6', '  GRADE 6  '));
    assert.equal(matches('English Teacher', 'math'), false);
    assert.equal(normalizeSearchText(null), '');
});

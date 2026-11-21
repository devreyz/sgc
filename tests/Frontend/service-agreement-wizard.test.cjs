const {readFileSync} = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');

const template = readFileSync(path.join(__dirname, '../../resources/views/services/_agreement-create.blade.php'), 'utf8');
const functions = template.slice(template.indexOf('function monthlyDate('), template.indexOf('function refresh()'));

test('monthly installments retain the day where possible and distribute every cent', () => {
    const schedule = vm.runInNewContext(functions + '\nautomaticRows()', {
        count: {value: '3'},
        entry: {value: '10.00'},
        firstDue: {value: '2027-01-31'},
        total: () => 11000,
        cents: value => Math.round(Number(value) * 100),
        edits: new Map(),
    });
    assert.equal(schedule.map(item => item.amount).join(','), '33.34,33.33,33.33');
    assert.equal(schedule.map(item => item.due_date).join(','), '2027-01-31,2027-02-28,2027-03-31');
});

export function filterTeacherGroups(groups, filters = {}) {
    const q = (filters.search || '').trim().toLowerCase();

    return groups.filter(group =>
        (!q || [group.name, group.subject?.name, group.grade?.name].some(value => value?.toLowerCase().includes(q))) &&
        (!filters.type || (filters.type === 'private') === group.is_private) &&
        (!filters.stage || group.grade?.stage === filters.stage) &&
        (!filters.grade || String(group.grade?.id) === String(filters.grade)) &&
        (!filters.track || group.grade?.track === filters.track) &&
        (!filters.subject || String(group.subject?.id) === String(filters.subject))
    );
}

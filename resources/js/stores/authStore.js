import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Auth Store — single source of truth for current user state.
 * No localStorage/sessionStorage usage — data comes from Inertia shared props.
 */
export const useAuthStore = defineStore('auth', () => {
    const page = usePage();

    // Derived from Inertia shared props (set by HandleInertiaRequests middleware)
    const user    = computed(() => page.props.auth?.user ?? null);
    const isGuest = computed(() => !user.value);

    const isStudent = computed(() =>
        user.value?.roles?.includes('student') ?? false
    );

    const isTeacher = computed(() =>
        user.value?.roles?.includes('teacher') ?? false
    );

    const isAdmin = computed(() =>
        user.value?.roles?.includes('admin') ?? false
    );

    const isParent = computed(() =>
        user.value?.roles?.includes('parent') ?? false
    );

    const hasRole = (role) =>
        user.value?.roles?.includes(role) ?? false;

    const canAccessCourse = (courseId) =>
        user.value?.enrolled_course_ids?.includes(courseId) ?? false;

    return {
        user,
        isGuest,
        isStudent,
        isTeacher,
        isAdmin,
        isParent,
        hasRole,
        canAccessCourse,
    };
});

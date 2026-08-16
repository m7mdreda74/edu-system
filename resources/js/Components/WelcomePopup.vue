<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

const page = usePage();
const isOpen = ref(false);

const settings = computed(() => page.props.settings || {});

const isActive = computed(() => settings.value.welcome_popup_active === 'true' || settings.value.welcome_popup_active === true);
const title = computed(() => settings.value.welcome_popup_title || 'أهلاً بك في منصة التفوق التعليمية');

const items = computed(() => {
    return [
        {
            label: settings.value.welcome_popup_item1_label,
            url: settings.value.welcome_popup_item1_url,
            icon: 'student'
        },
        {
            label: settings.value.welcome_popup_item2_label,
            url: settings.value.welcome_popup_item2_url,
            icon: 'payments'
        },
        {
            label: settings.value.welcome_popup_item3_label,
            url: settings.value.welcome_popup_item3_url,
            icon: 'lock'
        },
        {
            label: settings.value.welcome_popup_item4_label,
            url: settings.value.welcome_popup_item4_url,
            icon: 'globe'
        },
        {
            label: settings.value.welcome_popup_item5_label,
            url: settings.value.welcome_popup_item5_url,
            icon: 'globe'
        },
        {
            label: settings.value.welcome_popup_item6_label,
            url: settings.value.welcome_popup_item6_url,
            icon: 'live'
        }
    ].filter(item => item.label && item.url);
});

const bottomLabel = computed(() => settings.value.welcome_popup_bottom_label || 'للمزيد الإطلاع على دليل المستخدم');
const bottomUrl = computed(() => settings.value.welcome_popup_bottom_url || null);

// Video Overlay Player State
const activeVideoUrl = ref(null);
const isVideoModalOpen = ref(false);

function getYoutubeId(url) {
    if (!url) return null;
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    return (match && match[2].length === 11) ? match[2] : null;
}

const embedUrl = computed(() => {
    if (!activeVideoUrl.value) return '';
    const ytId = getYoutubeId(activeVideoUrl.value);
    if (ytId) {
        return `https://www.youtube-nocookie.com/embed/${ytId}?autoplay=1&rel=0`;
    }
    return activeVideoUrl.value;
});

const isDirectVideo = computed(() => {
    if (!activeVideoUrl.value) return false;
    return activeVideoUrl.value.endsWith('.mp4') || activeVideoUrl.value.endsWith('.webm') || activeVideoUrl.value.endsWith('.ogg');
});

function handleItemClick(item, event) {
    const ytId = getYoutubeId(item.url);
    const directVideo = item.url.endsWith('.mp4') || item.url.endsWith('.webm') || item.url.endsWith('.ogg');
    
    if (ytId || directVideo) {
        event.preventDefault(); // Intercept and handle in modal player
        activeVideoUrl.value = item.url;
        isVideoModalOpen.value = true;
    }
}

function closeVideoModal() {
    activeVideoUrl.value = null;
    isVideoModalOpen.value = false;
}

onMounted(() => {
    if (isActive.value) {
        const shown = sessionStorage.getItem('altafawwuq_welcome_popup_shown');
        if (!shown) {
            setTimeout(() => {
                isOpen.value = true;
            }, 800);
        }
    }
});

function closePopup() {
    isOpen.value = false;
    sessionStorage.setItem('altafawwuq_welcome_popup_shown', 'true');
}
</script>

<template>
    <Transition name="fade">
        <div v-if="isOpen" 
             @click.self="closePopup"
             class="modal-overlay z-55 bg-black/60 backdrop-blur-md cursor-pointer"
             dir="rtl"
        >
            
            <!-- Modal Body -->
            <div class="modal-panel relative w-full max-w-2xl bg-gradient-to-br from-primary-900 to-primary-950 text-white rounded-3xl p-8 md:p-10 shadow-2xl border border-primary-800 transform transition-all duration-300 scale-100 flex flex-col items-center cursor-default">
                
                <!-- Close Button -->
                <button @click="closePopup" class="absolute top-6 left-6 text-white/70 hover:text-white transition-colors bg-white/10 hover:bg-white/20 p-2 rounded-full">
                    <Icon name="close" class="w-5 h-5" />
                </button>

                <!-- Platform Logo -->
                <div class="w-16 h-16 rounded-2xl bg-white/10 flex items-center justify-center mb-6 shadow-glow-primary border border-white/20">
                    <span class="text-white font-black text-3xl">ت</span>
                </div>

                <!-- Title -->
                <h2 class="text-2xl md:text-3xl font-black text-center mb-8 leading-tight">
                    {{ title }}
                </h2>

                <!-- Items Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 w-full mb-8">
                    <a v-for="(item, index) in items" :key="index"
                       :href="item.url" target="_blank"
                       @click="handleItemClick(item, $event)"
                       class="relative flex flex-col items-center justify-center p-5 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 hover:border-white/20 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg text-center cursor-pointer"
                    >
                        <!-- Play Indicator for Video links -->
                        <div v-if="getYoutubeId(item.url) || item.url.endsWith('.mp4')" class="absolute top-2.5 left-2.5 bg-accent-500/20 text-accent-400 p-1.5 rounded-full" title="فيديو توضيحي">
                            <Icon name="live" class="w-3.5 h-3.5" />
                        </div>

                        <div class="p-3 bg-white/10 rounded-xl mb-3 text-accent-400">
                            <Icon :name="item.icon" class="w-6 h-6" />
                        </div>
                        <span class="text-xs font-bold leading-relaxed text-white/95">{{ item.label }}</span>
                    </a>
                </div>

                <!-- Bottom Link -->
                <a v-if="bottomLabel && bottomUrl" :href="bottomUrl" target="_blank"
                   class="text-xs font-semibold text-accent-400 hover:text-accent-300 underline underline-offset-4 transition-colors">
                    {{ bottomLabel }}
                </a>
            </div>

            <!-- ── Dynamic Video Player Modal Overlay ───────────────────────── -->
            <Transition name="fade">
                <div v-if="isVideoModalOpen" 
                     @click.self="closeVideoModal"
                     class="modal-overlay z-60 bg-black/85 backdrop-blur-xl cursor-pointer"
                >
                    <div class="modal-panel relative w-full max-w-3xl aspect-video bg-black rounded-3xl overflow-hidden border border-surface-800 shadow-2xl flex items-center justify-center cursor-default">
                        
                        <!-- Close Video Button -->
                        <button @click="closeVideoModal" class="absolute top-4 left-4 z-50 text-white/80 hover:text-white transition-colors bg-white/10 hover:bg-white/20 p-2 rounded-full">
                            <Icon name="close" class="w-5 h-5" />
                        </button>

                        <!-- Video Player Iframe / Tag -->
                        <iframe v-if="!isDirectVideo && embedUrl" :src="embedUrl" 
                                class="w-full h-full border-0" 
                                referrerpolicy="strict-origin-when-cross-origin"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                allowfullscreen></iframe>
                                
                        <video v-else-if="isDirectVideo && activeVideoUrl" 
                               :src="activeVideoUrl" 
                               controls autoplay 
                               class="w-full h-full object-contain"></video>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
.z-55 {
    z-index: 55;
}
.z-60 {
    z-index: 60;
}
</style>

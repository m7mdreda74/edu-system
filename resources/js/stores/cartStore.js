import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

/**
 * Cart Store — manages checkout cart (single course for now).
 * No localStorage — cart is ephemeral and server-validated on checkout.
 */
export const useCartStore = defineStore('cart', () => {
    const savedItem = typeof window !== 'undefined' ? localStorage.getItem('altafawwuq_cart_item') : null;
    const item   = ref(savedItem ? JSON.parse(savedItem) : null);
    const coupon = ref(null);   // { code, discountPercent }

    const hasItem     = computed(() => item.value !== null);
    const hasCoupon   = computed(() => coupon.value !== null);

    const originalPrice = computed(() =>
        item.value?.price ?? 0
    );

    const discountAmount = computed(() => {
        if (!hasCoupon.value || !item.value) return 0;
        return Math.round(originalPrice.value * coupon.value.discountPercent / 100);
    });

    /** Final price in halala/cents — integer arithmetic only */
    const finalPrice = computed(() =>
        Math.max(0, originalPrice.value - discountAmount.value)
    );

    /** Price formatted for display (divide by 100 for QAR) */
    const finalPriceFormatted = computed(() => {
        const qar = finalPrice.value / 100;
        return new Intl.NumberFormat('ar-QA', {
            style: 'currency',
            currency: 'QAR',
            minimumFractionDigits: 0,
        }).format(qar);
    });

    function addToCart(course) {
        item.value = {
            courseId:      course.id,
            title:         course.title,
            price:         course.effective_price,  // from server in halala
            thumbnail:     course.thumbnail,
            slug:          course.slug,
        };
        if (typeof window !== 'undefined') {
            localStorage.setItem('altafawwuq_cart_item', JSON.stringify(item.value));
        }
        coupon.value = null;
    }

    function applyCoupon(couponData) {
        coupon.value = {
            code:            couponData.code,
            discountPercent: couponData.discount_percent,
        };
    }

    function removeCoupon() { coupon.value = null; }

    function clearCart() {
        item.value   = null;
        coupon.value = null;
        if (typeof window !== 'undefined') {
            localStorage.removeItem('altafawwuq_cart_item');
        }
    }

    return {
        item,
        coupon,
        hasItem,
        hasCoupon,
        originalPrice,
        discountAmount,
        finalPrice,
        finalPriceFormatted,
        addToCart,
        applyCoupon,
        removeCoupon,
        clearCart,
    };
});

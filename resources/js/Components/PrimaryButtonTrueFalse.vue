<script setup>
defineProps({
    type: {
        type: Boolean,
        default: 'submit',
    },
});
</script>

<template>
                <div 
                          class="
                            mx-8
                            shadow
                            rounded-full
                            h-10
                            mt-4
                            flex
                            p-1
                            relative
                            items-center
                          "
                        >
                          <div class="w-full flex justify-center">
                            <button @click.prevent="type = false">كلا</button>
                          </div>
                          <div class="w-full flex justify-center">
                            <button @click.prevent="type =true">{{ $t('yes') }}</button>
                          </div>
                          <span
                            :class="type == true ? ' left-90' : ' left-1'"
                            class="
                              elSwitch
                              left-[100px]
                              bg-green-600
                              shadow
                              text-white
                              flex
                              items-center
                              justify-center
                              w-1/2
                              rounded-full
                              h-8
                              transition-all
                              top-[4px]
                              absolute
                            "
                          >
                            {{ type==false ? 'كلا': 'نعم' }}
                          </span>
                        </div>
</template>

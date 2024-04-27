<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import Dropdown from "@/Components/Dropdown.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import { ref } from "vue";

const selectedDate = ref('');
const appointments = ref([{ start: props.appointment.start, end: props.appointment.end }]);
const bookedSlots = ref([]);


const props = defineProps({
    url:String,
    userDoctor:Array,
    appointment:Object,
});

const form = useForm({
    id:props.appointment.id,
    user_id: props.appointment.user_id,
    card_id: props.appointment.card_id,
    date:  (new Date(props.appointment.start)).toISOString().split("T")[0],
    start:props.appointment.start,
    end:props.appointment.end,
});

const submit = () => {
    form.post(route('hospitalStoreEdit'),form);
};


const timeSlots = (() => {
  const slots = [];
  for (let i = 9; i <= 16; i += 1) {
    slots.push(`${i}:00-${i + 1}:00`);
  }
  return slots;
})();

const isSlotAvailable = (slot) => {
  if (!form.date) return false; // disable all slots if no date selected
  if (new Date(form.date).getDay() === 5) return false; // disable all slots if Friday

  // const today = new Date();

  // const selected = new Date(form.date);

  // if (selected < today) {
  //   return false; // disable slots for past dates
  // }

    const start = new Date(`${form.date} ${slot.split('-')[0]}:00`);
    const end = new Date(`${form.date} ${slot.split('-')[1]}:00`);
    const overlap = appointments.value.some((appointment) => {
    const apptStart = new Date(appointment.start);
    const apptEnd = new Date(appointment.end);

    return (start >= apptStart && start < apptEnd) || (end > apptStart && end <= apptEnd);
  });

  return !overlap && !bookedSlots.value.includes(slot);
};

const resetBookedSlots = () => {
  bookedSlots.value = [];
  if (form.date) {
    // Your HTTP request logic here
  }
};

const bookAppointment = (slot, type) => {
  appointments.value=[];
  const start = form.date+' '+slot.split('-')[0]+':00';
  const end =  form.date+' '+slot.split('-')[1]+':00';
  form.start = start
  form.end = end
  appointments.value.push({ start, end });
  bookedSlots.value.push(slot);
  const slots = slot.split('-')[0]; // "9:00"
  const hour = slots.split(':')[0]; // "9"
  resetBookedSlots()
  // Your HTTP request logic here
};


</script>
<template>
      <Head title="Dashboard" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-200">
         حجز وتثبيت موعد للمستخدمين 
      </h2>
    </template>
    <div v-if="$page.props.success">
      <div
        id="alert-2"
        class="p-4 mb-4 bg-red-100 rounded-lg dark:bg-red-200 text-center"
        role="alert"
      >
        <div class="ml-3 text-sm font-medium text-red-700 dark:text-red-800">
          {{ $page.props.success }}
        </div>
      </div>
    </div>
    <div class="max-w-8xl mx-auto sm:px-3 lg:px-4 mt-4">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6  dark:bg-gray-900">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-2 lg:gap-4">
                <div class="px-4">
                  <select v-model="form.user_id" id="default" class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 mb-6 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-red-500 dark:focus:border-red-500">
                    <option value="" disabled>يرجى اختيار طبيب</option>
                    <option v-for="(user, index) in userDoctor" :key="index" :value="user.id">{{ user.name }}</option>
                  </select>
                </div>
                <div class=" px-4">
                  <form class="items-center max-w-5xl">
                    <div class="relative w-full">
                      <div
                        class="
                          absolute
                          inset-y-0
                          left-0
                          flex
                          items-center
                          pl-3
                          pointer-events-none
                        "
                      >
                      </div>
                      <input
                        v-model="form.card_id"
                        type="number"
                        id="simple-search"
                        class="
                          bg-gray-50
                          border border-gray-300
                          text-gray-900 text-sm
                          rounded-lg
                          focus:ring-blue-500 focus:border-blue-500
                          block
                          w-full
                          pl-10
                          p-2.5
                          dark:bg-gray-700
                          dark:border-gray-600
                          dark:placeholder-gray-400
                          dark:text-white
                          dark:focus:ring-blue-500
                          dark:focus:border-blue-500
                        "
                        placeholder="رقم بطاقة المريض"
                        required
                      />
                    </div>
                    
                  </form>
                </div>
                <div class=" px-5">
                    <h5 class="py-3">اليوم</h5>
                    <input type="date" class="form-control w-full " v-model="form.date" @change="resetBookedSlots" />
                    </div>
                    <div class="  px-5">
                    <h5 class="py-3">الموعد</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-8">
                        <div  v-for="(slot, index) in timeSlots" :key="index">
                        <button class="px-6 py-2 text-white bg-rose-500 rounded-md focus:outline-none w-full" :disabled="!isSlotAvailable(slot)" @click="bookAppointment(slot, 'vip')">
                            {{ slot }}
                        </button>
                        </div>
                    </div>
                  </div>
                <div class=" px-5 py-7 pt-12 ">
                <button type="date" class="px-6 py-2 text-white bg-blue-500 rounded-md focus:outline-none w-full"  @click="submit" :disabled="!form.start || !form.end || !form.user_id || !form.card_id">حفظ</button>
                </div>
            </div>
          </div>
        </div>
    </div>
  </AuthenticatedLayout>
  </template>
  <style scoped>
    button:disabled{
        background-color: rgb(245, 135, 135) !important;
    }
  </style>
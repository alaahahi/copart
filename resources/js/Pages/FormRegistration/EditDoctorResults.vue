<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import Dropdown from "@/Components/Dropdown.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import ModaSave from "@/Components/ModaSave.vue";

import { ref } from "vue";
const show = ref(true);

const form = useForm({
  husband_talasyma: props.data.husband_talasyma,
  husband_faqar:  props.data.husband_faqar,
  husband_himofilya:  props.data.husband_himofilya,
  husband_al:  props.data.husband_al,
  husband_dam:  props.data.husband_dam,
  husband_note:  props.data.husband_note,
  husband_results:props.data.husband_results,
  wife_talasyma:  props.data.wife_talasyma,
  wife_faqar:  props.data.wife_faqar,
  wife_himofilya:  props.data.wife_himofilya,
  wife_al:  props.data.wife_al,
  wife_dam:  props.data.wife_dam,
  wife_results:props.data.wife_results,
  wife_note:  props.data.wife_note,
});
const props = defineProps({
  is_doctor: Boolean,
  profile: Object,
  profiles: Array,
  data:Object,
  profile_id: Number,
  message: String,
  url: String,
});
const accepted = () => {
  submit();
};
const rejected = () => {
  submit();
};
const submit = () => {
  form.post(route("resultsDoctorEdit",props.data.id));
};
const setTimeou = () => {
  window.setTimeout(() => (show.value = null), 15000);
};
let showModal = ref(false);
let showModal1 = ref(false);
function confirm() {
  accepted();
  showModal.value = false;
}
function confirm1() {
  rejected();
  showModal.value = false;
}

function open() {
  showModal.value = true;
}
function open1() {
  showModal1.value = true;
}
</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-200">
        محافظة كركوك - نتائج الطبيب
        {{ husband_results }}
      </h2>
    </template>
    <ModaSave
      :show="showModal ? true : false"
      :data="showModal"
      @a="confirm($event)"
      @close="showModal = false"
    >
      <template #header>
        <h3 class="text-center dark:text-gray-200fw-10">هل انت متأكد من تعديل نتائج الطبيب</h3>
      </template>
    </ModaSave>
    <ModaSave
      :show="showModal1 ? true : false"
      :data="showModal1"
      @a="confirm1($event)"
      @close="showModal1 = false"
    >
      <template #header>
        <h3 class="text-center dark:text-gray-200fw-10 text-red-500">
          هل انت متأكد من رفض نتائج التحاليل
        </h3>
      </template>
    </ModaSave>
    <div v-if="$page.props.flash.message && show" v-bind="setTimeou()">
      <div
        id="alert-2"
        class="p-4 mb-4 bg-red-100 rounded-lg dark:bg-red-200 text-center"
        role="alert"
      >
        <div class="ml-3 text-sm font-medium text-red-700 dark:text-red-800">
          {{ $page.props.flash.message }}
        </div>
      </div>
    </div>
    <div class="flex flex-row text-center">
      <div class="basis-1/2">
        <div class="max-w-7xl mt-4 mx-auto sm:px-6 lg:px-8 text-center">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6  dark:bg-gray-900">
              اسم الزوج:
              {{ profiles.husband_name }}
            </div>
          </div>
        </div>
      </div>
      <div class="basis-1/2">
        <div class="max-w-7xl mt-4 mx-auto sm:px-6 lg:px-8 text-center">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6  dark:bg-gray-900">
              اسم الزوجة:
              {{ profiles.wife_name }}
            </div>
          </div>
        </div>
      </div>
    </div>
    <form name="createForm" @submit.prevent="submit">
      <div class="flex flex-row text-center">
        <div class="basis-1/2">
          <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
              <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6  dark:bg-gray-900">
                  <h2 class="text-center dark:text-gray-200text-xl py-2">
                    نتائج الفحوصات المختبرية للزوج
                  </h2>
                  <div className="flex flex-col  pt-8" style="direction: ltr">
                    <div class="flex flex-row text-center">
                      <div class="basis-1/4">
                        <div className="mb-4 px-3">
                          <InputLabel for="husband_b" value="B- group & RH " />
                          <TextInput
                            id="husband_b"
                            type="text"
                            class="mt-1 block w-full"
                            :value="profile.husband_b"
                            disabled
                          />
                        </div>
                      </div>
                      <div class="basis-1/4">
                        <div className="mb-4 px-3">
                          <InputLabel for="husband_hb" value="Hb ( g / L) (11-15)" />

                          <TextInput
                            id="husband_hb"
                            type="text"
                            class="mt-1 block w-full"
                            :value="profile.husband_hb"
                            disabled
                            autofocus
                          />
                        </div>
                      </div>
                      <div class="basis-1/4">
                        <div className="mb-4 px-3">
                          <InputLabel for="husband_mcv" value="MCV (83-101)" />

                          <TextInput
                            id="husband_mcv"
                            type="text"
                            class="mt-1 block w-full"
                            :value="profile.husband_mcv"
                            disabled
                          />
                        </div>
                      </div>
                      <div class="basis-1/4">
                        <div className="mb-4 px-3">
                          <InputLabel for="husband_mch" value="MCH (27-32)" />
                          <TextInput
                            id="husband_mch"
                            type="text"
                            class="mt-1 block w-full"
                            :value="profile.husband_mch"
                            disabled
                          />
                        </div>
                      </div>
                    </div>
       
                    <div class="flex flex-row text-center">
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel
                            for="husband_tb"
                            value="TB"
                            class="mb-2"
                          />
                          <span
                            :class="
                              profile.husband_tb == 'positive'
                                ? 'text-red-600  font-bold'
                                : 'text-green-600'
                            "
                            >{{ profile.husband_tb }}</span
                          >
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel
                            for="husband_syphilis"
                            value="Syphilis"
                            class="mb-2"
                          />
                          <span
                            :class="
                              profile.husband_syphilis == 'positive'
                                ? 'text-red-600  font-bold'
                                : 'text-green-600'
                            "
                            >{{ profile.husband_syphilis }}</span
                          >
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel
                            for="husband_tpha"
                            value="TPHA"
                            class="mb-2"
                          />
                          <span
                            :class="
                              profile.husband_tpha == 'positive'
                                ? 'text-red-600  font-bold'
                                : 'text-green-600'
                            "
                            >{{ profile.husband_tpha }}</span
                          >
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-row text-center">
                      <div class="basis-1/3">
                        <div className="mb-4 px-3 ">
                          <InputLabel
                            for="husband_hbs"
                            value="HBs Ag"
                            class="mb-2"
                          />
                          <span
                            :class="
                              profile.husband_hbs == 'positive'
                                ? 'text-red-600  font-bold'
                                : 'text-green-600'
                            "
                            >{{ profile.husband_hbs }}</span
                          >
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3 ">
                          <InputLabel
                            for="husband_hcv"
                            value="HCV"
                            class="mb-2"
                          />
                          <span
                            :class="
                              profile.husband_hcv == 'positive'
                                ? 'text-red-600  font-bold'
                                : 'text-green-600'
                            "
                            >{{ profile.husband_hcv }}</span
                          >
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3 ">
                          <InputLabel
                            for="husband_hiv"
                            value="HIV"
                            class="mb-2"
                          />
                          <span
                            :class="
                              profile.husband_hiv == 'positive'
                                ? 'text-red-600  font-bold'
                                : 'text-green-600'
                            "
                            >{{ profile.husband_hiv }}</span
                          >
                        </div>
                      </div>
                    </div>
                    <h2 class="text-center dark:text-gray-200text-xl py-8">
                      تقيم الطبيب لنتائج الفحوصات للزوج
                    </h2>
                    <div class="flex flex-row text-center">
                      <div class="basis-1/2">
                        <div className="mb-4 px-3">
                          <label
                            class="
                              inline-flex
                              relative
                              items-center
                              cursor-pointer
                            "
                          >
                            <span
                              class="
                                ml-3
                                text-sm
                                font-medium
                                text-gray-900
                                dark:text-gray-300
                              "
                              >الثلاسيميا</span
                            >
                          </label>
                          <div
                          class="
                            shadow
                            rounded-full
                            h-10
                            flex
                            p-1
                            relative
                            items-center
                          "
                          style="width: 240px;"
                        >
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.husband_talasyma = 0">كلا</button>
                          </div>
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.husband_talasyma =1">{{ $t('yes') }}</button>
                          </div>
                          <span
                            :class="form.husband_talasyma == 1 ?  'left-[100px]' : 'left-[1px]'"
                            class="
                              elSwitch
                               
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
                            {{ form.husband_talasyma==0 ? 'كلا': 'نعم' }}
                          </span>
                          </div>
                        </div>
                      </div>
                      <div class="basis-1/2">
                        <div className="mb-4 px-3">
                          <label
                            class="
                              inline-flex
                              relative
                              items-center
                              cursor-pointer
                            "
                          >
                            <span
                              class="
                                ml-3
                                text-sm
                                font-medium
                                text-gray-900
                                dark:text-gray-300
                              "
                            >
                              فقر الدم المنجلي</span
                            >
                          </label>
                          <div
                          class="
                            shadow
                            rounded-full
                            h-10
                            flex
                            p-1
                            relative
                            items-center
                          "
                          style="width: 240px;"
                        >
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.husband_faqar = 0">كلا</button>
                          </div>
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.husband_faqar =1">{{ $t('yes') }}</button>
                          </div>
                          <span
                            :class="form.husband_faqar ==1 ?  'left-[100px]'  : 'left-[1px]'"
                            class="
                              elSwitch
                              
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
                            {{ form.husband_faqar==0 ? 'كلا': 'نعم' }}
                          </span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-row text-center">
                      <div class="basis-1/2">
             

                        <div className="mb-4 px-3">
                          <label
                            class="
                              inline-flex
                              relative
                              items-center
                              cursor-pointer
                            "
                          >
                            
                            <span
                              class="
                                ml-3
                                text-sm
                                font-medium
                                text-gray-900
                                dark:text-gray-300
                              "
                            >
                              العوق الذهني
                            </span>
                          </label>
                          <div
                          class="
                            shadow
                            rounded-full
                            h-10
                            flex
                            p-1
                            relative
                            items-center
                          "
                          style="width: 240px;"
                        >
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.husband_al = 0">كلا</button>
                          </div>
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.husband_al =1">{{ $t('yes') }}</button>
                          </div>
                          <span
                            :class="form.husband_al == 1 ?  'left-[100px]' : 'left-[1px]'"
                            class="
                              elSwitch
                               
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
                            {{ form.husband_al==0 ? 'كلا': 'نعم' }}
                          </span>
                          </div>
                        </div>
                      </div>
                      <div class="basis-1/2">
                        <div className="mb-4 px-3">
                          <label
                            class="
                              inline-flex
                              relative
                              items-center
                              cursor-pointer
                            "
                          >
                            <span
                              class="
                                ml-3
                                text-sm
                                font-medium
                                text-gray-900
                                dark:text-gray-300
                              "
                            >
                              داء السكري
                            </span>
                          </label>
                          <div
                          class="
                            shadow
                            rounded-full
                            h-10
                            flex
                            p-1
                            relative
                            items-center
                          "
                          style="width: 240px;"
                        >
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.husband_dam = 0">كلا</button>
                          </div>
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.husband_dam =1">{{ $t('yes') }}</button>
                          </div>
                          <span
                            :class="form.husband_dam == 1 ?  'left-[100px]' : 'left-[1px]'"
                            class="
                              elSwitch
                               
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
                            {{ form.husband_dam==0 ? 'كلا': 'نعم' }}
                          </span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-row text-center">
                    <div class="basis-1/2">
                        <div className="mb-4 px-3">
                          <label
                            class="
                              inline-flex
                              relative
                              items-center
                              cursor-pointer
                            "
                          >
                            <input
                              type="checkbox"
                              v-model="form.husband_himofilya"
                              value="form.husband_himofilya"
                              class="sr-only peer"
                            />
                            <span
                              class="
                                ml-3
                                text-sm
                                font-medium
                                text-gray-900
                                dark:text-gray-300
                              "
                              >الهيموفيليا</span
                            >
                          </label>
                          <div
                          class="
                            shadow
                            rounded-full
                            h-10
                            flex
                            p-1
                            relative
                            items-center
                          "
                          style="width: 240px;"
                        >
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.husband_himofilya = false">كلا</button>
                          </div>
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.husband_himofilya =1">{{ $t('yes') }}</button>
                          </div>
                          <span
                            :class="form.husband_himofilya == 1 ? 'left-[100px]' : 'left-[1px]'"
                            class="
                              elSwitch
                               
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
                            {{ form.husband_himofilya==0? 'كلا': 'نعم' }}
                          </span>
                          </div>
                        </div>
                      </div>
                      <div class="basis-1/2">
                        <div className="mb-4 px-3">
                          <label
                            class="
                              inline-flex
                              relative
                              items-center
                              cursor-pointer
                            "
                          >
                            <input
                              type="checkbox"
                              v-model="form.husband_results"
                              value="form.husband_results"
                              class="sr-only peer"
                            />
                            <span
                              class="
                                ml-3
                                text-sm
                                font-medium
                                text-gray-900
                                dark:text-gray-300
                              "
                              >نتيجة الزوج</span
                            >
                          </label>
                          <div
                          class="
                            shadow
                            rounded-full
                            h-10
                            flex
                            p-1
                            relative
                            items-center
                          "
                          style="width: 240px;"
                        >
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.husband_results = 1">سليم</button>
                          </div>
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.husband_results =0">كلا</button>
                          </div>
                          <span
                            :class="form.husband_results == 0 ?  'left-[100px]' : 'left-[1px]'"
                            class="
                              elSwitch
                               
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
                            {{ form.husband_results==1 ? 'سليم': 'كلا' }}
                          </span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="row" style="direction: rtl">
                      <label
                        for="message"
                        class="
                          block
                          mb-2
                          text-sm
                          font-medium
                          text-gray-900
                          dark:text-white
                        "
                        >ملاحظات اضافية لطبيب بالنسبة للزوج</label
                      >
                      <textarea
                        v-model="form.husband_note"
                        rows="4"
                        class="
                          block
                          p-2.5
                          w-full
                          text-sm text-gray-900
                          bg-gray-50
                          rounded-lg
                          border border-gray-300
                          focus:ring-blue-500 focus:border-blue-500
                          dark:bg-gray-700
                          dark:border-gray-600
                          dark:placeholder-gray-400
                          dark:text-white
                          dark:focus:ring-blue-500
                          dark:focus:border-blue-500
                        "
                        placeholder="تقرير الطبيب .."
                      ></textarea>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="basis-1/2">
          <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
              <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6  dark:bg-gray-900">
                  <h2 class="text-center dark:text-gray-200text-xl py-2">
                    نتائج الفحوصات المختبرية للزوجة
                  </h2>
                  <div className="flex flex-col  pt-8" style="direction: ltr">
                    <div class="flex flex-row text-center">
                      <div class="basis-1/4">
                        <div className="mb-4 px-3">
                          <InputLabel for="wife_b" value="B- group & RH " />
                          <TextInput
                            id="wife_b"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.wife_b"
                            :value="profile.wife_b"
                            disabled
                          />
                        </div>
                      </div>
                      <div class="basis-1/4">
                        <div className="mb-4 px-3">
                          <InputLabel for="wife_hb" value="Hb ( g / L) (11-15)" />

                          <TextInput
                            id="wife_hb"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.wife_hb"
                            :value="profile.wife_hb"
                            disabled
                            autofocus
                          />
                        </div>
                      </div>
                      <div class="basis-1/4">
                        <div className="mb-4 px-3">
                          <InputLabel for="wife_mcv" value="MCV (83-101)" />

                          <TextInput
                            id="wife_mcv"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.wife_mcv"
                            :value="profile.wife_mcv"
                            disabled
                          />
                        </div>
                      </div>
                      <div class="basis-1/4">
                        <div className="mb-4 px-3">
                          <InputLabel for="wife_mch" value="MCH (27-32)" />
                          <TextInput
                            id="wife_mch"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.wife_mch"
                            :value="profile.wife_mch"
                            disabled
                          />
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-row text-center">
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="wife_tb" value="TB" class="mb-2" />
                          <span
                            :class="
                              profile.wife_tb == 'positive'
                                ? 'text-red-600  font-bold'
                                : 'text-green-600'
                            "
                            >{{ profile.wife_tb }}</span
                          >
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel
                            for="wife_syphilis"
                            value="Syphilis"
                            class="mb-2"
                          />
                          <span
                            :class="
                              profile.wife_syphilis == 'positive'
                                ? 'text-red-600  font-bold'
                                : 'text-green-600'
                            "
                            >{{ profile.wife_syphilis }}</span
                          >
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel
                            for="wife_tpha"
                            value="TPHA"
                            class="mb-2"
                          />
                          <span
                            :class="
                              profile.wife_tpha == 'positive'
                                ? 'text-red-600  font-bold'
                                : 'text-green-600'
                            "
                            >{{ profile.wife_tpha }}</span
                          >
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-row text-center">
                      <div class="basis-1/3">
                        <div className="mb-4 px-3 ">
                          <InputLabel
                            for="wife_hbs"
                            value="HBs Ag"
                            class="mb-2"
                          />
                          <span
                            :class="
                              profile.wife_hbs == 'positive'
                                ? 'text-red-600  font-bold'
                                : 'text-green-600'
                            "
                            >{{ profile.wife_hbs }}</span
                          >
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3 ">
                          <InputLabel for="wife_hcv" value="HCV" class="mb-2" />
                          <span
                            :class="
                              profile.wife_hcv == 'positive'
                                ? 'text-red-600  font-bold'
                                : 'text-green-600'
                            "
                            >{{ profile.wife_hcv }}</span
                          >
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3 ">
                          <InputLabel for="wife_hiv" value="HIV" class="mb-2" />
                          <span
                            :class="
                              profile.wife_hiv == 'positive'
                                ? 'text-red-600  font-bold'
                                : 'text-green-600'
                            "
                            >{{ profile.wife_hiv }}</span
                          >
                        </div>
                      </div>
                    </div>
                    <h2 class="text-center dark:text-gray-200text-xl py-8">
                      تقيم الطبيب لنتائج الفحوصات للزوجة
                    </h2>
                    <div class="flex flex-row text-center">
                      <div class="basis-1/2">
                        <div className="mb-4 px-3">
                          <label
                            class="
                              inline-flex
                              relative
                              items-center
                              cursor-pointer
                            "
                          >
                            <span
                              class="
                                ml-3
                                text-sm
                                font-medium
                                text-gray-900
                                dark:text-gray-300
                              "
                              >الثلاسيميا</span
                            >
                          </label>
                          <div
                          class="
                            shadow
                            rounded-full
                            h-10
                            flex
                            p-1
                            relative
                            items-center
                          "
                          style="width: 240px;"
                        >
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.wife_talasyma = 0">كلا</button>
                          </div>
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.wife_talasyma =1">{{ $t('yes') }}</button>
                          </div>
                          <span
                            :class="form.wife_talasyma == 1 ?  'left-[100px]' : 'left-[1px]'"
                            class="
                              elSwitch
                               
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
                            {{ form.wife_talasyma==0 ? 'كلا': 'نعم' }}
                          </span>
                          </div>
                        </div>
                      </div>
                      <div class="basis-1/2">
                        <div className="mb-4 px-3">
                          <label
                            class="
                              inline-flex
                              relative
                              items-center
                              cursor-pointer
                            "
                          >
                            <span
                              class="
                                ml-3
                                text-sm
                                font-medium
                                text-gray-900
                                dark:text-gray-300
                              "
                            >
                              فقر الدم المنجلي</span
                            >
                          </label>
                          <div
                          class="
                            shadow
                            rounded-full
                            h-10
                            flex
                            p-1
                            relative
                            items-center
                          "
                          style="width: 240px;"
                        >
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.wife_faqar = 0">كلا</button>
                          </div>
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.wife_faqar =1">{{ $t('yes') }}</button>
                          </div>
                          <span
                            :class="form.wife_faqar == 1 ?  'left-[100px]' : 'left-[1px]'"
                            class="
                              elSwitch
                               
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
                            {{ form.wife_faqar==0 ? 'كلا': 'نعم' }}
                          </span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-row text-center">
                      <div class="basis-1/2">
             

                        <div className="mb-4 px-3">
                          <label
                            class="
                              inline-flex
                              relative
                              items-center
                              cursor-pointer
                            "
                          >
                            
                            <span
                              class="
                                ml-3
                                text-sm
                                font-medium
                                text-gray-900
                                dark:text-gray-300
                              "
                            >
                              العوق الذهني
                            </span>
                          </label>
                          <div
                          class="
                            shadow
                            rounded-full
                            h-10
                            flex
                            p-1
                            relative
                            items-center
                          "
                          style="width: 240px;"
                        >
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.wife_al = 0">كلا</button>
                          </div>
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.wife_al =1">{{ $t('yes') }}</button>
                          </div>
                          <span
                            :class="form.wife_al == 1 ?  'left-[100px]' : 'left-[1px]'"
                            class="
                              elSwitch
                               
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
                            {{ form.wife_al==0 ? 'كلا': 'نعم' }}
                          </span>
                          </div>
                        </div>
                      </div>
                      <div class="basis-1/2">
                        <div className="mb-4 px-3">
                          <label
                            class="
                              inline-flex
                              relative
                              items-center
                              cursor-pointer
                            "
                          >
                            <span
                              class="
                                ml-3
                                text-sm
                                font-medium
                                text-gray-900
                                dark:text-gray-300
                              "
                            >
                              داء السكري
                            </span>
                          </label>
                          <div
                          class="
                            shadow
                            rounded-full
                            h-10
                            flex
                            p-1
                            relative
                            items-center
                          "
                          style="width: 240px;"
                        >
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.wife_dam = 0">كلا</button>
                          </div>
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.wife_dam =1">{{ $t('yes') }}</button>
                          </div>
                          <span
                            :class="form.wife_dam == 1 ?  'left-[100px]' : 'left-[1px]'"
                            class="
                              elSwitch
                               
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
                            {{ form.wife_dam==0 ? 'كلا': 'نعم' }}
                          </span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-row text-center">
                    <div class="basis-1/2">
                        <div className="mb-4 px-3">
                          <label
                            class="
                              inline-flex
                              relative
                              items-center
                              cursor-pointer
                            "
                          >
                            <input
                              type="checkbox"
                              v-model="form.wife_himofilya"
                              value="form.wife_himofilya"
                              class="sr-only peer"
                            />
                            <span
                              class="
                                ml-3
                                text-sm
                                font-medium
                                text-gray-900
                                dark:text-gray-300
                              "
                              >الهيموفيليا</span
                            >
                          </label>
                          <div
                          class="
                            shadow
                            rounded-full
                            h-10
                            flex
                            p-1
                            relative
                            items-center
                          "
                          style="width: 240px;"
                        >
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.wife_himofilya = 0">كلا</button>
                          </div>
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.wife_himofilya =1">{{ $t('yes') }}</button>
                          </div>
                          <span
                            :class="form.wife_himofilya == 1 ?  'left-[100px]' : 'left-[1px]'"
                            class="
                              elSwitch
                               
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
                            {{ form.wife_himofilya==0 ? 'كلا': 'نعم' }}
                          </span>
                          </div>
                        </div>
                      </div>
                      <div class="basis-1/2">
                        <div className="mb-4 px-3">
                          <label
                            class="
                              inline-flex
                              relative
                              items-center
                              cursor-pointer
                            "
                          >
                            <input
                              type="checkbox"
                              v-model="form.wife_results"
                              value="form.wife_results"
                              class="sr-only peer"
                            />
                            <span
                              class="
                                ml-3
                                text-sm
                                font-medium
                                text-gray-900
                                dark:text-gray-300
                              "
                              >نتيجة الزوجة </span
                            >
                          </label>
                          <div
                          class="
                            shadow
                            rounded-full
                            h-10
                            flex
                            p-1
                            relative
                            items-center
                          "
                          style="width: 240px;"
                        >
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.wife_results = 1">سليم</button>
                          </div>
                          <div class="w-full flex justify-center">
                            <button @click.prevent="form.wife_results =0">كلا</button>
                          </div>
                          <span
                            :class="form.wife_results == 0 ?  'left-[100px]' : 'left-[1px]'"
                            class="
                              elSwitch
                               
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
                            {{ form.wife_results==1 ? 'سليم': 'كلا' }}
                          </span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="row" style="direction: rtl">
                      <label
                        for="message"
                        class="
                          block
                          mb-2
                          text-sm
                          font-medium
                          text-gray-900
                          dark:text-white
                        "
                        >ملاحظات اضافية لطبيب بالنسبة للزوجة</label
                      >
                      <textarea
                        rows="4"
                        v-model="form.wife_note"
                        class="
                          block
                          p-2.5
                          w-full
                          text-sm text-gray-900
                          bg-gray-50
                          rounded-lg
                          border border-gray-300
                          focus:ring-blue-500 focus:border-blue-500
                          dark:bg-gray-700
                          dark:border-gray-600
                          dark:placeholder-gray-400
                          dark:text-white
                          dark:focus:ring-blue-500
                          dark:focus:border-blue-500
                        "
                        placeholder="تقرير الطبيب .."
                      ></textarea>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </form>
    <div className="flex items-center justify-center my-6 ">
      <Link
        className="px-6 mx-2 w-24 py-2 mb-12 text-white bg-gray-500 rounded-md focus:outline-none rounded"
        :href="route('FormRegistrationCompleted')"
      >
        العودة
      </Link>
      <button
        type="button"
        @click="open()"
        className="px-6  w-24 mb-12 mx-2 py-2 font-bold text-white bg-rose-500 rounded"
      >
        تعديل
      </button>
    </div>
  </AuthenticatedLayout>
</template>
<style>
.w-14 {
  min-width: 56px;
}
</style>

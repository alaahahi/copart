<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import ModaSave from "@/Components/ModaSave.vue";

import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import Dropdown from "@/Components/Dropdown.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import { ref } from "vue";

const form = useForm({
  husband_b: "",
  husband_hb: "",
  husband_mcv: "",
  husband_mch: "",
  husband_hemoglobin_a: "",
  husband_hemoglobin_f: "",
  husband_hemoglobin_a2: "",
  husband_hbs: "",
  husband_hcv: "",
  husband_hiv: "",
  husband_tb: "",
  husband_syphilis: "",
  husband_tpha: "",
  husband_results:"",
  wife_b: "",
  wife_hb: "",
  wife_mcv: "",
  wife_mch: "",
  wife_hemoglobin_a: "",
  wife_hemoglobin_f: "",
  wife_hemoglobin_a2: "",
  wife_hbs: "",
  wife_hcv: "",
  wife_hiv: "",
  wife_tb: "",
  wife_syphilis: "",
  wife_tpha: "",
  wife_results:"",
  created_at: "",
  updated_at: "",
  user_id: "",
  profile_id: props.profile_id,
});
const props = defineProps({
  usersType: Array,
  profile: Array,
  profile_id: Number,
});
let certification = ref([
  { key: "positive", name: "Positive" },
  { key: "negative", name: "Negative" },
]);
let certificationTb = ref([
  { key: "Non seen(N)", name: "Non seen(N)" },
  { key: "No AFB seen(N)", name: "No AFB seen(N)" },
  { key: "No Organism seen(N)", name: "No Organism seen(N)" },
  { key: "positive", name: "Positive" },
  { key: "negative", name: "Negative" },
]);
let certificationWithNone = ref([
  { key: "", name: "" },
  { key: "positive", name: "Positive" },
  { key: "negative", name: "Negative" },
]);

let bloodType = ref([
  { key: "A+", name: "A+" },
  { key: "A-", name: "A-" },
  { key: "B+", name: "B+" },
  { key: "B-", name: "B-" },
  { key: "AB+", name: "AB+" },
  { key: "AB-", name: "AB-" },
  { key: "O+", name: "O+" },
  { key: "O-", name: "O-" },
]);

const submit = () => {
  form.post(route("results", 2));
};
let showModal = ref(false);
function confirm() {
  submit();
  showModal.value = false;
}
function open() {
  showModal.value = true;
}
</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-200">
        محافظة كركوك - نتائج الفحوص المختبرية
      </h2>
    </template>
    <ModaSave
      :show="showModal ? true : false"
      :data="showModal"
      @a="confirm($event)"
      @close="showModal = false"
    >
      <template #header>
        <h3 class="text-center dark:text-gray-200fw-10">هل انت متأكد من حفظ التغيرات</h3>
      </template>
    </ModaSave>
    <div class="flex flex-row">
      <div class="basis-1/2">
        <div class="max-w-7xl mt-4 mx-auto sm:px-6 lg:px-8 text-center">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6  dark:bg-gray-900">
              اسم الزوج:
              {{ profile.husband_name }}
            </div>
          </div>
        </div>
      </div>
      <div class="basis-1/2">
        <div class="max-w-7xl mt-4 mx-auto sm:px-6 lg:px-8 text-center">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6  dark:bg-gray-900">
              اسم الزوجة:
              {{ profile.wife_name }}
            </div>
          </div>
        </div>
      </div>
    </div>
    <form name="createForm" @submit.prevent="submit">
      <div class="flex flex-row">
        <div class="basis-1/2">
          <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
              <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6  dark:bg-gray-900">
                  <h2 class="text-center dark:text-gray-200text-xl py-2">
                    الفحوص المختبرية للزوج
                  </h2>
                  <div className="flex flex-col  pt-8" style="direction: ltr">
                    <div class="flex flex-row">
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="husband_b" value="B- group & RH " />
                          <select
                            v-model="form.husband_b"
                            id="husband_b"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in bloodType"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>

                          <span
                            className="text-red-600"
                            v-if="form.errors.husband_b"
                          >
                            {{ form.errors.husband_b }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="husband_hb" value="Hb ( g / L) (11-15)" />

                          <TextInput
                            id="husband_hb"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.husband_hb"
                            autofocus
                          />

                          <span
                            className="text-red-600"
                            v-if="form.errors.husband_hb"
                          >
                            {{ form.errors.husband_hb }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="husband_mcv" value="MCV (83-101)" />

                          <TextInput
                            id="husband_mcv"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.husband_mcv"
                            autofocus
                          />

                          <span
                            className="text-red-600"
                            v-if="form.errors.husband_mcv"
                          >
                            {{ form.errors.husband_mcv }}
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-row">
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="husband_mch" value="MCH 27-32" />
                          <TextInput
                            id="husband_mch"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.husband_mch"
                            autofocus
                          />

                          <span
                            className="text-red-600"
                            v-if="form.errors.husband_mch"
                          >
                            {{ form.errors.husband_mch }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel
                            for="husband_hemoglobin_f"
                            value="Hemoglobin F (0.8-2)"
                          />

                          <TextInput
                            id="husband_hemoglobin_f"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.husband_hemoglobin_f"
                            autofocus
                          />

                          <span
                            className="text-red-600"
                            v-if="form.errors.husband_hemoglobin_f"
                          >
                            {{ form.errors.husband_hemoglobin_f }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel
                            for="husband_hemoglobin_a2"
                            value="Hemoglobin A2 (1.5-3.5)"
                          />

                          <TextInput
                            id="husband_hemoglobin_a2"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.husband_hemoglobin_a2"
                            autofocus
                          />

                          <span
                            className="text-red-600"
                            v-if="form.errors.husband_hemoglobin_a2"
                          >
                            {{ form.errors.husband_hemoglobin_a2 }}
                          </span>
                        </div>
                      </div>
                    </div>

                    <div class="flex flex-row">
                      <div class="basis-1/3">
                        <div className="mb-4 px-3 ">
                          <InputLabel for="husband_hbs" value="HBs Ag" />
                          <select
                            v-model="form.husband_hbs"
                            id="husband_hbs"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in certification"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>
                          <span
                            className="text-red-600"
                            v-if="form.errors.husband_hbs"
                          >
                            {{ form.errors.husband_hbs }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3 ">
                          <InputLabel for="husband_hcv" value="HCV" />
                          <select
                            v-model="form.husband_hcv"
                            id="husband_hcv"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in certification"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>
                          <span
                            className="text-red-600"
                            v-if="form.errors.husband_hcv"
                          >
                            {{ form.errors.husband_hcv }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3 ">
                          <InputLabel for="husband_hiv" value="HIV" />
                          <select
                            v-model="form.husband_hiv"
                            id="husband_hiv"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in certification"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>
                          <span
                            className="text-red-600"
                            v-if="form.errors.husband_hiv"
                          >
                            {{ form.errors.husband_hiv }}
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-row">
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="husband_tb" value="TB" />

                          <select
                            v-model="form.husband_tb"
                            id="husband_tb"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in certificationTb"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>
                          <span
                            className="text-red-600"
                            v-if="form.errors.husband_tb"
                          >
                            {{ form.errors.husband_tb }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="husband_syphilis" value="Syphilis" />
                          <select
                            v-model="form.husband_syphilis"
                            id="husband_syphilis"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in certification"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>
                          <span
                            className="text-red-600"
                            v-if="form.errors.husband_syphilis"
                          >
                            {{ form.errors.husband_syphilis }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="husband_tpha" value="TPHA" />
                          <select
                            v-model="form.husband_tpha"
                            id="husband_tpha"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in certificationWithNone"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>
                          <span
                            className="text-red-600"
                            v-if="form.errors.husband_tpha"
                          >
                            {{ form.errors.husband_tpha }}
                          </span>
                        </div>
                      </div>
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
                    الفحوص المختبرية للزوجة
                  </h2>
                  <div className="flex flex-col  pt-8" style="direction: ltr">
                    <div class="flex flex-row">
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="wife_b" value="B- group & RH " />
                          <select
                            v-model="form.wife_b"
                            id="husband_b"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in bloodType"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>

                          <span
                            className="text-red-600"
                            v-if="form.errors.wife_b"
                          >
                            {{ form.errors.wife_b }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="wife_hb" value="Hb ( g / L) (11-15)" />

                          <TextInput
                            id="wife_hb"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.wife_hb"
                            autofocus
                          />

                          <span
                            className="text-red-600"
                            v-if="form.errors.wife_hb"
                          >
                            {{ form.errors.wife_hb }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="wife_mcv" value="MCV (83-101)" />

                          <TextInput
                            id="wife_mcv"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.wife_mcv"
                            autofocus
                          />

                          <span
                            className="text-red-600"
                            v-if="form.errors.wife_mcv"
                          >
                            {{ form.errors.wife_mcv }}
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-row">
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="wife_mch" value="MCH (27-32)" />
                          <TextInput
                            id="wife_mch"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.wife_mch"
                            autofocus
                          />

                          <span
                            className="text-red-600"
                            v-if="form.errors.wife_mch"
                          >
                            {{ form.errors.wife_mch }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel
                            for="wife_hemoglobin_f"
                            value="Hemoglobin F (0.8-2)"
                          />

                          <TextInput
                            id="wife_hemoglobin_f"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.wife_hemoglobin_f"
                            autofocus
                          />

                          <span
                            className="text-red-600"
                            v-if="form.errors.wife_hemoglobin_f"
                          >
                            {{ form.errors.wife_hemoglobin_f }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel
                            for="wife_hemoglobin_a2"
                            value="Hemoglobin A2 (1.5-3.5)"
                          />

                          <TextInput
                            id="wife_hemoglobin_a2"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.wife_hemoglobin_a2"
                            autofocus
                          />

                          <span
                            className="text-red-600"
                            v-if="form.errors.wife_hemoglobin_a2"
                          >
                            {{ form.errors.wife_hemoglobin_a2 }}
                          </span>
                        </div>
                      </div>
                    </div>

                    <div class="flex flex-row">
                      <div class="basis-1/3">
                        <div className="mb-4 px-3 ">
                          <InputLabel for="wife_hbs" value="HBs Ag" />
                          <select
                            v-model="form.wife_hbs"
                            id="wife_hbs"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in certification"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>
                          <span
                            className="text-red-600"
                            v-if="form.errors.wife_hbs"
                          >
                            {{ form.errors.wife_hbs }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3 ">
                          <InputLabel for="wife_hcv" value="HCV" />
                          <select
                            v-model="form.wife_hcv"
                            id="wife_hcv"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in certification"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>
                          <span
                            className="text-red-600"
                            v-if="form.errors.wife_hcv"
                          >
                            {{ form.errors.wife_hcv }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3 ">
                          <InputLabel for="wife_hiv" value="HIV" />
                          <select
                            v-model="form.wife_hiv"
                            id="wife_hiv"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in certification"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>
                          <span
                            className="text-red-600"
                            v-if="form.errors.wife_hiv"
                          >
                            {{ form.errors.wife_hiv }}
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-row">
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="wife_tb" value="TB" />

                          <select
                            v-model="form.wife_tb"
                            id="wife_tb"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in certificationTb"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>
                          <span
                            className="text-red-600"
                            v-if="form.errors.wife_tb"
                          >
                            {{ form.errors.wife_tb }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="wife_syphilis" value="Syphilis" />

                          <select
                            v-model="form.wife_syphilis"
                            id="wife_syphilis"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in certification"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>

                          <span
                            className="text-red-600"
                            v-if="form.errors.wife_syphilis"
                          >
                            {{ form.errors.wife_syphilis }}
                          </span>
                        </div>
                      </div>
                      <div class="basis-1/3">
                        <div className="mb-4 px-3">
                          <InputLabel for="wife_tpha" value="TPHA" />
                          <select
                            v-model="form.wife_tpha"
                            id="wife_tpha"
                            class="
                              mt-1
                              pr-8
                              bg-gray-50
                              border border-gray-300
                              text-gray-900 text-sm
                              rounded-lg
                              focus:ring-blue-500 focus:border-blue-500
                              block
                              w-full
                              p-2.5
                              dark:bg-gray-700
                              dark:border-gray-600
                              dark:placeholder-gray-400
                              dark:text-white
                              dark:focus:ring-blue-500
                              dark:focus:border-blue-500
                            "
                          >
                            <option
                              v-for="type in certificationWithNone"
                              :value="type.key"
                              :key="type.key"
                            >
                              {{ type.name }}
                            </option>
                          </select>
                          <span
                            className="text-red-600"
                            v-if="form.errors.wife_tpha"
                          >
                            {{ form.errors.wife_tpha }}
                          </span>
                        </div>
                      </div>
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
        className="px-6 mx-2 py-2 mb-12 text-white bg-gray-500 rounded-md focus:outline-none rounded"
        :href="route('formRegistration')"
      >
        العودة
      </Link>

      <button
        @click="open()"
        className="px-6 mb-12 mx-2 py-2 font-bold text-white bg-rose-500 rounded"
      >
        حفظ
      </button>
    </div>
  </AuthenticatedLayout>
</template>
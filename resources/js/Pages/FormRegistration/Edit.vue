<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import Dropdown from "@/Components/Dropdown.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import { ref } from "vue";
import { WebCamUI } from "vue-camera-lib";

const form = useForm({
  name: props.data.name,
  birthdate:  props.data.birthdate,
  certification:  props.data.certification,
  job:  props.data.job,
  address:  props.data.address,
  phone_number:  props.data.phone_number,
  invoice_number:  props.data.invoice_number,
  relatives:  props.data.relatives,
  saler_id:  props.data.saler_id,
  card_number:props.data.card_number,
  family_name: props.data.family_name,
});
const props = defineProps({
  data: Array,
  url: String,
  sales:Array
});
let showWife = ref(false);
let day = ref("اليوم");
let month = ref("الشهر");
let year = ref("السنة");
let dayW = ref("اليوم");
let monthW = ref("الشهر");
let yearW = ref("السنة");
let showHusband = ref(false);
let certification = ref([
  { key: "بدون", name: "بدون" },
  { key: "ابتدائي", name: "ابتدائي" },
  { key: "اعدادي", name: "اعدادي" },
  { key: "دبلوم", name: "دبلوم" },
  { key: "بكالوريوس", name: "بكالوريوس" },
  { key: "ماجستير", name: "ماجستير" },
  { key: "دكتوراه", name: "دكتوراه" },
]);
let relativesType = ref([
  { key: "قريب", name: "قريب" },
  { key: "بعيد", name: "بعيد" },
]);
let dayList = ref([
  { key: "01", name: "01" },
  { key: "02", name: "02" },
  { key: "03", name: "03" },
  { key: "04", name: "04" },
  { key: "05", name: "05" },
  { key: "06", name: "06" },
  { key: "07", name: "07" },
  { key: "08", name: "08" },
  { key: "09", name: "09" },
]);
const submit = () => {
  form.post(route("formRegistrationstoreEdit", props.data.id));
};

const photoHusband = (data) => {
  form.husband_image = data.image_data_url;
  showHusband.value = false;
};
const photoWife = (data) => {
  form.wife_image = data.image_data_url;
  showWife.value = false;
};
const handleImage = (e) => {
  const selectedImage = e.target.files[0]; // get first file
  createBase64Image(selectedImage);
};
const createBase64Image = (fileObject) => {
  const reader = new FileReader();

  reader.onload = (e) => {
    form.husband_image = e.target.result;
    //this.uploadImage();
  };
  reader.readAsDataURL(fileObject);
};
const handleImageWife = (e) => {
  const selectedImage = e.target.files[0]; // get first file
  createBase64ImageWife(selectedImage);
};
const createBase64ImageWife = (fileObject) => {
  const reader = new FileReader();

  reader.onload = (e) => {
    form.wife_image = e.target.result;
    //this.uploadImage();
  };
  reader.readAsDataURL(fileObject);
};
</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-200">
        محافظة كركوك - العقد الإلكتروني
      </h2>
      <WebCamUI @photoTaken="photoHusband" v-if="showHusband" />
      <WebCamUI @photoTaken="photoWife" v-if="showWife" />
    </template>
    <form name="createForm" @submit.prevent="submit">
      <div class="flex flex-row">
        <div class="grow">
          <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
              <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6  dark:bg-gray-900">
                  <h2 class="text-center dark:text-gray-200text-xl py-2">معلومات البطاقة</h2>
                  <div className="flex flex-col">
                    <!-- <div className="mb-4">
                      <InputLabel for="name" value="الصورة الشخصية" />
                      <img :src="form.image" />
                      <button
                        @click.prevent="showHusband = true"
                        class="px-12 mt-3 mx-2 py-2 font-bold text-white bg-rose-500 rounded"
                      >
                        الكاميرة
                      </button>
                      <input
                        @change="handleImage"
                        type="file"
                        accept="image/*"
                        class="px-2 mt-3 py-1 font-bold text-white bg-rose-500 rounded"
                      />
                      <span className="text-red-600" v-if="form.errors.image">
                        الصورة الشخصية حقل مطلوب
                      </span>
                    </div> -->
                    <div className="mb-4">
                      <InputLabel for="card_number" value="رقم البطاقة" />

                      <TextInput
                        id="card_number"
                        type="number"
                        class="mt-1 block w-full"
                        v-model="form.card_number"
                      />

                      <span
                        className="text-red-600"
                        v-if="form.errors.card_number"
                      >
                        رقم البطاقة حقل مطلوب
                      </span>
                    </div>
                    <div className="mb-4">
                      <InputLabel for="name" value="الأسم" />

                      <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.name"
                      />

                      <span className="text-red-600" v-if="form.errors.name">
                        الأسم حقل مطلوب
                      </span>
                    </div>
                    <!-- <div className="mb-4">
                      <InputLabel for="birthdate" value="تاريخ الميلاد" />
                      <div class="flex flex-row">
                        <div class="basis-1/3 px-2">
                          <select
                            v-model="day"
                            @change="
                              form.birthdate = year + '/' + month + '/' + day
                            "
                            class="m-1 pr-8 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                          >
                            <option :value="day" selected disabled>
                              {{ day }}
                            </option>
                            <option
                              :value="d.key"
                              v-for="d in dayList"
                              :key="d.key"
                            >
                              {{ d.name }}
                            </option>
                            <option :value="n + 9" v-for="n in 22" :key="n">
                              {{ n + 9 }}
                            </option>
                          </select>
                        </div>
                        <div class="basis-1/3 px-2">
                          <select
                            v-model="month"
                            @change="
                              form.birthdate = year + '/' + month + '/' + day
                            "
                            class="m-1 pr-8 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                          >
                            <option :value="month" selected disabled>
                              {{ month }}
                            </option>
                            <option
                              :value="d.key"
                              v-for="d in dayList"
                              :key="d.key"
                            >
                              {{ d.name }}
                            </option>
                            <option :value="n + 9" v-for="n in 3" :key="n + 9">
                              {{ n + 9 }}
                            </option>
                          </select>
                        </div>
                        <div class="basis-1/3 px-2">
                          <select
                            v-model="year"
                            @change="
                              form.birthdate = year + '/' + month + '/' + day
                            "
                            class="m-1 pr-8 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                          >
                            <option :value="year" selected disabled>
                              {{ year }}
                            </option>
                            <option :value="n + 1949" v-for="n in 60" :key="n">
                              {{ n + 1949 }}
                            </option>
                          </select>
                        </div>
                      </div>

                      <span
                        className="text-red-600"
                        v-if="form.errors.birthdate || show_birthday"
                      >
                        حقل تاريخ الميلاد مطلوب
                      </span>
                    </div> -->

                    <div className="mb-4">
                      <InputLabel for="sales_id" value="المندوب" />
                      <select
                        v-model="form.saler_id"
                        id="userType"
                        class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                      >
                        <option selected disabled>المندوب</option>
                        <option
                          v-for="(type ,index) in sales"
                          :key="index"
                          :value="type.id"
                        >
                          {{ type.name }}
                        </option>
                      </select>
                      <span
                        className="text-red-600"
                        v-if="form.errors.saler_id"
                      >
                         اسم المندوب حقل مطلوب
                      </span>
                    </div>


                    <div className="mb-4">
                      <InputLabel for="address" value="العنوان" />

                      <TextInput
                        id="address"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.address"
                      />

                      <span className="text-red-600" v-if="form.errors.address">
                        العنوان حقل مطلوب
                      </span>
                    </div>

                    <div className="mb-4">
                      <InputLabel for="family_name" value="أفراد العائلة" />

                      <TextInput
                        id="family_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.family_name"
                      />

                      <span
                        className="text-red-600"
                        v-if="form.errors.family_name"
                      >
                        أفراد العائلة حقل مطلوب
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="flex flex-row">
        <div class="grow">
          <div class="pb-3">
            <div class="mx-auto mx-7">
              <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6  dark:bg-gray-900">
                  <div class="flex flex-row">
                    <div class="basis-1/2">
                      <div className="mb-4 mx-5">
                        <InputLabel for="invoice_number" value="رقم الوصل" />
                        <TextInput
                          id="invoice_number"
                          type="number"
                          class="mt-1 block w-full"
                          v-model="form.invoice_number"
                        />

                        <span
                          className="text-red-600"
                          v-if="form.errors.invoice_number"
                        >
                          رقم الوصل حقل مطلوب
                        </span>
                      </div>
                    </div>
                    <div class="basis-1/2">
                      <div className="mb-4 mx-5">
                        <InputLabel for="phone_number" value="رقم الهاتف" />
                        <TextInput
                          id="phone_number"
                          type="text"
                          class="mt-1 block w-full"
                          v-model="form.phone_number"
                        />

                        <span
                          className="text-red-600"
                          v-if="form.errors.phone_number"
                        >
                          رقم الهاتف حقل مطلوب
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

      <div className="flex items-center justify-center my-6 ">
        <Link
          className="px-6 mx-2 py-2 mb-12 text-white bg-gray-500 rounded-md focus:outline-none rounded"
          :href="route('formRegistration')"
        >
          العودة
        </Link>

        <button
          type="submit"
          className="px-6 mb-12 mx-2 py-2 font-bold text-white bg-rose-500 rounded"
        >
          حفظ التعديلات
        </button>
      </div>
    </form>
  </AuthenticatedLayout>
</template>
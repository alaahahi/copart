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
  saler_id:"",
  card_number: "",
  name: "",
  birthdate: "",
  certification: "",
  job: "",
  address: "",
  image: "",
  phone_number: "",
  invoice_number: "",
  card_id: "",
  family_name: "",
});
defineProps({
  usersType: Array,
  sales : Array,
  cards: Array,
});
//
let show_birthday = ref(false);

let day = ref("اليوم");
let month = ref("الشهر");
let year = ref("السنة");

let showHusband = ref(false);
let certification = ref([
  { key: "بدون", name: "بدون" },
  { key: "ابتدائي", name: "ابتدائي" },
  { key: "متوسطة", name: "متوسطة" },
  { key: "اعدادي", name: "اعدادي" },
  { key: "دبلوم", name: "دبلوم" },
  { key: "بكالوريوس", name: "بكالوريوس" },
  { key: "ماجستير", name: "ماجستير" },
  { key: "دكتوراه", name: "دكتوراه" },
]);
let card_idType = ref([{ key: "1", name: "الهلال الأحمر" }]);
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

const isLoading = ref(false);

const submit = () => {

  isLoading.value = true;
  form.post(route("formRegistration"))
  isLoading.value = false;
};

const photoHusband = (data) => {
  form.image = data.image_data_url;
  showHusband.value = false;
};
const photoWife = (data) => {
  form.wife_image = data.image_data_url;
  // showWife.value = false;
};
const handleImage = (e) => {
  const selectedImage = e.target.files[0]; // get first file
  createBase64Image(selectedImage);
};
const createBase64Image = (fileObject) => {
  const reader = new FileReader();

  reader.onload = (e) => {
    form.image = e.target.result;
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
        العقد الإلكتروني
      </h2>
      <!-- <WebCamUI @photoTaken="photoHusband" v-if="showHusband" />
      <WebCamUI @photoTaken="photoWife" v-if="showWife" /> -->
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
                        رقم البطاقة  غير صحيح أو مستخدم من قبل
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
                      <InputLabel for="certification" value="التحصيل العلمي" />
                      <select
                        v-model="form.certification"
                        id="userType"
                        class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                      >
                        <option selected disabled>التحصيل العلمي</option>
                        <option
                          v-for="(type ,index) in certification"
                          :key="index"
                          :value="type.key"
                        >
                          {{ type.name }}
                        </option>
                      </select>
                      <span
                        className="text-red-600"
                        v-if="form.errors.certification"
                      >
                        التحصيل العلمي حقل مطلوب
                      </span>
                    </div>
                    <div className="mb-4">
                      <InputLabel for="job" value="المهنة" />

                      <TextInput
                        id="job"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.job"
                      />

                      <span className="text-red-600" v-if="form.errors.job">
                        المهنة حقل مطلوب
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
                    <div class="basis-1/3">
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
                    <div class="basis-1/3">
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
                    <div class="basis-1/3">
                      <div className="mb-4 mx-5">
                        <InputLabel for="card_id" value="البطاقة" />
                        <select
                          v-model="form.card_id"
                          id="card_id"
                          class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                        >
                          <option selected disabled>تحديد البطاقة</option>
                          <option v-for="(card, index) in cards" :key="index" :value="card.id">{{ card.name }}</option>
                        </select>
                        <span
                          className="text-red-600"
                          v-if="form.errors.card_id"
                        >
                          البطاقة حقل مطلوب
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
          @click.prevent="submit"
          :disabled="isLoading"
          class="px-6 mb-12 mx-2 py-2 font-bold text-white bg-rose-500 rounded"
        >
          <span v-if="!isLoading">حفظ</span>
          <span v-else>جاري الحفظ...</span>
        </button>
      </div>
    </form>
  </AuthenticatedLayout>
</template>
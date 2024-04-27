<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import Dropdown from "@/Components/Dropdown.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";

const form = useForm({
  name: "",
  email: "",
  password: "",
  userType: "",
  parent_id: "",
  phone: "",
});
defineProps({
  usersType: Array,
});

const submit = () => {
  form.post(route("users.store"));
};
</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout v-if="$page.props.auth.user.type_id == 1">
    <template #header>
      <h2
        class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-200"
      >
        إنشاء مستخدم
      </h2>
    </template>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 dark:bg-gray-900">
            <div className="flex items-center justify-between mb-6">
              <Link
                className="px-6 py-2 text-white bg-gray-500 rounded-md focus:outline-none"
                :href="route('users.index')"
              >
                العودة
              </Link>
            </div>

            <form name="createForm" @submit.prevent="submit">
              <div className="flex flex-col">
                <div className="mb-4">
                  <InputLabel for="name" value="الأسم" />

                  <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    autofocus
                  />

                  <span className="text-red-600" v-if="form.errors.name">
                    الأسم حقل مطلوب
                  </span>
                </div>
                <div className="mb-4">
                  <InputLabel for="email" value="اسم المستخدم" />

                  <TextInput
                    id="email"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.email"
                  />

                  <span className="text-red-600" v-if="form.errors.email">
                    اسم المستخدم هذا غير متاح
                  </span>
                </div>
                <div className="mb-4">
                  <InputLabel for="password" value="كلمة المرور" />

                  <TextInput
                    id="password"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.password"
                  />

                  <span className="text-red-600" v-if="form.errors.password">
                    كلمة المرور حقل مطلوب
                  </span>
                </div>
                <div className="mb-4">
                  <InputLabel for="userType" value="صلاجيات المستخدم" />
                  <select
                    v-model="form.userType"
                    id="userType"
                    class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                  >
                    <option selected disabled>صلاحيات المستخدم المتاحة</option>
                    <option
                      v-for="(user, index) in usersType"
                      :key="index"
                      :value="user.id"
                    >
                      {{ user.name }}
                    </option>
                  </select>
                
                </div>
                <div className="mb-4">
                  <InputLabel for="phone" value="رقم الهاتف" />
                  <TextInput
                    id="phone"
                    type="number"
                    class="mt-1 block w-full"
                    v-model="form.phone"
                  />
                </div>
              </div>

              <div className="mt-4">
                <button
                  type="submit"
                  className="px-6 py-2 font-bold text-white bg-rose-500 rounded"
                >
                  حفظ
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
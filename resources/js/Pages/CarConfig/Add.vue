<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Dropdown from "@/Components/Dropdown.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import { ref } from "vue";

const form = useForm({
  name:"",
  nameEn: "",
});


const isLoading = ref(false);

const submit = () => {

  isLoading.value = true;
  form.post(route("addCompany"))
  isLoading.value = false;
};

</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-200">
        {{ $t('addCompany') }}
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
                  <h2 class="text-center dark:text-gray-200text-xl py-2">{{ $t('companyInformation') }}</h2>
                  <div className="flex flex-col">
                    <div className="mb-4">
                      <InputLabel for="name" :value="$t('name')" />

                      <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.name"
                      />

                      <span v-if="form.errors.name" class="text-red-600">
                        {{ $t('nameRequired') }}
                      </span>
                    </div>
                    <div className="mb-4">
                      <InputLabel for="name" :value="$t('englishName')" />
                      <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.nameEn"
                      />
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
        {{ $t('return') }}

        </Link>

        <button
          @click.prevent="submit"
          :disabled="isLoading"
          class="px-6 mb-12 mx-2 py-2 font-bold text-white bg-rose-500 rounded"
        >
        <span v-if="!isLoading">{{ $t('save') }}</span>
        <span v-else>{{ $t('saving') }}</span>
        </button>
      </div>
    </form>


  </AuthenticatedLayout>
</template>
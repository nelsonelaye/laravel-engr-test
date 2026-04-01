<template>
    <GuestLayout>
        <Head title="Submit Claim" />

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-lg sm:rounded-lg">
                    <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-800">
                        <h1 class="text-3xl font-bold text-white">Submit Medical Claim</h1>
                        <p class="text-blue-100 mt-2">Fill in the details below to submit a new claim</p>
                    </div>

                    <form @submit.prevent="submitClaim" class="p-8 space-y-8">
                        <!-- Header Info -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Insurer Code</label>
                                <select
                                    v-model="form.insurer_id"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">-- Select Insurer --</option>
                                    <option v-for="insurer in insurers" :key="insurer.id" :value="insurer.id">
                                        {{ insurer.code }} - {{ insurer.name }}
                                    </option>
                                </select>
                                <span v-if="errors.insurer_id" class="text-sm text-red-600">{{ errors.insurer_id[0] }}</span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Provider Name</label>
                                <input
                                    v-model="form.provider_name"
                                    type="text"
                                    placeholder="Your provider name"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                />
                                <span v-if="errors.provider_name" class="text-sm text-red-600">{{ errors.provider_name[0] }}</span>
                            </div>
                        </div>

                        <!-- Date & Details -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Encounter Date</label>
                                <input
                                    v-model="form.encounter_date"
                                    type="date"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                />
                                <span v-if="errors.encounter_date" class="text-sm text-red-600">{{ errors.encounter_date[0] }}</span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Specialty</label>
                                <select
                                    v-model="form.specialty"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">-- Select Specialty --</option>
                                    <option>Cardiology</option>
                                    <option>Orthopedics</option>
                                    <option>Neurology</option>
                                    <option>Pediatrics</option>
                                    <option>General Practice</option>
                                </select>
                                <span v-if="errors.specialty" class="text-sm text-red-600">{{ errors.specialty[0] }}</span>
                            </div>
                        </div>

                        <!-- Priority -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Priority Level</label>
                            <div class="flex gap-4">
                                <label v-for="level in [1, 2, 3, 4, 5]" :key="level" class="flex items-center">
                                    <input
                                        type="radio"
                                        :value="level"
                                        v-model.number="form.priority_level"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">{{ level }}</span>
                                </label>
                            </div>
                            <span v-if="errors.priority_level" class="text-sm text-red-600">{{ errors.priority_level[0] }}</span>
                        </div>

                        <!-- Claim Items -->
                        <div>
                            <div class="flex justify-between items-center mb-4">
                                <label class="block text-sm font-medium text-gray-700">Claim Items</label>
                                <button
                                    type="button"
                                    @click="addClaimItem"
                                    class="inline-flex items-center px-3 py-1 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700"
                                >
                                    + Add Item
                                </button>
                            </div>

                            <div class="overflow-x-auto border border-gray-300 rounded-md">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Item Name</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Unit Price</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Quantity</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Subtotal</th>
                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-700">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="(item, idx) in form.items" :key="idx">
                                            <td class="px-4 py-2">
                                                <input
                                                    v-model="item.name"
                                                    type="text"
                                                    placeholder="Item name"
                                                    class="block w-full px-2 py-1 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                                                />
                                            </td>
                                            <td class="px-4 py-2">
                                                <input
                                                    v-model.number="item.unit_price"
                                                    type="number"
                                                    step="0.01"
                                                    placeholder="0.00"
                                                    class="block w-full px-2 py-1 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                                                />
                                            </td>
                                            <td class="px-4 py-2">
                                                <input
                                                    v-model.number="item.quantity"
                                                    type="number"
                                                    min="1"
                                                    class="block w-full px-2 py-1 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500"
                                                />
                                            </td>
                                            <td class="px-4 py-2 text-right text-sm font-semibold text-gray-900">
                                                ₦{{ (item.unit_price * item.quantity).toFixed(2) }}
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <button
                                                    type="button"
                                                    @click="removeClaimItem(idx)"
                                                    class="text-red-600 hover:text-red-900 text-sm font-medium"
                                                >
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <span v-if="errors.items" class="text-sm text-red-600">{{ errors.items[0] }}</span>
                        </div>

                        <!-- Total -->
                        <div class="flex justify-end">
                            <div class="w-full sm:w-1/3">
                                <div class="bg-gray-50 rounded-md p-4 border border-gray-200">
                                    <div class="flex justify-between items-center">
                                        <span class="text-lg font-semibold text-gray-900">Claim Total:</span>
                                        <span class="text-2xl font-bold text-blue-600">₦{{ claimTotal.toFixed(2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submission Status & Errors -->
                        <div v-if="errors.general" class="bg-red-50 border border-red-200 rounded-md p-4">
                            <p class="text-sm text-red-700">{{ errors.general }}</p>
                        </div>

                        <div v-if="successMessage" class="bg-green-50 border border-green-200 rounded-md p-4">
                            <p class="text-sm text-green-700">{{ successMessage }}</p>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex gap-4 justify-end">
                            <button
                                type="button"
                                @click="resetForm"
                                class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                            >
                                Reset
                            </button>
                            <button
                                type="submit"
                                :disabled="isSubmitting"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                            >
                                {{ isSubmitting ? 'Submitting...' : 'Submit Claim' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>

<script setup>
import { Head } from "@inertiajs/vue3";
import { ref, reactive, computed, onMounted } from "vue";
import axios from "axios";

const insurers = ref([]);
const isSubmitting = ref(false);
const successMessage = ref("");
const errors = reactive({});

const form = reactive({
    insurer_id: "",
    provider_name: "",
    encounter_date: new Date().toISOString().split("T")[0],
    specialty: "",
    priority_level: 3,
    items: [
        { name: "", unit_price: 0, quantity: 1 },
    ],
});

const claimTotal = computed(() => {
    return form.items.reduce((total, item) => {
        return total + (item.unit_price * item.quantity);
    }, 0);
});

const addClaimItem = () => {
    form.items.push({ name: "", unit_price: 0, quantity: 1 });
};

const removeClaimItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const resetForm = () => {
    form.insurer_id = "";
    form.provider_name = "";
    form.encounter_date = new Date().toISOString().split("T")[0];
    form.specialty = "";
    form.priority_level = 3;
    form.items = [{ name: "", unit_price: 0, quantity: 1 }];
    successMessage.value = "";
    Object.keys(errors).forEach(key => delete errors[key]);
};

const submitClaim = async () => {
    isSubmitting.value = true;
    Object.keys(errors).forEach(key => delete errors[key]);
    successMessage.value = "";

    try {
        const response = await axios.post("/api/claims", {
            insurer_id: form.insurer_id,
            provider_name: form.provider_name,
            encounter_date: form.encounter_date,
            specialty: form.specialty,
            priority_level: form.priority_level,
            items: form.items,
        });

        successMessage.value = `Claim submitted successfully! Claim ID: ${response.data.data.id}`;
        resetForm();
    } catch (error) {
        if (error.response && error.response.data.errors) {
            Object.assign(errors, error.response.data.errors);
        } else if (error.response && error.response.data.message) {
            errors.general = error.response.data.message;
        } else {
            errors.general = "An error occurred while submitting the claim.";
        }
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(async () => {
    try {
        const response = await axios.get("/api/insurers");
        insurers.value = response.data.data;
    } catch (error) {
        errors.general = "Failed to load insurers. Please refresh the page.";
    }
});
</script>

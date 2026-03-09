/**
 *
 * MIXINS
 *
 */

var componentsFuncs = {
    mixins: [mainFuncs],
    methods: {
        blockSaveData: function (code, callback) {
            this.state.active = false;
            this.ajaxReq('settings_'+code+'_save', 'post', {
                fields: this.fields,
            }, (response) => {
                // Blocks update
                this.$emit('block_update', code);
            }, (response) => {
            }, (response) => {
                // Callback success
                if (typeof callback === 'function') {
                    callback(response);
                }
            });
        },
    },
    mounted() {
        // Blocks update (ordering data)
        this.$root.$on('blocks_update_before', (calling_block) => {
            this.state.active = false;
        });
        // Blocks update (data is received)
        this.$root.$on('blocks_update', (data, calling_block) => {
            this.state = data.blocks[this.code].state;
            this.fields = data.blocks[this.code].fields;
            this.info = data.blocks[this.code].info;
            // Ensure button is enabled after data load
            if (!this.state.active) {
                this.state.active = true;
            }
        });
    },
};

/**
 *
 * COMPONENTS
 *
 */

// Info block component
Vue.component('info-block', {
    props: {
        titleKey: {
            type: String,
            required: true
        },
        textKey: {
            type: String,
            required: true
        }
    },
    template: `
<div class="card-box ribbon-box">
    <div class="ribbon ribbon-info float-left"><i class="mdi mdi-access-point mr-1"></i> {{ $t("page.SP_CI_SETTINGS_INFO") }}</div>
    <h5 class="text-info float-right mt-0">{{ $t("page." + titleKey) }}</h5>
    <div class="ribbon-content">
        <p class="mb-0" v-html="$t('page.' + textKey)"></p>
    </div>
</div>
`
});

// Background sync settings component
Vue.component('settings-background-sync', {
    mixins: [componentsFuncs],
    data: function () {
        return {
            code: 'background_sync',
            state: {
                display: true,
                active: true,
            },
            fields: {
                fbasket_sync_schedule: '',
            },
            info: {},
        }
    },
    template: `
<div class="card" v-bind:class="{ 'block-disabled': state.active == false }" v-if="state.display">
    <div class="card-body">
        <h4 class="header-title">{{ $t("page.SP_CI_SETTINGS_BACKGROUND_SYNC_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_SETTINGS_BACKGROUND_SYNC_SUBTITLE") }}</p>
        <div class="form-group mb-3">
            <label class="mb-2">{{ $t("page.SP_CI_FBASKET_SYNC_SCHEDULE") }}</label>
            <div class="radio mb-2">
                <input v-model="fields.fbasket_sync_schedule" type="radio" name="fbasket_sync_schedule" id="fbasket_sync_schedule_disabled" value="">
                <label for="fbasket_sync_schedule_disabled">
                    {{ $t("page.SP_CI_SETTINGS_FBASKET_SYNC_SCHEDULE_DISABLED") }}
                </label>
            </div>
            <div class="radio radio-info mb-2">
                <input v-model="fields.fbasket_sync_schedule" type="radio" name="fbasket_sync_schedule" id="fbasket_sync_schedule_1h" value="1h">
                <label for="fbasket_sync_schedule_1h">
                    {{ $t("page.SP_CI_SETTINGS_FBASKET_SYNC_SCHEDULE_1H") }}
                </label>
            </div>
            <div class="radio radio-info mb-2">
                <input v-model="fields.fbasket_sync_schedule" type="radio" name="fbasket_sync_schedule" id="fbasket_sync_schedule_1d" value="1d">
                <label for="fbasket_sync_schedule_1d">
                    {{ $t("page.SP_CI_SETTINGS_FBASKET_SYNC_SCHEDULE_1D") }}
                </label>
            </div>
        </div>
        <div class="text-right">
            <button class="btn btn-success" @click="blockSaveData" :disabled="!state.active">
                {{ $t("page.SP_CI_SETTINGS_SAVE") }}
            </button>
        </div>
    </div>
</div>`,
    methods: {
        blockSaveData: function () {
            // Use mixin method for saving (it will set active = false)
            this.$options.mixins[0].methods.blockSaveData.call(this, this.code, () => {
                setTimeout(() => {
                    this.state.active = true; // Re-enable after save
                    console.log('Settings saved:', this.fields);
                }, 1000);
            });
        },
    },
});

// Settings forgotten basket component for fbasket settings page
Vue.component('settings-forgotten-basket', {
    mixins: [componentsFuncs],
    data: function () {
        return {
            code: 'forgotten_basket',
            state: {
                display: true,
                active: true,
            },
            fields: {
                fbasket_hours: '72',
                fbasket_deal_field: '',
                fbasket_sync_active: 'Y',
            },
            info: {
                deal_string_fields: []
            },
        }
    },
    template: `
<div class="card" v-bind:class="{ 'block-disabled': state.active == false }" v-if="state.display">
    <div class="card-body">
        <h4 class="header-title">{{ $t("page.SP_CI_SETTINGS_FORGOTTEN_BASKET_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_SETTINGS_FORGOTTEN_BASKET_SUBTITLE") }}</p>
        <div class="checkbox checkbox-info mb-3">
            <input type="checkbox" id="settings_forgotten_basket_sync_active" v-model="fields.fbasket_sync_active" value="Y">
            <label for="settings_forgotten_basket_sync_active">{{ $t("page.SP_CI_SETTINGS_FORGOTTEN_BASKET_SYNC_ACTIVE") }}</label>
        </div>
        <div class="form-group mb-3">
            <label>{{ $t("page.SP_CI_SETTINGS_FORGOTTEN_BASKET_DAYS") }}</label>
            <input type="number" v-model="fields.fbasket_hours" class="form-control" min="1" max="8760" placeholder="72">
            <small class="form-text text-muted">{{ $t("page.SP_CI_SETTINGS_FORGOTTEN_BASKET_DAYS_DEFAULT") }}</small>
        </div>
        <div class="form-group mb-3">
            <label>{{ $t("page.SP_CI_SETTINGS_FORGOTTEN_BASKET_DEAL_FIELD") }}</label>
            <select v-model="fields.fbasket_deal_field" class="form-control">
                <option value="">{{ $t("page.SP_CI_SETTINGS_FORGOTTEN_BASKET_DEAL_FIELD_DEFAULT") }}</option>
                <option v-for="field in info.deal_string_fields" :value="field.id">{{field.name}}</option>
            </select>
        </div>
        <div class="text-right">
            <button class="btn btn-success" @click="blockSaveData" :disabled="!state.active">
                {{ $t("page.SP_CI_SETTINGS_SAVE") }}
            </button>
        </div>
    </div>
</div>`,
    methods: {
        blockSaveData: function () {
            // Use mixin method for saving (it will set active = false)
            this.$options.mixins[0].methods.blockSaveData.call(this, this.code, () => {
                setTimeout(() => {
                    this.state.active = true; // Re-enable after save
                    console.log('Settings saved:', this.fields);
                }, 1000);
            });
        },
    },
});

// Manual forgotten basket synchronization
Vue.component('settings-man_fbasket_sync', {
    mixins: [componentsFuncs],
    data: function () {
        return {
            code: 'man_fbasket_sync',
            state: {
                display: true,
                active: false,
            },
            fields: {},
            progress: 0,
            max: 100,
            syncRunning: false,
            abortRequested: false,
        }
    },
    methods: {
        runSync: function () {
            this.syncRunning = true;
            this.abortRequested = false;
            this.progress = 1;
            this.runSyncStep(0);
        },
        stopSync: function () {
            this.abortRequested = true;
        },
        runSyncStep: function (next_item) {
            var self = this;
            axios
                .post('/bitrix/admin/sprod_integr_fbasket_sync.php', {
                    next_item: next_item
                })
                .then(response => {
                    if (self.abortRequested) {
                        self.syncRunning = false;
                        self.abortRequested = false;
                        self.progress = 0;
                        return;
                    }
                    if (response.data.status == 'success') {
                        if (response.data.count) {
                            self.max = response.data.count;
                            self.progress = response.data.next_item;
                            if (self.progress < self.max) {
                                self.runSyncStep(response.data.next_item);
                            } else {
                                self.syncRunning = false;
                                setTimeout(() => {
                                    self.progress = 0;
                                }, 1000);
                            }
                        }
                        else {
                            self.syncRunning = false;
                            self.progress = 0;
                        }
                    }
                    else {
                        self.syncRunning = false;
                        console.log(response.data);
                    }
                })
                .catch(error => {
                    self.syncRunning = false;
                    self.abortRequested = false;
                    self.progress = 0;
                    console.log(error);
                });
        },
    },
    template: `
<div class="card" v-bind:class="{ 'block-disabled': state.active == false }" v-if="state.display">
    <div class="card-body">
        <h4 class="header-title">{{ $t("page.SP_CI_SETTINGS_MAN_FBASKET_SYNC_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_SETTINGS_MAN_FBASKET_SYNC_SUBTITLE") }}</p>
        <button v-if="!syncRunning" class="btn btn-blue" @click="runSync">
            Запустить <i class="fas fa-arrow-right"></i>
        </button>
        <button v-else class="btn btn-warning" :disabled="abortRequested" @click="stopSync">
            Остановить <i class="fas fa-stop"></i>
        </button>
        <b-progress :value="progress" :max="max" variant="info" class="mt-3" animated></b-progress>
    </div>
</div>`,
});

/**
 *
 * VUE APP
 *
 */

const i18n = new VueI18n({
    locale: 'ru',
    messages,
});

var app = new Vue({
    el: '#app',
    i18n,
    mixins: [utilFuncs, mainFuncs],
    data: {
        main_error: '',
        loader_counter: 0,
        errors: [],
        warnings: [],
        sync_start_date: '',
    },
    mounted() {
        // Load forgotten basket settings on page load
        this.updateBlocks();
    },
    methods: {
        // Blocks update method for components
        updateBlocks: function (calling_block) {
            // Load forgotten basket settings
            this.$emit('blocks_update_before', calling_block);
            this.ajaxReq('settings_get', 'get', {
                id: this.$profile_id,
            }, (response) => {
                this.$emit('blocks_update', response.data, calling_block);
                var info = response.data.blocks && response.data.blocks.background_sync && response.data.blocks.background_sync.info;
                this.sync_start_date = (info && info.sync_start_date) ? info.sync_start_date : '';
            }, (response) => {
            }, (response) => {
                // Callback success
                if (typeof callback === 'function') {
                    callback(response);
                }
            });
        },
        startLoadingInfo: function() {
            this.loader_counter++;
        },
        stopLoadingInfo: function() {
            this.loader_counter--;
        }
    },
});

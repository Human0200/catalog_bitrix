
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
            this.ajaxReq('status_'+code+'_save', 'post', {
                fields: this.fields,
            }, (response) => {
                // All blocks update
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
            if (data.blocks != undefined) {
                this.state = data.blocks[this.code].state;
                this.fields = data.blocks[this.code].fields;
            }
            // let res = parse_url('http://example.com:3000/pathname/?search=test#hash');
            // console.log(res.protocol);
            // console.log(res.hostname);
        });
    },
};


/**
 *
 * COMPONENTS
 *
 */

// Table of system state
Vue.component('status-table', {
    props: [],
    components: {
        'vuejs-datepicker': vuejsDatepicker,
    },
    methods: {
        getLabelClass: function (status) {
            let classes = [];
            if (status) {
                classes = [
                    'badge',
                    'bg-soft-success',
                    'text-success',
                ];
            }
            else {
                classes = [
                    'badge',
                    'bg-soft-danger',
                    'text-danger',
                ];
            }
            return classes;
        }
    },
    data: function () {
        return {
            code: 'table',
            state: {
                display: true,
                active: false,
            },
            fields: {},
        }
    },
    template: `
<div class="card" v-bind:class="{ \'block-disabled\': state.active == false }" v-if="state.display">
    <div class="card-body">
        <h4 class="header-title">{{ $t("page.SP_CI_STATUS_TABLE_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_STATUS_TABLE_SUBTITLE") }}</p>
        <div class="table-responsive">
            <table class="table table-borderless table-hover table-centered m-0">
                <tbody>
                <tr>
                    <td>
                        <h5 class="m-0 font-weight-normal">{{ $t("page.SP_CI_STATUS_TABLE_AUTH_FILE") }}</h5>
                    </td>
                    <td>
                        <span :class="getLabelClass(fields.auth_file)">{{fields.auth_file?$t("page.SP_CI_STATUS_TABLE_AUTH_FILE_Y"):$t("page.SP_CI_STATUS_TABLE_AUTH_FILE_N")}}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <h5 class="m-0 font-weight-normal">{{ $t("page.SP_CI_STATUS_TABLE_STORE_HANDLER_FILE") }}</h5>
                    </td>
                    <td>
                        <span :class="getLabelClass(fields.store_handler_file)">{{fields.store_handler_file?$t("page.SP_CI_STATUS_TABLE_STORE_HANDLER_FILE_Y"):$t("page.SP_CI_STATUS_TABLE_STORE_HANDLER_FILE_N")}}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <h5 class="m-0 font-weight-normal">{{ $t("page.SP_CI_STATUS_TABLE_CRM_HANDLER_FILE") }}</h5>
                    </td>
                    <td>
                        <span :class="getLabelClass(fields.crm_handler_file)">{{fields.crm_handler_file?$t("page.SP_CI_STATUS_TABLE_CRM_HANDLER_FILE_Y"):$t("page.SP_CI_STATUS_TABLE_CRM_HANDLER_FILE_N")}}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <h5 class="m-0 font-weight-normal">{{ $t("page.SP_CI_STATUS_TABLE_APP_INFO") }}</h5>
                    </td>
                    <td>
                        <span :class="getLabelClass(fields.app_info)">{{fields.app_info?$t("page.SP_CI_STATUS_TABLE_APP_INFO_Y"):$t("page.SP_CI_STATUS_TABLE_APP_INFO_N")}}</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <h5 class="m-0 font-weight-normal">{{ $t("page.SP_CI_STATUS_TABLE_AUTH_INFO") }}</h5>
                    </td>
                    <td>
                        <span :class="getLabelClass(fields.auth_info)">{{fields.auth_info?$t("page.SP_CI_STATUS_TABLE_AUTH_INFO_Y"):$t("page.SP_CI_STATUS_TABLE_AUTH_INFO_N")}}</span>
                        <!-- <a href="#" class="ml-2"><i class="fe-refresh-cw"></i></a> -->
                    </td>
                </tr>
                <tr>
                    <td>
                        <h5 class="m-0 font-weight-normal">{{ $t("page.SP_CI_STATUS_TABLE_CONNECT") }}</h5>
                    </td>
                    <td>
                        <span :class="getLabelClass(fields.connect)">{{fields.connect?$t("page.SP_CI_STATUS_TABLE_CONNECT_Y"):$t("page.SP_CI_STATUS_TABLE_CONNECT_N")}}</span>
                        <!-- <a href="#" class="ml-2"><i class="fe-refresh-cw"></i></a> -->
                    </td>
                </tr>
                <tr>
                    <td>
                        <h5 class="m-0 font-weight-normal">{{ $t("page.SP_CI_STATUS_TABLE_STORE_EVENTS") }}</h5>
                    </td>
                    <td>
                        <span :class="getLabelClass(fields.store_events)">{{fields.store_events?$t("page.SP_CI_STATUS_TABLE_STORE_EVENTS_Y"):$t("page.SP_CI_STATUS_TABLE_STORE_EVENTS_N")}}</span>
                        <!-- <a href="#" class="ml-2"><i class="fe-refresh-cw"></i></a> -->
                    </td>
                </tr>
                <tr v-if="!fields.crm_events_uncheck">
                    <td>
                        <h5 class="m-0 font-weight-normal">{{ $t("page.SP_CI_STATUS_TABLE_CRM_EVENTS") }}</h5>
                    </td>
                    <td>
                        <span :class="getLabelClass(fields.crm_events)">{{fields.crm_events?$t("page.SP_CI_STATUS_TABLE_CRM_EVENTS_Y"):$t("page.SP_CI_STATUS_TABLE_CRM_EVENTS_N")}}</span>
                        <!-- <a href="#" class="ml-2"><i class="fe-refresh-cw"></i></a> -->
                    </td>
                </tr>
                <tr>
                    <td>
                        <h5 class="m-0 font-weight-normal">{{ $t("page.SP_CI_STATUS_TABLE_PROFILES") }}</h5>
                    </td>
                    <td>
                        <span :class="getLabelClass(fields.profiles)">{{fields.profiles?$t("page.SP_CI_STATUS_TABLE_PROFILES_Y"):$t("page.SP_CI_STATUS_TABLE_PROFILES_N")}}</span>
                        <!-- <a href="#" class="ml-2"><i class="fe-refresh-cw"></i></a> -->
                    </td>
                </tr>
                </tbody>
            </table>
        </div> <!-- end .table-responsive-->
    </div> <!-- end card-body -->
</div> <!-- end card -->
`,
    mixins: [utilFuncs, componentsFuncs],
});

// File log
Vue.component('status-filelog', {
    props: [],
    data: function () {
        return {
            code: 'filelog',
            state: {
                display: true,
                active: false,
            },
            fields: {
                active: '',
                link: '',
                info: false,
            },
            reset_btn: {
                active: true,
            },
        }
    },
    methods: {
        clearLog: function (status) {
            this.reset_btn.active = false;
            this.ajaxReq('status_filelog_reset', 'get', false, (response) => {
                // All blocks update
                this.$emit('block_update', this.code);
            }, (response) => {}, (response) => {
                this.reset_btn.active = true;
            });
        }
    },
    template: `
<div class="card" v-bind:class="{ \'block-disabled\': state.active == false }" v-if="state.display">
    <div class="card-body">
        <h4 class="header-title mb-3">{{ $t("page.SP_CI_STATUS_FILELOG_TITLE") }}</h4>
        <div class="checkbox checkbox-info mb-3">
            <input id="status_filelog_active" type="checkbox" v-model="fields.active" @change="blockSaveData(code)">
            <label for="status_filelog_active">{{ $t("page.SP_CI_STATUS_FILELOG_ACTIVE") }}</label>
        </div>
        <div class="file-link" v-if="fields.info">
            <label>{{ $t("page.SP_CI_STATUS_FILELOG_LINK") }}</label>
            <p><a :href="fields.link" target="_blank">{{fields.link}}</a> <b-badge variant="light">{{fields.info.size_f}}</b-badge></p>
            <b-button variant="danger" size="sm" :disabled="!reset_btn.active" @click="clearLog">{{ $t("page.SP_CI_STATUS_FILELOG_CLEAR") }}</b-button>
        </div>
    </div> <!-- end card-body -->
</div> <!-- end card -->
`,
    mixins: [utilFuncs, componentsFuncs],
});

// Remote diagnostics access
Vue.component('status-remote', {
    props: [],
    components: {
        'datepicker': DatePicker,
    },
    data: function () {
        return {
            code: 'remote',
            state: {
                display: true,
                active: false,
            },
            fields: {
                active: '',
                link: '',
                link_state: false,
                close_date: false,
                server_time: '',
            },
            reset_btn: {
                active: true,
            },
            dp_options: {
                lang: {
                    formatLocale: {
                        firstDayOfWeek: 1,
                    },
                    monthBeforeYear: false,
                },
                format: 'DD.MM.YYYY HH:mm',
            },
            link_state_bgr: '',
        }
    },
    methods: {
        clearLog: function (status) {
            this.reset_btn.active = false;
            this.ajaxReq('status_filelog_reset', 'get', false, (response) => {
                // All blocks update
                this.$emit('block_update', this.code);
            }, (response) => {}, (response) => {
                this.reset_btn.active = true;
            });
        }
    },
    watch: {
        'fields.link_state': function (new_value) {
            if (this.fields.link_state.code == 'active') {
                this.link_state_bgr = 'success';
            }
            else if (this.fields.link_state.code == 'not_active') {
                this.link_state_bgr = 'secondary';
            }
            else if (this.fields.link_state.code == 'active_expired') {
                this.link_state_bgr = 'danger';
            }
        },
    },
    template: `
<div class="card" v-bind:class="{ \'block-disabled\': state.active == false }" v-if="state.display">
    <div class="card-body">
        <h4 class="header-title">{{ $t("page.SP_CI_STATUS_REMOTE_TITLE") }}</h4>
        <p class="sub-header mb-3">{{ $t("page.SP_CI_STATUS_REMOTE_SUBTITLE") }}</p>
        <div class="row">
            <div class="col-md-6">
                <div class="remote-active checkbox checkbox-info mb-3">
                    <input id="status_remote_active" type="checkbox" v-model="fields.active" @change="blockSaveData(code)">
                    <label for="status_remote_active">{{ $t("page.SP_CI_STATUS_REMOTE_ACTIVE") }}</label>
                </div>
                <div class="remote-link">
                    <label>{{ $t("page.SP_CI_STATUS_REMOTE_LINK") }}</label>
                    <p><a v-if="fields.active" :href="fields.link" target="_blank">{{fields.link}}</a> <b-badge v-if="fields.link_state!==false" :variant="link_state_bgr">{{fields.link_state.title}}</b-badge></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="remote-closedate mb-3">
                    <label>{{ $t("page.SP_CI_STATUS_REMOTE_CLOSE_DATE") }}</label>
                    <p><datepicker v-model="fields.close_date" type="datetime" :format="dp_options.format" :minute-step="15" :lang="dp_options.lang" value-type="format" editable="false" :placeholder="$t('page.SP_CI_STATUS_REMOTE_CLOSE_DATE_INPUT')" @change="blockSaveData(code)"></datepicker></p>
                    <b-alert show>{{ $t("page.SP_CI_STATUS_REMOTE_SERVER_TIME") }}{{fields.server_time}}</b-alert>
                </div>
            </div>
        </div>
        <b-alert variant="success" show>{{ $t("page.SP_CI_STATUS_REMOTE_INFO") }}</b-alert>
    </div> <!-- end card-body -->
</div> <!-- end card -->
`,
    mixins: [utilFuncs, componentsFuncs],
});

// File log
Vue.component('status-monitor', {
    props: [],
    data: function () {
        return {
            code: 'monitor',
            state: {
                display: true,
                active: false,
            },
            fields: {
                active: '',
                code: '',
                link: '',
            },
            reset_btn: {
                active: true,
            },
        }
    },
    methods: {
        refreshCode: function (status) {
            if (confirm(this.$t("page.SP_CI_STATUS_MONITOR_CLEAR_CONFIRM")) === true) {
                this.reset_btn.active = false;
                this.ajaxReq('status_monitor_refresh', 'get', false, (response) => {
                    // All blocks update
                    this.$emit('block_update', this.code);
                }, (response) => {
                }, (response) => {
                    this.reset_btn.active = true;
                });
            }
        }
    },
    template: `
<div class="card" v-bind:class="{ \'block-disabled\': state.active == false }" v-if="state.display">
    <div class="card-body">
        <h4 class="header-title mb-3">{{ $t("page.SP_CI_STATUS_MONITOR_TITLE") }}</h4>
        <div class="checkbox checkbox-info mb-3">
            <input id="status_monitor_active" type="checkbox" v-model="fields.active" @change="blockSaveData(code)">
            <label for="status_monitor_active">{{ $t("page.SP_CI_STATUS_MONITOR_ACTIVE") }}</label>
        </div>
        <div class="code" v-if="fields.active">
            <label>{{ $t("page.SP_CI_STATUS_MONITOR_CODE") }}</label>
            <p><b-form-input v-model="fields.code" readonly></b-form-input></p>
            <p><b-button variant="warning" size="sm" :disabled="!reset_btn.active" @click="refreshCode">{{ $t("page.SP_CI_STATUS_MONITOR_CLEAR") }}</b-button></p>
        </div>
        <div class="link" v-if="fields.active">
            <b-button :href="fields.link" target="_blank" variant="info" size="sm">{{ $t("page.SP_CI_STATUS_MONITOR_LINK") }} &#10150;</b-button>
        </div>
    </div> <!-- end card-body -->
</div> <!-- end card -->
`,
    mixins: [utilFuncs, componentsFuncs],
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
    },
    methods: {
        // Blocks update
        updateBlocks: function (calling_block) {
            this.$emit('blocks_update_before', calling_block);
            this.ajaxReq('status_get', 'get', false, (response) => {
                this.$emit('blocks_update', response.data, calling_block);
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
        this.updateBlocks();
    },
});

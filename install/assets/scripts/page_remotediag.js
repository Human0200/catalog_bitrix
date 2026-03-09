
/**
 *
 * SETTINGS AND EXTERNAL VALUES
 *
 */

Vue.prototype.$secure_code = secure_code;


/**
 *
 * MIXINS
 *
 */

var componentsFuncs = {
    mixins: [mainFuncs],
    methods: {
        getReqPath: function (action) {
            return '/bitrix/sprod_integr_diagnostics_ajax.php?action=' + action + '&sc=' + this.$secure_code;
        },
        blockSaveData: function (code, callback) {
            this.state.active = false;
            this.ajaxReq(code + '_save', 'post', {
                fields: this.fields,
            }, (response) => {
                this.state.active = true;
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
    },
};


/**
 *
 * COMPONENTS
 *
 */

// Display table
Vue.component('info-table', {
    props: ['blocks'],
    data: function () {
        return {
        }
    },
    template: `
<div>
    <b-card v-for="block in blocks" :title="block.title">
        <div class="table-responsive">
            <b-table hover :items="block.list">
                <template #cell(value)="data">
                    <span v-html="data.value"></span>
                </template>
            </b-table>
        </div>
    </b-card>
</div>
`,
    mixins: [utilFuncs, componentsFuncs],
});

// Logs search
Vue.component('logs-search', {
    props: ['fields', 'filelog', 'log_queries'],
    data: function () {
        return {
            code: 'logs',
            state: {
                display: true,
                active: true,
            },
            search_type: 'order', // 'order' or 'deal'
            search_id: '',
            found_labels: [], // Теперь это массив объектов с полями label и timestamp
            found_labels_error: false,
            labels_collapse_open: false,
            selected_labels: [], // Массив выбранных меток
            log_content: [],
            searching: false,
            loading_content: false,
            order_data: null,
            deal_data: null,
            reset_btn: {
                active: true,
            },
        }
    },
    watch: {
        selected_labels: function(newVal, oldVal) {
            // Автоматически загружать логи при изменении выбора меток
            if (newVal.length > 0 && newVal !== oldVal) {
                this.loadLogContent();
            } else if (newVal.length === 0) {
                this.log_content = [];
            }
        }
    },
    methods: {
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },
        searchLabels: function () {
            if (!this.search_id.trim()) {
                return;
            }

            this.searching = true;
            this.found_labels = [];
            this.selected_labels = [];
            this.log_content = [];

            let action = this.search_type === 'order' ? 'logs_find_labels_by_order' : 'logs_find_labels_by_deal';

            this.ajaxReq(action, 'post', {
                id: this.search_id.trim()
            }, (response) => {
                this.found_labels = response.data.labels || [];
                this.order_data = response.data.order_data || null;
                this.deal_data = response.data.deal_data || null;
                this.searching = false;
                this.found_labels_error = this.found_labels.length === 0;
                this.labels_collapse_open = true;
            }, (response) => {
                this.searching = false;
                this.errors = [{message: response.data.message}];
            });
        },
        loadLogContent: function () {
            if (this.selected_labels.length === 0) {
                this.log_content = [];
                return;
            }

            this.loading_content = true;
            this.log_content = [];

            this.ajaxReq('logs_get_content_by_labels', 'post', {
                labels: this.selected_labels
            }, (response) => {
                this.log_content = response.data.content || [];
                this.loading_content = false;
            }, (response) => {
                this.loading_content = false;
                this.errors = [{message: response.data.message}];
            });
        },
        toggleLabelSelection: function (labelData) {
            const label = typeof labelData === 'string' ? labelData : labelData.label;
            const index = this.selected_labels.indexOf(label);
            if (index > -1) {
                this.selected_labels.splice(index, 1);
            } else {
                this.selected_labels.push(label);
            }
        },
        isLabelSelected: function (labelData) {
            const label = typeof labelData === 'string' ? labelData : labelData.label;
            return this.selected_labels.indexOf(label) > -1;
        },
        selectAllLabels: function () {
            this.selected_labels = this.found_labels.map(labelData => labelData.label || labelData);
        },
        deselectAllLabels: function () {
            this.selected_labels = [];
        },
        clearSearch: function () {
            this.search_id = '';
            this.found_labels = [];
            this.found_labels_error = false;
            this.labels_collapse_open = false;
            this.selected_labels = [];
            this.log_content = [];
            this.order_data = null;
            this.deal_data = null;
        },
        formatEntityData: function (data) {
            if (!data) {
                return [];
            }
            const items = [];
            for (const key in data) {
                if (data.hasOwnProperty(key)) {
                    let value = data[key];
                    if (value === null || value === undefined) {
                        value = '<em class="text-muted">не указано</em>';
                    } else if (Array.isArray(value)) {
                        if (value.length === 0) {
                            value = '<em class="text-muted">пусто</em>';
                        } else {
                            value = '<pre style="margin:0;font-size:12px;">' + JSON.stringify(value, null, 2) + '</pre>';
                        }
                    } else if (typeof value === 'object') {
                        value = '<pre style="margin:0;font-size:12px;">' + JSON.stringify(value, null, 2) + '</pre>';
                    } else {
                        value = String(value);
                    }
                    items.push({
                        title: key,
                        value: value
                    });
                }
            }
            return items;
        },
        clearLog: function (status) {
            this.reset_btn.active = false;
            this.ajaxReq('filelog_reset', 'get', false, (response) => {
                // All blocks update
                this.$emit('block_update', 'filelog');
            }, (response) => {}, (response) => {
                this.reset_btn.active = true;
            });
        },
        copyToClipboard: function () {
            if (navigator.clipboard && this.formattedLogContent) {
                navigator.clipboard.writeText(this.formattedLogContent).then(() => {
                    // Success - could show a toast notification here
                    console.log('Log content copied to clipboard');
                }).catch(err => {
                    console.error('Failed to copy: ', err);
                });
            }
        },
        downloadAsFile: function () {
            if (this.formattedLogContent) {
                const blob = new Blob([this.formattedLogContent], { type: 'text/plain;charset=utf-8' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                const fileName = this.selected_labels.length === 1
                    ? 'logs_' + this.selected_labels[0]
                    : 'logs_multiple_' + this.selected_labels.length + '_labels';
                a.download = fileName + '_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.txt';
                a.href = url;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            }
        },
        getLabelButtonVariant: function (labelData) {
            const isSelected = this.isLabelSelected(labelData);
            if (isSelected) {
                return labelData.search_type === 'order' ? 'primary' : 'success';
            } else {
                return labelData.search_type === 'order' ? 'outline-primary' : 'outline-success';
            }
        },
        getLabelTooltip: function (labelData) {
            let tooltip = '';
            if (labelData.timestamp) {
                tooltip += this.$t('page.SP_CI_DIAGNOSTICS_LOGS_FIRST_SEEN') + ': ' + labelData.timestamp;
            }
            if (labelData.search_type) {
                const typeText = labelData.search_type === 'order' ?
                    this.$t('page.SP_CI_DIAGNOSTICS_LOGS_ORDER_ID') :
                    this.$t('page.SP_CI_DIAGNOSTICS_LOGS_DEAL_ID');
                tooltip += (tooltip ? '\n' : '') + this.$t('page.SP_CI_DIAGNOSTICS_LOGS_SEARCH_TYPE') + ': ' + typeText;
            }
            return tooltip;
        },
        getLabelIcon: function (searchType) {
            return searchType === 'order' ? 'fa fa-shopping-cart' : 'fa fa-handshake-o';
        },
        blockSaveData: function (code, callback) {
            this.state.active = false;
            let fieldsData;
            if (code === 'filelog') {
                fieldsData = this.filelog;
            } else if (code === 'log_queries') {
                fieldsData = this.log_queries;
            } else {
                fieldsData = this.fields;
            }
            this.ajaxReq(code + '_save', 'post', {
                fields: fieldsData,
            }, (response) => {
                this.state.active = true;
                // All blocks update
                this.$emit('block_update', code);
            }, (response) => {
            }, (response) => {
                // Callback success
                if (typeof callback === 'function') {
                    callback(response);
                }
            });
        }
    },
    computed: {
        formattedLogContent: function() {
            return this.log_content.join("\n");
        },
        selectedLabelsText: function() {
            return this.selected_labels.length > 0 ? this.selected_labels.join(', ') : '';
        }
    },
    template: `
<b-card :class="{ 'block-disabled': state.active == false }" v-if="state.display">
    <b-card-body>
        <!-- File log section -->
        <div v-if="filelog" class="mb-4">
            <h5 class="mb-3">{{ $t("page.SP_CI_DIAGNOSTICS_PAGE_FILELOG") }}</h5>
            <div class="checkbox checkbox-info mb-3">
                <input id="status_filelog_active" type="checkbox" v-model="filelog.active" @change="blockSaveData('filelog')">
                <label for="status_filelog_active">{{ $t("page.SP_CI_DIAGNOSTICS_FILELOG_ACTIVE") }}</label>
            </div>
            <div class="checkbox checkbox-info mb-3">
                <input id="status_log_queries_active" type="checkbox" v-model="log_queries.active" @change="blockSaveData('log_queries')">
                <label for="status_log_queries_active">{{ $t("page.SP_CI_DIAGNOSTICS_LOG_QUERIES_ACTIVE") }}</label>
            </div>
            <div class="file-link" v-if="filelog.info">
                <label>{{ $t("page.SP_CI_DIAGNOSTICS_FILELOG_LINK") }}</label>
                <p><a :href="filelog.link" target="_blank">{{filelog.link}}</a> <b-badge variant="light">{{filelog.info.size_f}}</b-badge></p>
                <b-button variant="danger" size="sm" :disabled="!reset_btn.active" @click="clearLog">{{ $t("page.SP_CI_DIAGNOSTICS_FILELOG_CLEAR") }}</b-button>
            </div>
        </div>

        <hr class="my-4" v-if="filelog">

        <!-- Logs search section -->
        <b-alert v-if="fields.error" variant="warning" show class="mb-3">
            <strong>{{ $t("page.SP_CI_DIAGNOSTICS_LOGS_ERROR") }}:</strong> {{ fields.error }}
        </b-alert>

        <b-row class="mb-3">
            <b-col cols="12" md="3">
                <label>{{ $t("page.SP_CI_DIAGNOSTICS_LOGS_SEARCH_TYPE") }}</label>
                <b-form-select v-model="search_type" :options="[
                        { value: 'order', text: $t('page.SP_CI_DIAGNOSTICS_LOGS_ORDER_ID') },
                        { value: 'deal', text: $t('page.SP_CI_DIAGNOSTICS_LOGS_DEAL_ID') }
                    ]">
                </b-form-select>
            </b-col>
            <b-col cols="12" md="6">
                <label>{{ $t("page.SP_CI_DIAGNOSTICS_LOGS_SEARCH_ID") }}</label>
                <b-form-input
                    v-model="search_id"
                    :placeholder="search_type === 'order' ? $t('page.SP_CI_DIAGNOSTICS_LOGS_ORDER_PLACEHOLDER') : $t('page.SP_CI_DIAGNOSTICS_LOGS_DEAL_PLACEHOLDER')"
                ></b-form-input>
            </b-col>
            <b-col cols="12" md="3">
                <label>&nbsp;</label>
                <div>
                    <b-button variant="primary" :disabled="!search_id.trim() || searching" @click="searchLabels">
                        <b-spinner small v-if="searching"></b-spinner>
                        {{ $t("page.SP_CI_DIAGNOSTICS_LOGS_SEARCH") }}
                    </b-button>
                    <b-button variant="secondary" class="ml-2" @click="clearSearch" :disabled="searching">
                        {{ $t("page.SP_CI_DIAGNOSTICS_LOGS_CLEAR") }}
                    </b-button>
                </div>
            </b-col>
        </b-row>

        <b-card no-body class="mb-3">
            <b-card-header header-tag="header" class="p-1" role="tab">
                <b-button block v-b-toggle.accordion-order variant="outline-info" class="text-left">
                    {{ search_type === 'order' ? $t('page.SP_CI_DIAGNOSTICS_LOGS_CURRENT_ORDER') : $t('page.SP_CI_DIAGNOSTICS_LOGS_RELATED_ORDER') }}
                </b-button>
            </b-card-header>
            <b-collapse id="accordion-order" accordion="search-results-accordion" v-if="order_data">
                <b-card-body>
                    <b-table hover :items="formatEntityData(order_data)" small :fields="[{key: 'title', label: 'Поле'}, {key: 'value', label: 'Значение'}]">
                        <template #cell(value)="data">
                            <span v-html="data.value"></span>
                        </template>
                    </b-table>
                </b-card-body>
            </b-collapse>
        </b-card>

        <b-card no-body class="mb-3">
            <b-card-header header-tag="header" class="p-1" role="tab">
                <b-button block v-b-toggle.accordion-deal variant="outline-info" class="text-left">
                    {{ search_type === 'deal' ? $t('page.SP_CI_DIAGNOSTICS_LOGS_CURRENT_DEAL') : $t('page.SP_CI_DIAGNOSTICS_LOGS_RELATED_DEAL') }}
                </b-button>
            </b-card-header>
            <b-collapse id="accordion-deal" accordion="search-results-accordion" v-if="deal_data">
                <b-card-body>
                    <b-table hover :items="formatEntityData(deal_data)" small :fields="[{key: 'title', label: 'Поле'}, {key: 'value', label: 'Значение'}]">
                        <template #cell(value)="data">
                            <span v-html="data.value"></span>
                        </template>
                    </b-table>
                </b-card-body>
            </b-collapse>
        </b-card>

        <b-card no-body class="mb-3">
            <b-card-header header-tag="header" class="p-1" role="tab">
                <b-button block v-b-toggle.accordion-labels variant="outline-info" class="text-left">
                    {{ $t("page.SP_CI_DIAGNOSTICS_LOGS_FOUND_LABELS") }}
                </b-button>
            </b-card-header>
            <b-collapse id="accordion-labels" accordion="search-results-accordion" v-model="labels_collapse_open">
                <b-card-body>
                    <div v-if="found_labels.length > 0">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="mb-0">{{ $t("page.SP_CI_DIAGNOSTICS_LOGS_SELECT_LABELS") || "Выберите метки:" }}</label>
                                <div>
                                    <b-button variant="outline-secondary" size="sm" @click="selectAllLabels" class="mr-2">
                                        {{ $t("page.SP_CI_DIAGNOSTICS_LOGS_SELECT_ALL") || "Выбрать все" }}
                                    </b-button>
                                    <b-button variant="outline-secondary" size="sm" @click="deselectAllLabels">
                                        {{ $t("page.SP_CI_DIAGNOSTICS_LOGS_DESELECT_ALL") || "Отменить выбор" }}
                                    </b-button>
                                </div>
                            </div>
                            <div class="labels-selection" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; padding: 10px;">
                                <div v-for="labelData in found_labels" :key="labelData.label || labelData" class="mb-2">
                                    <b-form-checkbox
                                        :value="labelData.label || labelData"
                                        v-model="selected_labels"
                                        :disabled="loading_content"
                                        class="mr-2"
                                    >
                                        <span :class="{'font-weight-bold': isLabelSelected(labelData)}">
                                            {{ labelData.label || labelData }}
                                        </span>
                                        <small v-if="labelData.search_type" class="ml-1">
                                            <i :class="getLabelIcon(labelData.search_type)"></i>
                                        </small>
                                        <small v-if="labelData.timestamp" class="text-muted ml-2">
                                            ({{ labelData.timestamp }})
                                        </small>
                                    </b-form-checkbox>
                                </div>
                            </div>
                        </div>

                        <div v-if="selected_labels.length > 0" class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label>{{ $t("page.SP_CI_DIAGNOSTICS_LOGS_CONTENT") }} ({{ selectedLabelsText }})</label>
                                <div v-if="!loading_content && formattedLogContent">
                                    <b-button variant="outline-secondary" size="sm" @click="copyToClipboard" class="mr-2">
                                        <i class="fa fa-copy"></i> {{ $t("page.SP_CI_DIAGNOSTICS_LOGS_COPY") || "Копировать" }}
                                    </b-button>
                                    <b-button variant="outline-primary" size="sm" @click="downloadAsFile">
                                        <i class="fa fa-download"></i> {{ $t("page.SP_CI_DIAGNOSTICS_LOGS_DOWNLOAD") || "Скачать" }}
                                    </b-button>
                                </div>
                            </div>
                            <b-spinner small v-if="loading_content" class="mb-2"></b-spinner>
                            <div class="log-content">
                                <b-form-textarea
                                    v-model="formattedLogContent"
                                    rows="12"
                                    class="bg-light p-3 rounded"
                                    style="font-family: monospace; font-size: 14px;"
                                ></b-form-textarea>
                            </div>
                        </div>
                    </div>

                    <b-alert v-if="found_labels_error" variant="info" show>
                        {{ $t("page.SP_CI_DIAGNOSTICS_LOGS_NO_LABELS_FOUND") }}
                    </b-alert>
                </b-card-body>
            </b-collapse>
        </b-card>
    </b-card-body>
</b-card>
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
    mixins: [utilFuncs, mainFuncs, componentsFuncs],
    data: {
        info: [],
        options: [],
        profiles: [],
        fbasket_profiles: [],
        store_fields: [],
        crm_fields: [],
        handlers: [],
        logs: {},
        filelog: {},
        log_queries: {},
        errors: [],
        warnings: [],
    },
    methods: {
        // Blocks update
        updateBlocks: function (calling_block) {
            this.startLoadingInfo();
            this.ajaxReq('get_all_info', 'get', {}, (response) => {
                this.stopLoadingInfo();
                // Get data
                this.info = response.data.info || [];
                this.options = response.data.options || [];
                this.profiles = response.data.profiles || [];
                this.fbasket_profiles = response.data.fbasket_profiles || [];
                this.store_fields = response.data.store_fields || [];
                this.crm_fields = response.data.crm_fields || [];
                this.handlers = response.data.handlers || [];
                this.logs = response.data.logs || {};
                this.filelog = response.data.filelog || {};
                this.log_queries = response.data.log_queries || {};
            }, (response) => {
                this.stopLoadingInfo();
                // Get data
                this.info = response.data.info || [];
                this.options = response.data.options || [];
                this.profiles = response.data.profiles || [];
                this.fbasket_profiles = response.data.fbasket_profiles || [];
                this.store_fields = response.data.store_fields || [];
                this.crm_fields = response.data.crm_fields || [];
                this.handlers = response.data.handlers || [];
                this.logs = response.data.logs || {};
                this.filelog = response.data.filelog || {};
                this.log_queries = response.data.log_queries || {};
                // Display error
                this.errors.push({message: response.data.message});
            });
        },
    },
    mounted() {
        this.updateBlocks();
    },
});

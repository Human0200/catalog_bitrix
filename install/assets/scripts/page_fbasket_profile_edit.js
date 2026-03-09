/**
 *
 * SETTINGS AND EXTERNAL VALUES
 *
 */

Vue.prototype.$profile_id = profile_id;


/**
 *
 * MIXINS
 *
 */

var componentsFuncs = {
    mixins: [mainFuncs],
    methods: {
        blockSaveData: function (code, fields, callback) {
            if (typeof fields === 'function') {
                callback = fields;
                fields = undefined;
            }
            if (fields === undefined) {
                fields = this.fields;
            }
            this.$emit('load_start');
            this.ajaxReq('fbasket_profile_save', 'post', {
                id: this.$profile_id,
                block: code,
                fields: fields,
            }, (response) => {
                this.$emit('block_update', code);
            }, (response) => {
            }, (response) => {
                if (typeof callback === 'function') {
                    callback(response);
                }
                this.$emit('load_stop');
            });
        },
        afterBlockUpdate() {
        },
        profileDelete: function () {
            if (confirm(this.$t("page.SP_CI_FBASKET_PROFILE_EDIT_DELETE_WARNING"))) {
                this.$emit('load_start');
                this.ajaxReq('fbasket_profile_del', 'post', {
                    id: this.$profile_id,
                }, (response) => {
                    window.parent.location = '/bitrix/admin/sprod_integr_fbasket_profiles.php?lang=ru';
                }, (response) => {
                }, (response) => {
                    this.$emit('load_stop');
                });
            }
        },
    },
    mounted() {
        this.$root.$on('blocks_update', (data, calling_block) => {
            if (!data.blocks || data.blocks[this.code] === undefined) {
                return;
            }
            let item;
            for (item in data.blocks[this.code]) {
                if (this.fields[item] !== undefined) {
                    this.fields[item] = data.blocks[this.code][item];
                }
            }
            this.afterBlockUpdate();
        });
    },
};


/**
 *
 * COMPONENTS
 *
 */

// Main settings
Vue.component('fbasket-profile-main', {
    props: ['sites_list','categ_list','users_list','sources_list','stages_list'],
    mixins: [utilFuncs, componentsFuncs],
    data: function () {
        return {
            code: 'main',
            fields: {
                active: '',
                name: '',
                site: '',
                options: {},
                prefix: '',
                deal_category: '',
                deal_respons_def: '',
                deal_source: '',
                fbasket_closed_stage: '',
            },
            profile_active: false,
        }
    },
    watch: {
        'users_list': function (new_val) {
            if (this.fields.deal_respons_def == '') {
                for (let id in this.users_list) {
                    this.fields.deal_respons_def = id;
                    break;
                }
            }
        },
        profile_active: function (new_value) {
            new_active_value = new_value ? 'Y' : 'N';
            if (this.fields.active != new_active_value) {
                this.fields.active = new_active_value;
            }
        },
        'fields.active': function (new_value) {
            new_profile_active_value = (this.fields.active == 'Y');
            if (this.profile_active != new_profile_active_value) {
                this.profile_active = new_profile_active_value;
            }
        },
    },
    methods: {
        normalizeJsonValue: function (value) {
            if (value === null || value === undefined || typeof value !== 'object' || Array.isArray(value)) {
                return {};
            }
            return value;
        },
        saveMain: function () {
            let options = {
                prefix: this.fields.prefix,
                deal_category: this.fields.deal_category,
                deal_respons_def: this.fields.deal_respons_def,
                deal_source: this.fields.deal_source,
                fbasket_closed_stage: this.fields.fbasket_closed_stage,
            };
            this.blockSaveData(this.code, {
                active: this.fields.active,
                name: this.fields.name,
                site: this.fields.site,
                options: options,
            });
        },
        afterBlockUpdate: function () {
            this.fields.options = this.normalizeJsonValue(this.fields.options);
            this.fields.prefix = this.fields.options.prefix || '';
            this.fields.deal_category = this.fields.options.deal_category !== undefined ? this.fields.options.deal_category : 0;
            this.fields.deal_respons_def = this.fields.options.deal_respons_def || '';
            this.fields.deal_source = this.fields.options.deal_source || '';
            this.fields.fbasket_closed_stage = this.fields.options.fbasket_closed_stage || '';
        },
    },
    template: `
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <div class="alert alert-info">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="fbasket_main_active" v-model="profile_active" value="Y">
                                <label class="custom-control-label" for="fbasket_main_active">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_MAIN_ACTIVE") }}</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <button class="btn btn-sm btn-danger float-right" @click="profileDelete">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_MAIN_PROFILE_DELETE") }}</button>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label for="fbasket_main_name">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_MAIN_NAME") }}</label>
                    <input type="text" id="fbasket_main_name" class="form-control" v-model="fields.name">
                </div>
                <div class="form-group mb-3">
                    <label for="fbasket_main_site">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_SITE") }}</label>
                    <b-form-select v-model="fields.site" id="fbasket_main_site">
                        <option value="">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_SITE_EMPTY") }}</option>
                        <option v-for="site in sites_list" :value="site.id">{{site.name}}</option>
                    </b-form-select>
                </div>
                <div class="form-group mb-3">
                    <label for="fbasket_main_prefix">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_PREFIX") }}</label>
                    <input type="text" id="fbasket_main_prefix" class="form-control" v-model="fields.prefix" v-b-tooltip.hover :title="$t('page.SP_CI_PROFILE_EDIT_MAIN_PREFIX_TOOLTIP')">
                </div>
                <div class="form-group mb-3">
                    <label for="fbasket_main_deal_category">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_DEAL_CATEGORY") }}</label>
                    <b-form-select v-model="fields.deal_category" id="fbasket_main_deal_category">
                        <option v-for="(c_name,c_id) in categ_list" :value="c_id">{{c_name}}</option>
                    </b-form-select>
                </div>
                <div class="form-group mb-3">
                    <label for="fbasket_main_deal_respons_def">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_DEAL_RESPONS_DEF") }}</label>
                    <b-form-select v-model="fields.deal_respons_def" id="fbasket_main_deal_respons_def">
                        <option value="">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_DEAL_RESPONS_DEF_EMPTY") }}</option>
                        <option v-for="(item_name,item_id) in users_list" :value="item_id">{{item_name}}</option>
                    </b-form-select>
                </div>
                <div class="form-group mb-3">
                    <label for="fbasket_main_deal_source">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_DEAL_SOURCE") }}</label>
                    <b-form-select v-model="fields.deal_source" id="fbasket_main_deal_source">
                        <option value="">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_DEAL_SOURCE_EMPTY") }}</option>
                        <option v-for="(field,field_i) in sources_list" :value="field.id">{{field.name}}</option>
                    </b-form-select>
                </div>
            </div> <!-- end card-body -->
            <div class="card-footer">
                <button class="btn btn-success" @click="saveMain">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_SAVE") }}</button>
            </div>
        </div> <!-- end card -->
    </div>
</div>
`,
});

// Contacts settings
Vue.component('fbasket-profile-contacts', {
    props: ['site_field_list','crm_contact_field_list','crm_contact_search_field_list','ugroup_list'],
    mixins: [utilFuncs, componentsFuncs],
    data: function () {
        return {
            code: 'contacts',
            params: {},
            fields: {
                sync_new_type: '',
                comp_table: {},
                contact_search_fields: '',
            }
        }
    },
    watch: {
        'fields.comp_table': function(new_val, old_val) {
            let crm_field_id;
            for (crm_field_id in this.crm_contact_field_list) {
                if (this.fields.comp_table[crm_field_id] === undefined) {
                    this.fields.comp_table[crm_field_id] = '';
                }
            }
        },
        'crm_contact_field_list': function(new_val, old_val) {
            let crm_field_id, crm_field, def_value;
            for (crm_field_id in this.crm_contact_field_list) {
                crm_field = this.crm_contact_field_list[crm_field_id];
                if (this.fields.comp_table[crm_field_id] === undefined) {
                    // Set default mapping for common fields
                    def_value = '';
                    if (this.site_field_list && this.site_field_list.user && this.site_field_list.user.items) {
                        // Map CRM fields to user fields
                        if (crm_field_id === 'NAME' && this.site_field_list.user.items['NAME']) {
                            def_value = 'NAME';
                        } else if (crm_field_id === 'EMAIL' && this.site_field_list.user.items['EMAIL']) {
                            def_value = 'EMAIL';
                        } else if (crm_field_id === 'PHONE' && this.site_field_list.user.items['PHONE']) {
                            def_value = 'PHONE';
                        }
                    }
                    // Fallback to CRM default if no user field mapping found
                    if (!def_value && crm_field.default) {
                        def_value = crm_field.default;
                    }
                    this.fields.comp_table[crm_field_id] = def_value;
                }
            }
        },
    },
    template: `
<div class="card">
    <div class="card-body">
        <h4 class="header-title mb-2">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_SUBTITLE") }}</p>
        <div class="row mb-3">
            <div class="col-md-6">
                <label>{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_SEARCH_FIELDS_LABEL") }}</label>
                <b-form-select v-model="fields.contact_search_fields">
                    <option v-for="(field_name, field_id) in crm_contact_search_field_list" :value="field_id">{{field_name}}</option>
                </b-form-select>
            </div>
            <div class="col-md-6">
                <label>{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_SYNC_NEW_TYPE_LABEL") }}</label>
                <b-form-select v-model="fields.sync_new_type">
                    <option value="">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_SYNC_NEW_TYPE_0") }}</option>
                    <option value="1">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_SYNC_NEW_TYPE_1") }}</option>
                    <option value="2">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_SYNC_NEW_TYPE_2") }}</option>
                </b-form-select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <h5 class="mb-3">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_TITLE") }}</h5>

                <table class="table table-params table-params-props table-bordered">
                    <thead>
                    <tr>
                        <th class="param"><i class="icon icon-bitrix24"></i> {{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_COMP_TABLE_HEAD_B24") }}</th>
                        <!-- <th class="direct"></th> -->
                        <th class="value"><i class="icon icon-bitrix"></i> {{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_COMP_TABLE_HEAD_STORE") }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(crm_field,crm_field_id) in crm_contact_field_list">
                        <td class="param" v-b-tooltip.hover :title="crm_field.hint">{{crm_field.name}} ({{crm_field_id}})</td>
                        <!-- <td class="direct">
                            <a href="#" class="params-change-direct to-crm"><i class="fa fa-arrow-alt-circle-right"></i></a>
                            <a href="#" class="params-change-direct to-order active"><i class="fa fa-arrow-alt-circle-left"></i></a>
                        </td> -->
                        <td class="value">
                            <b-form-select v-model="fields.comp_table[crm_field_id]">
                                <option value="">{{ $t('page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_COMP_TABLE_NOT_SYNC') }}</option>
                                <optgroup v-if="site_field_list.user" :label="site_field_list.user.title">
                                    <option v-for="(field_name, field_id) in site_field_list.user.items" :value="field_id">{{field_name}} ({{field_id}})</option>
                                </optgroup>
                                <optgroup v-if="site_field_list.uf" :label="site_field_list.uf.title">
                                    <option v-for="(field_name, field_id) in site_field_list.uf.items" :value="field_id">{{field_name}} ({{field_id}})</option>
                                </optgroup>
                            </b-form-select>
                        </td>
                    </tr>
                    </tbody>
                </table>

            </div>
        </div>

    </div> <!-- end card-body -->
    <div class="card-footer">
        <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_SAVE") }}</button>
    </div>
</div> <!-- end card -->
`,
});

// Deal fields settings
Vue.component('fbasket-profile-fields', {
    props: ['crm_deal_field_list', 'site_basket_field_list'],
    mixins: [utilFuncs, componentsFuncs],
    data: function () {
        return {
            code: 'fields',
            fields: {
                comp_table: {},
            }
        }
    },
    watch: {
        'fields.comp_table': function(new_val, old_val) {
            let crm_field_id;
            for (crm_field_id in this.crm_deal_field_list) {
                if (this.fields.comp_table[crm_field_id] === undefined) {
                    this.fields.comp_table[crm_field_id] = '';
                }
            }
        },
        'crm_deal_field_list': function(new_val, old_val) {
            let crm_field_id, crm_field, def_value;
            for (crm_field_id in this.crm_deal_field_list) {
                crm_field = this.crm_deal_field_list[crm_field_id];
                if (this.fields.comp_table[crm_field_id] === undefined) {
                    // Set default mapping for common fields
                    def_value = '';
                    if (crm_field.default) {
                        def_value = crm_field.default;
                    }
                    this.fields.comp_table[crm_field_id] = def_value;
                }
            }
        },
    },
    template: `
<div class="card">
    <div class="card-body">
        <h4 class="header-title mb-2">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_FIELDS_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_FIELDS_SUBTITLE") }}</p>

        <div class="row">
            <div class="col-md-12">
                <h5 class="mb-3">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_FIELDS_TITLE") }}</h5>

                <table class="table table-params table-params-props table-bordered">
                    <thead>
                    <tr>
                        <th class="param"><i class="icon icon-bitrix24"></i> {{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_COMP_TABLE_HEAD_B24") }}</th>
                        <th class="value"><i class="icon icon-bitrix"></i> {{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_COMP_TABLE_HEAD_STORE") }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(crm_field,crm_field_id) in crm_deal_field_list">
                        <td class="param" v-b-tooltip.hover :title="crm_field.hint">{{crm_field.name}} ({{crm_field_id}})</td>
                        <td class="value">
                            <b-form-select v-model="fields.comp_table[crm_field_id]">
                                <option value="">{{ $t('page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_COMP_TABLE_NOT_SYNC') }}</option>
                                <optgroup v-for="(field_group, group_key) in site_basket_field_list" :label="field_group.title">
                                    <option v-for="(field_name, field_id) in field_group.items" :value="field_id">{{field_name}}</option>
                                </optgroup>
                            </b-form-select>
                        </td>
                    </tr>
                    </tbody>
                </table>

            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_SAVE") }}</button>
            </div>
        </div>

    </div> <!-- end card-body -->
    <div class="card-footer">
        <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_SAVE") }}</button>
    </div>
</div> <!-- end card -->
`,
});


// Statuses and stages (multiple deal stages per basket status, like order sync profiles)
Vue.component('fbasket-profile-statuses', {
    props: ['stage_list'],
    mixins: [utilFuncs, componentsFuncs],
    data: function () {
        return {
            code: 'statuses',
            params: {},
            fields: {
                comp_table: {},
            },
            fbasket_statuses: {
                'forgotten': { id: 'forgotten', nameKey: 'SP_CI_FBASKET_PROFILE_EDIT_STATUSES_FORGOTTEN' },
                'active': { id: 'active', nameKey: 'SP_CI_FBASKET_PROFILE_EDIT_STATUSES_ACTIVE' },
                'ordered': { id: 'ordered', nameKey: 'SP_CI_FBASKET_PROFILE_EDIT_STATUSES_ORDERED' },
            },
        }
    },
    watch: {
        fields: {
            handler: function () {
                var status_key, status_id, stages, last_value;
                for (status_key in this.fbasket_statuses) {
                    status_id = this.fbasket_statuses[status_key].id;
                    stages = this.fields.comp_table[status_id];
                    if (!stages || !Array.isArray(stages)) continue;
                    last_value = stages.length - 1;
                    if (last_value < 1) continue;
                    if (stages[last_value] === '' && stages[last_value - 1] === '') {
                        stages.splice(last_value - 1, 1);
                    } else if (stages[last_value] !== '') {
                        stages.push('');
                    }
                }
            },
            deep: true
        },
    },
    computed: {
        fbasketStatusList() {
            var list = [];
            for (var key in this.fbasket_statuses) {
                list.push(this.fbasket_statuses[key]);
            }
            return list;
        },
    },
    methods: {
        initStatuses: function() {
            var status_key, status, existing, arr;
            for (status_key in this.fbasket_statuses) {
                status = this.fbasket_statuses[status_key];
                existing = this.fields.comp_table[status.id];
                if (existing === undefined) {
                    arr = [''];
                    if (this.$set) {
                        this.$set(this.fields.comp_table, status.id, arr);
                    } else {
                        this.fields.comp_table[status.id] = arr;
                    }
                } else if (typeof existing === 'string') {
                    arr = existing ? [existing, ''] : [''];
                    if (this.$set) {
                        this.$set(this.fields.comp_table, status.id, arr);
                    } else {
                        this.fields.comp_table[status.id] = arr;
                    }
                } else if (existing && (existing.stages !== undefined)) {
                    arr = Array.isArray(existing.stages) ? existing.stages : [''];
                    if (this.$set) {
                        this.$set(this.fields.comp_table, status.id, arr);
                    } else {
                        this.fields.comp_table[status.id] = arr;
                    }
                } else if (!Array.isArray(existing)) {
                    arr = [''];
                    if (this.$set) {
                        this.$set(this.fields.comp_table, status.id, arr);
                    } else {
                        this.fields.comp_table[status.id] = arr;
                    }
                } else if (existing.length > 0 && existing[existing.length - 1] !== '') {
                    existing.push('');
                }
            }
        },
        afterBlockUpdate() {
            this.initStatuses();
        },
    },
    mounted: function() {
        this.initStatuses();
    },
    template: `
<div class="card">
    <div class="card-body">
        <h4 class="header-title mb-2">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_STATUSES_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_STATUSES_SUBTITLE") }}</p>
        <table class="table table-params table-params-status table-bordered">
            <thead>
            <tr>
                <th class="param"><i class="icon icon-bitrix"></i> {{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_STATUSES_HEAD_BASKET") }}</th>
                <th class="value"><i class="icon icon-bitrix24"></i> {{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_STATUSES_HEAD_DEAL") }}</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(status, status_key) in fbasket_statuses">
                <td class="param">
                    {{ $t('page.' + status.nameKey) }} ({{status.id}})
                    <i class="fa fa-question-circle help-link-icon" v-if="status_key === 'forgotten'" v-b-tooltip.hover :title="$t('page.SP_CI_FBASKET_PROFILE_EDIT_STATUSES_FORGOTTEN_HINT')"></i>
                    <i class="fa fa-question-circle help-link-icon" v-if="status_key === 'active'" v-b-tooltip.hover :title="$t('page.SP_CI_FBASKET_PROFILE_EDIT_STATUSES_ACTIVE_HINT')"></i>
                    <i class="fa fa-question-circle help-link-icon" v-if="status_key === 'ordered'" v-b-tooltip.hover :title="$t('page.SP_CI_FBASKET_PROFILE_EDIT_STATUSES_ORDERED_HINT')"></i>
                </td>
                <td class="value">
                    <b-form-select v-for="(item_v, item_i) in fields.comp_table[status.id]" v-model="fields.comp_table[status.id][item_i]" :key="status.id + '_' + item_i" class="mb-1">
                        <option value="">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_CONTACT_COMP_TABLE_NOT_SYNC") }}</option>
                        <option v-for="(stage, stage_i) in stage_list" :value="stage.id">{{stage.name}} ({{stage.id}})</option>
                    </b-form-select>
                </td>
            </tr>
            </tbody>
        </table>
    </div> <!-- end card-body -->
    <div class="card-footer">
        <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_FBASKET_PROFILE_EDIT_SAVE") }}</button>
    </div>
</div> <!-- end card -->
`,
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
        info: {
            crm: {
                users: [],
                directions: [],
                sources: [],
                contact_fields: [],
                contact_search_fields: [],
            },
            site: {
                contact_fields: [],
            },
        },
    },
    methods: {
        updateBlocks: function (calling_block) {
            this.startLoadingInfo();
            this.ajaxReq('fbasket_profile_info', 'post', {
                id: this.$profile_id,
            }, (response) => {
                this.info = response.data.info;
                this.ajaxReq('fbasket_profile_get', 'post', {
                    id: this.$profile_id,
                }, (response) => {
                    this.$emit('blocks_update', response.data, calling_block);
                }, (response) => {
                }, (response) => {
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                    this.stopLoadingInfo();
                });
            });
        },
    },
    mounted() {
        this.updateBlocks();
    },
});

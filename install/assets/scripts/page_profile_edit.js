
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
        blockSaveData: function (code, callback) {
            this.$emit('load_start');
            this.ajaxReq('profile_save', 'post', {
                id: this.$profile_id,
                block: code,
                fields: this.fields,
            }, (response) => {
                this.$emit('block_update', code);
            }, (response) => {
            }, (response) => {
                // Callback success
                if (typeof callback === 'function') {
                    callback(response);
                }
                this.$emit('load_stop');
            });
        },
        profileDelete: function () {
            if (confirm(this.$t("page.SP_CI_PROFILE_EDIT_DELETE_WARNING"))) {
                this.$emit('load_start');
                this.ajaxReq('profile_del', 'post', {
                    id: this.$profile_id,
                }, (response) => {
                    window.parent.location = '/bitrix/admin/sprod_integr_profiles.php?lang=ru';
                }, (response) => {
                }, (response) => {
                    this.$emit('load_stop');
                });
            }
        },
        afterBlockUpdate() {
        },
    },
    mounted() {
        // Blocks update (data received)
        this.$root.$on('blocks_update', (data, calling_block) => {
            // console.log(this.code);
            // console.log(this.fields);
            let item;
            for (item in this.fields) {
                if (data.blocks[this.code][item] !== undefined) {
                    this.fields[item] = data.blocks[this.code][item];
                }
            }
            for (item in data.params[this.code]) {
                this.params[item] = data.params[this.code][item];
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
Vue.component('profile-main', {
    props: ['categ_list','users_list','sources_list'],
    mixins: [utilFuncs, componentsFuncs],
    data: function () {
        return {
            code: 'main',
            params: {},
            fields: {
                active: '',
                name: '',
                prefix: '',
                deal_category: '',
                deal_respons_def: '',
                deal_source: '',
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
    template: `
<div class="row">
    <div class="col-md-12">

        <div class="card">
            <div class="card-body">

                <div class="row mb-2">
                    <div class="col-sm-6">
                        <div class="alert alert-info">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="main_active" v-model="profile_active" value="Y">
                                <label class="custom-control-label" for="main_active">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_PROFILE_ACTIVE") }}</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <button class="btn btn-sm btn-danger float-right" @click="profileDelete">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_PROFILE_DELETE") }}</button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="main_name">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_NAME") }}</label>
                            <input type="text" id="main_name" class="form-control" v-model="fields.name">
                        </div>
                        <div class="form-group mb-3">
                            <label for="main_prefix">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_PREFIX") }}</label>
                            <input type="text" id="main_prefix" class="form-control" v-model="fields.prefix" v-b-tooltip.hover :title="$t('page.SP_CI_PROFILE_EDIT_MAIN_PREFIX_TOOLTIP')">
                        </div>
                        <div class="form-group mb-3">
                            <label for="main_deal_category">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_DEAL_CATEGORY") }}</label>
                            <b-form-select v-model="fields.deal_category" id="main_deal_category">
                                <option v-for="(c_name,c_id) in categ_list" :value="c_id">{{c_name}}</option>
                            </b-form-select>
                        </div>
                        <div class="form-group mb-3">
                            <label for="main_deal_respons_def">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_DEAL_RESPONS_DEF") }}</label>
                            <b-form-select v-model="fields.deal_respons_def" id="main_deal_respons_def">
                                <option value="">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_DEAL_RESPONS_DEF_EMPTY") }}</option>
                                <option v-for="(item_name,item_id) in users_list" :value="item_id">{{item_name}}</option>
                            </b-form-select>
                        </div>
                        <div class="form-group mb-3">
                            <label for="main_deal_source">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_DEAL_SOURCE") }}</label>
                            <b-form-select v-model="fields.deal_source" id="main_deal_source">
                                <option value="">{{ $t("page.SP_CI_PROFILE_EDIT_MAIN_DEAL_SOURCE_EMPTY") }}</option>
                                <option v-for="(field,field_i) in sources_list" :value="field.id">{{field.name}}</option>
                            </b-form-select>
                        </div>
                    </div>
                </div>

            </div> <!-- end card-body -->
            <div class="card-footer">
                <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_PROFILE_EDIT_SAVE") }}</button>
                <!--<vue-ladda button-class="btn btn-success" data-style="expand-left" loading="true">Сохранить</vue-ladda>-->
            </div>
        </div> <!-- end card -->

    </div>
</div>
`,
});

// Filter
Vue.component('profile-filter', {
    props: ['condition_list'],
    mixins: [utilFuncs, componentsFuncs],
    data: function () {
        return {
            code: 'filter',
            params: {},
            fields: {
                filter: [],
            },
            info: {
                condition_list_flat: [],
            },
        }
    },
    methods: {
        addCondition: function () {
            this.fields.filter.push({
                condition: '',
                operation: '',
                value: [''],
            });
        },
        delCondition: function (index) {
            this.fields.filter.splice(index, 1)
        },
    },
    watch: {
        fields: {
            handler: function () {
                let item_i, item, last_value;
                for (item_i in this.fields.filter) {
                    item = this.fields.filter[item_i];
                    last_value = item.value.length - 1;
                    if (item.value[last_value] == '' && item.value[last_value - 1] == '') {
                        item.value.splice(last_value - 1, 1);
                    }
                    else if (item.value[last_value] != '') {
                        item.value.push('');
                    }
                }
            },
            deep: true
        },
        'condition_list': function(new_val, old_val) {
            // Convert fields list to flat version
            this.info.condition_list_flat = {};
            let crm_section_code, crm_section, crm_field_code, crm_field_name;
            for (group_id in this.condition_list) {
                group = this.condition_list[group_id];
                if (group.items.length == 0) {
                    this.info.condition_list_flat[group_id] = {
                        title: group.title,
                        values: group.values,
                    };
                }
                else {
                    for (field_id in group.items) {
                        field = group.items[field_id];
                        this.info.condition_list_flat[group_id+'_'+field_id] = {
                            title: field.title,
                            values: field.values,
                        };
                    }
                }
            }
        }
    },
    template: `
<div class="card">
    <div class="card-body">
        <h4 class="header-title mb-2">{{ $t("page.SP_CI_PROFILE_EDIT_FILTER_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_PROFILE_EDIT_FILTER_SUBTITLE") }}</p>
        <div class="row">
            <div class="col-md-12">
                <div class="row mb-2" v-for="(item,index) in fields.filter">
                    <div class="col-4">
                        <b-form-select v-model="item.field">
                            <option v-for="(group,group_id) in condition_list" v-if="group.items.length == 0" :value="group_id">{{group.title}}</option>
                            <optgroup v-for="(group,group_id) in condition_list" v-if="group.items.length != 0" :label="group.title">
                                <option v-for="(field,field_id) in group.items" :value="group_id+'_'+field_id">{{field.title}} ({{field_id}})</option>
                            </optgroup>
                        </b-form-select>
                    </div>
                    <div class="col-2">
                        <b-form-select v-model="item.operation">
                            <option value="equal">{{ $t("page.SP_CI_PROFILE_EDIT_FILTER_OPERATION_EQUAL") }}</option>
                            <option value="not_equal">{{ $t("page.SP_CI_PROFILE_EDIT_FILTER_OPERATION_NOT_EQUAL") }}</option>
                            <option value="more">{{ $t("page.SP_CI_PROFILE_EDIT_FILTER_OPERATION_MORE") }}</option>
                            <option value="less">{{ $t("page.SP_CI_PROFILE_EDIT_FILTER_OPERATION_LESS") }}</option>
                        </b-form-select>
                    </div>
                    <div class="col-4">
                        <template v-if="fields.filter[index].field != undefined">
                            <input v-if="info.condition_list_flat[fields.filter[index].field].values.length == 0" type="text" v-for="(item_v,item_i) in item.value" v-model="fields.filter[index].value[item_i]" class="form-control mb-1" />
                            <b-form-select v-if="info.condition_list_flat[fields.filter[index].field].values.length != 0" v-for="(item_v,item_i) in item.value" v-model="fields.filter[index].value[item_i]" class="mb-1">
                                <option value=""></option>
                                <option v-for="(cond_val,cond_val_id) in info.condition_list_flat[fields.filter[index].field].values" :value="cond_val_id">{{cond_val}}</option>
                            </b-form-select>
                        </template>
                    </div>
                    <div class="col-2">
                        <button class="btn btn-danger" @click="delCondition(index)">{{ $t("page.SP_CI_PROFILE_EDIT_FILTER_COND_DEL") }}</button>
                    </div>
                </div>
                <a href="#" @click="addCondition" class="btn btn-info waves-effect waves-light mt-2" data-animation="fadein" data-overlaycolor="#38414a"><i class="mdi mdi-plus-circle mr-1"></i> {{ $t("page.SP_CI_PROFILE_EDIT_FILTER_COND_ADD") }}</a>
            </div>
        </div>
    </div> <!-- end card-body -->
    <div class="card-footer">
        <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_PROFILE_EDIT_SAVE") }}</button>
    </div>
</div> <!-- end card -->
`,
});

// Contact info
Vue.component('profile-contact', {
    props: ['person_type_list','site_field_list','crm_contact_field_list','crm_contact_search_field_list','crm_company_field_list','ugroup_list'],
    mixins: [utilFuncs, componentsFuncs],
    data: function () {
        return {
            code: 'contact',
            params: {},
            fields: {
                sync_new_type: '',
                comp_table: {},
                company_sync_new_type: '',
                contact_search_fields: '',
                company_comp_table: {},
            },
            info: {
                crm_company_field_list_flat: [],
            }
        }
    },
    watch: {
        'fields.comp_table': function(new_val, old_val) {
            let crm_field_id;
            for (let pt_id in this.person_type_list) {
                if (this.fields.comp_table[pt_id] === undefined) {
                    this.fields.comp_table[pt_id] = {};
                }
                for (crm_field_id in this.crm_contact_field_list) {
                    if (this.fields.comp_table[pt_id][crm_field_id] === undefined) {
                        this.fields.comp_table[pt_id][crm_field_id] = {
                            direction: 'all',
                            value: '',
                        };
                    }
                }
            }
        },
        'fields.company_comp_table': function(new_val, old_val) {
            let crm_field_id, crm_field;
            for (let pt_id in this.person_type_list) {
                if (this.fields.company_comp_table[pt_id] === undefined) {
                    this.fields.company_comp_table[pt_id] = {};
                }
                for (crm_field_id in this.info.crm_company_field_list_flat) {
                    crm_field = this.info.crm_company_field_list_flat[crm_field_id];
                    if (!crm_field.is_section) {
                        if (this.fields.company_comp_table[pt_id][crm_field.section] === undefined) {
                            this.fields.company_comp_table[pt_id][crm_field.section] = {};
                        }
                        if (this.fields.company_comp_table[pt_id][crm_field.section][crm_field.id] === undefined) {
                            this.fields.company_comp_table[pt_id][crm_field.section][crm_field.id] = {
                                direction: 'all',
                                value: '',
                            };
                        }
                    }
                }
            }
        },
        'crm_contact_field_list': function(new_val, old_val) {
            let crm_field_id, crm_field, def_value;
            for (let pt_id in this.person_type_list) {
                if (this.fields.comp_table[pt_id] === undefined) {
                    this.fields.comp_table[pt_id] = {};
                }
                for (crm_field_id in this.crm_contact_field_list) {
                    crm_field = this.crm_contact_field_list[crm_field_id];
                    if (this.fields.comp_table[pt_id][crm_field_id] === undefined) {
                        if (crm_field.default[pt_id] !== undefined) {
                            def_value = crm_field.default[pt_id];
                        }
                        else {
                            def_value = crm_field.default;
                        }
                        this.fields.comp_table[pt_id][crm_field_id] = {
                            direction: crm_field.direction,
                            value: def_value,
                        };
                    }
                }
            }
        },
        'crm_company_field_list': function(new_val, old_val) {
            // Convert fields list to flat version
            this.info.crm_company_field_list_flat = [];
            let crm_section_code, crm_section, crm_field_code, crm_field_name;
            for (crm_section_code in this.crm_company_field_list) {
                crm_section = this.crm_company_field_list[crm_section_code];
                this.info.crm_company_field_list_flat.push({
                    title: crm_section.title,
                    is_section: true,
                });
                for (crm_field_code in crm_section.items) {
                    crm_field_name = crm_section.items[crm_field_code];
                    this.info.crm_company_field_list_flat.push({
                        id: crm_field_code,
                        title: crm_field_name,
                        section: crm_section_code,
                        is_section: false,
                        values: crm_section.values !== undefined ? crm_section.values[crm_field_code] : false,
                        value_def: crm_section.value_def !== undefined ? crm_section.value_def[crm_field_code] : false,
                    });
                }
            }
            // Prepare compare table values
            let crm_field_id, crm_field;
            for (let pt_id in this.person_type_list) {
                if (this.fields.company_comp_table[pt_id] === undefined) {
                    this.fields.company_comp_table[pt_id] = {};
                }
                for (crm_field_id in this.info.crm_company_field_list_flat) {
                    crm_field = this.info.crm_company_field_list_flat[crm_field_id];
                    if (!crm_field.is_section) {
                        if (this.fields.company_comp_table[pt_id][crm_field.section] === undefined) {
                            this.fields.company_comp_table[pt_id][crm_field.section] = {};
                        }
                        if (this.fields.company_comp_table[pt_id][crm_field.section][crm_field.id] === undefined) {
                            this.fields.company_comp_table[pt_id][crm_field.section][crm_field.id] = {
                                direction: 'all',
                                value: crm_field.values ? crm_field.value_def : '',
                            };
                        }
                    }
                }
            }
        },
    },
    template: `
<div class="row">
    <div class="col-md-12">
        <b-alert variant="light" show>
            <b-button v-b-toggle.contact-block-1 variant="info" class="mr-2">{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_TITLE") }}</b-button>
            <b-button v-b-toggle.contact-block-2 variant="info">{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_COMPANY_TITLE") }}</b-button>
        </b-alert>
        <b-collapse id="contact-block-1" visible accordion="contact-blocks">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title mb-2">{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_TITLE") }}</h4>
                    <p class="sub-header">{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_SUBTITLE") }}</p>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_SEARCH_FIELDS_LABEL") }}</label>
                            <b-form-select v-model="fields.contact_search_fields">
                                <option v-for="(field_name, field_id) in crm_contact_search_field_list" :value="field_id">{{field_name}}</option>
                            </b-form-select>
                        </div>
                        <div class="col-md-6">
                            <label>{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_SYNC_NEW_TYPE_LABEL") }}</label>
                            <b-form-select v-model="fields.sync_new_type">
                                <option value="">{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_SYNC_NEW_TYPE_0") }}</option>
                                <option value="1">{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_SYNC_NEW_TYPE_1") }}</option>
                                <option value="2">{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_SYNC_NEW_TYPE_2") }}</option>
                            </b-form-select>
                        </div>
                    </div>
                    <div class="row">
                        <div v-for="(pt_name, pt_id) in person_type_list" class="col-md-6">
                            <label v-html="$t('page.SP_CI_PROFILE_EDIT_CONTACT_PT_LABEL', [pt_name, pt_id])"></label>
                    
                            <table class="table mb-3 table-params table-params-props table-bordered">
                                <thead>
                                <tr>
                                    <th class="param"><i class="icon icon-bitrix24"></i> {{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_COMP_TABLE_HEAD_B24") }}</th>
                                    <!-- <th class="direct"></th> -->
                                    <th class="value"><i class="icon icon-bitrix"></i> {{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_COMP_TABLE_HEAD_STORE") }}</th>
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
                                        <b-form-select v-model="fields.comp_table[pt_id][crm_field_id].value">
                                            <option value="">{{ $t('page.SP_CI_PROFILE_EDIT_CONTACT_COMP_TABLE_NOT_SYNC') }}</option>
                                            <optgroup :label="site_field_list.user.title">
                                                <option v-for="(field_name, field_id) in site_field_list.user.items" :value="field_id">{{field_name}} ({{field_id}})</option>
                                            </optgroup>
                                            <optgroup :label="$t('page.SP_CI_PROFILE_EDIT_CONTACT_COMP_TABLE_PROPS', [pt_id])">
                                                <option v-for="(field_name, field_id) in site_field_list.props.items[pt_id]" :value="field_id">{{field_name}} ({{field_id}})</option>
                                            </optgroup>
                                            <optgroup :label="site_field_list.personal.title">
                                                <option v-for="(field_name, field_id) in site_field_list.personal.items" :value="field_id">{{field_name}} ({{field_id}})</option>
                                            </optgroup>
                                            <optgroup :label="site_field_list.uf.title">
                                                <option v-for="(field_name, field_id) in site_field_list.uf.items" :value="field_id">{{field_name}} ({{field_id}})</option>
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
                            <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_PROFILE_EDIT_SAVE") }}</button>
                        </div>
                    </div>
                    
                    <!-- <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_COMPANY_SYNC_NEW_TYPE_LABEL") }}</label>
                            <b-form-select v-model="fields.company_sync_new_type">
                                <option value="">{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_COMPANY_SYNC_NEW_TYPE_0") }}</option>
                                <option value="1">{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_COMPANY_SYNC_NEW_TYPE_1") }}</option>
                            </b-form-select>
                        </div>
                    </div> -->
                    
                </div>
            </div> <!-- end card -->
        </b-collapse>
        <b-collapse id="contact-block-2" accordion="contact-blocks">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title mb-2">{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_COMPANY_TITLE") }}</h4>
                    <p class="sub-header">{{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_COMPANY_SUBTITLE") }}</p>
                    
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <b-alert show variant="info" v-html="$t('page.SP_CI_PROFILE_EDIT_CONTACT_COMPANY_INFO')"></b-alert>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div v-for="(pt_name, pt_id) in person_type_list" class="col-md-6">
                            <label v-html="$t('page.SP_CI_PROFILE_EDIT_CONTACT_COMPANY_PT_LABEL', [pt_name, pt_id])"></label>
                    
                            <table class="table mb-3 table-params table-params-props table-bordered">
                                <thead>
                                <tr>
                                    <th class="param"><i class="icon icon-bitrix24"></i> {{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_COMP_TABLE_HEAD_B24") }}</th>
                                    <!-- <th class="direct"></th> -->
                                    <th class="value"><i class="icon icon-bitrix"></i> {{ $t("page.SP_CI_PROFILE_EDIT_CONTACT_COMP_TABLE_HEAD_STORE") }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="crm_field in info.crm_company_field_list_flat">
                                    <td :class="crm_field.is_section?'section':'param'" :colspan="crm_field.is_section?2:1">{{crm_field.title}}</td>
                                    <!-- <td class="direct">
                                        <a href="#" class="params-change-direct to-crm"><i class="fa fa-arrow-alt-circle-right"></i></a>
                                        <a href="#" class="params-change-direct to-order active"><i class="fa fa-arrow-alt-circle-left"></i></a>
                                    </td> -->
                                    <td class="value" v-if="!crm_field.is_section">
                                        <b-form-select v-if="!crm_field.values" v-model="fields.company_comp_table[pt_id][crm_field.section][crm_field.id].value">
                                            <option value="">{{ $t('page.SP_CI_PROFILE_EDIT_CONTACT_COMP_TABLE_NOT_SYNC') }}</option>
                                            <optgroup :label="$t('page.SP_CI_PROFILE_EDIT_CONTACT_COMP_TABLE_USER')">
                                                <option v-for="(field_name, field_id) in site_field_list.user.items" :value="field_id">{{field_name}} ({{field_id}})</option>
                                            </optgroup>
                                            <optgroup :label="$t('page.SP_CI_PROFILE_EDIT_CONTACT_COMP_TABLE_PROPS', [pt_id])">
                                                <option v-for="(field_name, field_id) in site_field_list.props.items[pt_id]" :value="field_id">{{field_name}} ({{field_id}})</option>
                                            </optgroup>
                                            <optgroup :label="$t('page.SP_CI_PROFILE_EDIT_CONTACT_COMP_TABLE_PERSONAL')">
                                                <option v-for="(field_name, field_id) in site_field_list.personal.items" :value="field_id">{{field_name}} ({{field_id}})</option>
                                            </optgroup>
                                        </b-form-select>
                                        <b-form-select v-if="crm_field.values" v-model="fields.company_comp_table[pt_id][crm_field.section][crm_field.id].value">
                                            <option v-for="(value_name, value_id) in crm_field.values" :value="value_id">{{value_name}}</option>
                                        </b-form-select>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!--
                        <div class="col-md-6">
                            <label>Группы пользователей, которые не должны обновляться из CRM</label>
                            <div class="stages-cancel pl-2">
                                <div class="checkbox checkbox-info mb-2">
                                    <input id="checkbox0" type="checkbox" checked>
                                    <label for="checkbox0">
                                        Администраторы
                                    </label>
                                </div>
                            </div>
                        </div>
                        -->
                    </div>
                </div> <!-- end card-body -->
                <div class="card-footer">
                    <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_PROFILE_EDIT_SAVE") }}</button>
                </div>
            </div> <!-- end card -->
        </b-collapse>
    </div>
</div>
`,
});

// Statuses and stages
Vue.component('profile-statuses', {
    props: ['stage_list','status_list'],
    mixins: [utilFuncs, componentsFuncs],
    data: function () {
        return {
            code: 'statuses',
            params: {},
            fields: {
                comp_table: {},
                cancel_stages: {},
                reverse_disable: false,
            },
        }
    },
    watch: {
        status_list: function () {
            this.correctCompTable();
        },
        fields: {
            handler: function () {
                let item_i, item, last_value;
                for (item_i in this.fields.comp_table) {
                    item = this.fields.comp_table[item_i];
                    last_value = item.stages.length - 1;
                    if (item.stages[last_value] == '' && item.stages[last_value - 1] == '') {
                        item.stages.splice(last_value - 1, 1);
                    }
                    else if (item.stages[last_value] != '') {
                        item.stages.push('');
                    }
                }
            },
            deep: true
        },
    },
    methods: {
        // Correct comparable table
        correctCompTable: function  ()  {
            let item, item_i, i;
            let comp_table  = this.fields.comp_table;
            this.fields.comp_table = {};
            for (item_i in this.status_list) {
                item = this.status_list[item_i];
                if (comp_table[item.id] === undefined) {
                    this.fields.comp_table[item.id] = {
                        direction: 'all',
                        stages: [''],
                    };
                }
                else  {
                    this.fields.comp_table[item.id] = comp_table[item.id];
                }
            }
        },
        afterBlockUpdate: function () {
            this.correctCompTable();
        },
    },
    template: `
<div class="card">
    <div class="card-body">
        <h4 class="header-title mb-2">{{ $t("page.SP_CI_PROFILE_EDIT_STATUSES_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_PROFILE_EDIT_STATUSES_SUBTITLE") }}</p>
        <div class="form-group mb-3">
            <div class="checkbox checkbox-info">
                <input type="checkbox" id="profile_edit_statuses_reverse_disable" v-model="fields.reverse_disable" value="Y">
                <label for="profile_edit_statuses_reverse_disable" v-b-tooltip.hover :title="$t('page.SP_CI_PROFILE_EDIT_STATUSES_REVERSE_DISABLE_TOOLTIP')">{{ $t("page.SP_CI_PROFILE_EDIT_STATUSES_REVERSE_DISABLE") }}</label>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <label>{{ $t("page.SP_CI_PROFILE_EDIT_STATUSES_COMP_TABLE") }}</label>
                <b-alert show variant="warning">{{ $t("page.SP_CI_PROFILE_EDIT_STATUSES_COMP_TABLE_HINT") }}</b-alert>
                <table class="table mb-0 table-params table-params-status table-bordered">
                    <thead>
                    <tr>
                        <th class="param"><i class="icon icon-bitrix"></i> {{ $t("page.SP_CI_PROFILE_EDIT_STATUSES_COMP_TABLE_HEAD_ORDER") }}</th>
                        <th class="value"><i class="icon icon-bitrix24"></i> {{ $t("page.SP_CI_PROFILE_EDIT_STATUSES_COMP_TABLE_HEAD_DEAL") }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(status, status_i) in status_list">
                        <td>{{status.name}} ({{status.id}})</td>
                        <td>
                            <b-form-select v-for="(item_v,item_i) in fields.comp_table[status.id].stages" v-model="fields.comp_table[status.id].stages[item_i]" class="mb-1">
                                <option value="">{{ $t("page.SP_CI_PROFILE_EDIT_STATUSES_COMP_TABLE_NOT_SYNC") }}</option>
                                <option v-for="(stage, stage_i) in stage_list" :value="stage.id">{{stage.name}} ({{stage.id}})</option>
                            </b-form-select>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <label>{{ $t("page.SP_CI_PROFILE_EDIT_STATUSES_CANCEL_STAGES") }}</label>
                <div class="stages-cancel p-3 alert alert-light">
                    <div v-for="(stage, stage_i) in stage_list" class="checkbox checkbox-info mb-2">
                        <input type="checkbox" :id="'checkbox_'+stage.id.replace(':','_')" v-model="fields.cancel_stages" :value="stage.id">
                        <label :for="'checkbox_'+stage.id.replace(':','_')">
                            {{stage.name}} ({{stage.id}})
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- end card-body -->
    <div class="card-footer">
        <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_PROFILE_EDIT_SAVE") }}</button>
    </div>
</div> <!-- end card -->
`,
});

// Order properties
Vue.component('profile-props', {
    props: ['person_type_list','prop_list','prop_other_list','field_list'],
    mixins: [utilFuncs, componentsFuncs],
    data: function () {
        return {
            code: 'props',
            params: {},
            fields: {
                comp_table: {},
            },
        }
    },
    watch: {
        field_list: function(new_val, old_val) {
            this.correctCompTable();
        },
    },
    methods: {
        correctCompTable: function () {
            let pt_i, pt, prop_i, prop;
            let comp_table  = this.fields.comp_table;
            this.fields.comp_table = {};
            for (pt_i in this.person_type_list) {
                for (prop_i in this.prop_list[pt_i]) {
                    prop = this.prop_list[pt_i][prop_i];
                    if (comp_table[prop.ID] === undefined) {
                        this.fields.comp_table[prop.ID] = {
                            direction: 'all',
                            value: '',
                        };
                    }
                    else {
                        this.fields.comp_table[prop.ID] = comp_table[prop.ID];
                    }
                }
            }
        },
        afterBlockUpdate: function () {
            this.correctCompTable();
        },
    },
    template: `
<div class="card">
    <div class="card-body">
        <h4 class="header-title mb-2">{{ $t("page.SP_CI_PROFILE_EDIT_PROPS_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_PROFILE_EDIT_PROPS_SUBTITLE") }}</p>
        <div class="row">
            <div v-for="(pt_name, pt_id) in person_type_list" class="col-md-6">
                <label v-html="$t('page.SP_CI_PROFILE_EDIT_PROPS_COMP_TABLE_PT_LABEL', [pt_name, pt_id])"></label>
                <table class="table mb-2 table-params table-params-props table-bordered">
                    <thead>
                    <tr>
                        <th class="param"><i class="icon icon-bitrix"></i> {{ $t("page.SP_CI_PROFILE_EDIT_PROPS_COMP_TABLE_HEAD_ORDER") }}</th>
                        <th class="direct"></th>
                        <th class="value"><i class="icon icon-bitrix24"></i> {{ $t("page.SP_CI_PROFILE_EDIT_PROPS_COMP_TABLE_HEAD_DEAL") }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="prop in prop_list[pt_id]">
                        <td class="param">
                            {{prop.NAME}} ({{prop.ID}}) 
                            <i class="fa fa-question-circle help-link-icon" v-if="prop.HINT" v-b-tooltip.hover :title="prop.HINT"></i>
                        </td>
                        <td class="direct">
                            <profile-param-dir-switch :field_data="fields.comp_table[prop.ID]" :field_info="prop" />
                        </td>
                        <td class="value">
                            <b-form-select v-model="fields.comp_table[prop.ID].value">
                                <option value="">{{ $t("page.SP_CI_PROFILE_EDIT_PROPS_COMP_TABLE_NOT_SYNC") }}</option>
                                <option v-for="field in prop.values" :value="field.id" :disabled="field.disabled">{{field.title}} ({{field.id}})</option>
                            </b-form-select>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div> <!-- end card-body -->
    <div class="card-footer">
        <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_PROFILE_EDIT_SAVE") }}</button>
    </div>
</div> <!-- end card -->
`,
});

// Other properties
Vue.component('profile-other', {
    props: ['prop_other_list','field_list'],
    mixins: [utilFuncs, componentsFuncs],
    data: function () {
        return {
            code: 'other',
            params: {},
            fields: {
                comp_table: {},
            },
            info: {
                prop_other_list_flat: [],
            },
        }
    },
    methods: {
        updateList() {
            // Fill compare table
            let prop_i, prop;
            for (prop_i in this.prop_other_list) {
                prop = this.prop_other_list[prop_i];
                if (this.fields.comp_table[prop.ID] === undefined) {
                    this.fields.comp_table[prop.ID] = {
                        direction: 'all',
                        value: '',
                    };
                }
            }
            // Convert fields list to flat version
            this.info.prop_other_list_flat = [];
            let section_code, section, field_code, field;
            for (section_code in this.prop_other_list) {
                section = this.prop_other_list[section_code];
                this.info.prop_other_list_flat.push({
                    NAME: section.title,
                    is_section: true,
                });
                for (field_code in section.items) {
                    field = section.items[field_code];
                    this.info.prop_other_list_flat.push({
                        ID: field.ID,
                        NAME: field.NAME,
                        HINT: field.HINT,
                        SYNC_DIR: field.SYNC_DIR,
                        values: field.values,
                        section: section_code,
                        is_section: false,
                    });
                }
            }
            // Compare table initiate
            for (section_code in this.prop_other_list) {
                section = this.prop_other_list[section_code];
                for (field_code in section.items) {
                    field = section.items[field_code];
                    if (this.fields.comp_table[field.ID] === undefined) {
                        this.fields.comp_table[field.ID] = {
                            direction: 'all',
                            value: '',
                        };
                    }
                }
            }
        }
    },
    watch: {
        'fields.comp_table': function(new_val, old_val) {
            this.updateList();
        },
        // 'field_list': function(new_val, old_val) {
        // },
        'prop_other_list': function(new_val, old_val) {
            this.updateList();
        },
    },
    template: `
<div class="card">
    <div class="card-body">
        <h4 class="header-title mb-2">{{ $t("page.SP_CI_PROFILE_EDIT_OTHER_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_PROFILE_EDIT_OTHER_SUBTITLE") }}</p>
        <div class="row">
            <div class="col-md-6">
                <label>{{ $t("page.SP_CI_PROFILE_EDIT_OTHER_LABEL") }}</label>
                <table class="table mb-2 table-params table-params-props table-bordered">
                    <thead>
                    <tr>
                        <th class="param"><i class="icon icon-bitrix"></i> {{ $t("page.SP_CI_PROFILE_EDIT_OTHER_HEAD_ORDER") }}</th>
                        <th class="direct"></th>
                        <th class="value"><i class="icon icon-bitrix24"></i> {{ $t("page.SP_CI_PROFILE_EDIT_OTHER_HEAD_DEAL") }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="p_other in info.prop_other_list_flat">
                        <td :class="p_other.is_section?'section':'param'" :colspan="p_other.is_section?3:1">
                            {{p_other.NAME}}
                            <i class="fa fa-question-circle help-link-icon" v-if="p_other.HINT" v-b-tooltip.hover :title="p_other.HINT"></i>
                        </td>
                        <td v-if="!p_other.is_section" class="direct">
                            <profile-param-dir-switch :field_data="fields.comp_table[p_other.ID]" :field_info="p_other" />
                        </td>
                        <td v-if="!p_other.is_section" class="value">
                            <b-form-select v-model="fields.comp_table[p_other.ID].value">
                                <option value="">{{ $t("page.SP_CI_PROFILE_EDIT_OTHER_NOT_SYNC") }}</option>
                                <option v-for="field in p_other.values" :value="field.id" :disabled="field.disabled">{{field.title}} ({{field.id}})</option>
                            </b-form-select>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div> <!-- end card-body -->
    <div class="card-footer">
        <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_PROFILE_EDIT_SAVE") }}</button>
    </div>
</div> <!-- end card -->
`,
});

// Create new order
Vue.component('profile-neworder', {
    props: ['categ_list', 'condition_list', 'person_type_list', 'delivery_type_list', 'payment_type_list',
        'crm_contact_field_list', 'site_buyer_field_list', 'sites_list'],
    mixins: [utilFuncs, componentsFuncs],
    data: function () {
        return {
            code: 'neworder',
            params: {
                enabled: false,
                warnings: [],
                condition_category_id: 0,
            },
            fields: {
                active: false,
                site_code: '',
                buyer_def: '',
                resp_def: '',
                buyer_type: '',
                pay_method: '',
                delivery_method: '',
                title_replace: false,
                filter: [],
                buyer_comp_table: {},
                buyer_default_email: '',
            },
            info: {
                condition_list_flat: [],
                buyer_def_list: [],
                resp_def_list: [],
            },
        }
    },
    methods: {
        addCondition: function () {
            this.fields.filter.push({
                condition: '',
                operation: '',
                value: [''],
            });
        },
        delCondition: function (index) {
            this.fields.filter.splice(index, 1)
        },
        findBuyers (text, loader) {
            if (text.length > 0) {
                this.info.buyer_def_list = [];
            }
            if (text.length > 2) {
                this.info.buyer_def_list = this.findUser(text);
            }
        },
        findResps (text, loader) {
            if (text.length > 0) {
                this.info.resp_def_list = [];
            }
            if (text.length > 2) {
                this.info.resp_def_list = this.findUser(text);
            }
        },
        findUser (text) {
            let list, i, item;
            list = [];
            axios
                .post(this.getReqPath('otherfunc_find_user'), {
                    search: text,
                })
                .then(response => {
                    if (response.data.status == 'ok') {
                        for (i in response.data.list) {
                            item = response.data.list[i];
                            list.push({
                                "code": item.code,
                                "label": item.label
                            });
                        }
                    }
                    // this.stopLoadingInfo();
                })
                .catch(error => {
                    console.log(error);
                });
            return list;
        },
        afterBlockUpdate() {
            if (this.fields.buyer_def.length > 0) {
                this.info.buyer_def_list = this.findUser(this.fields.buyer_def);
            }
            if (this.fields.resp_def.length > 0) {
                this.info.resp_def_list = this.findUser(this.fields.resp_def);
            }
        },
        updateBuyerList() {
            let item_i, item;
            for (item_i in this.site_buyer_field_list) {
                item = this.site_buyer_field_list[item_i];
                if (this.fields.buyer_comp_table[item.id] === undefined) {
                    this.fields.buyer_comp_table[item.id] = {
                        direction: 'ctos',
                        value: '',
                    };
                }
            }
        }
    },
    watch: {
        site_buyer_field_list: function(new_val, old_val) {
            this.updateBuyerList();
        },
        fields: {
            handler: function () {
                this.updateBuyerList();
                let item_i, item, last_value;
                for (item_i in this.fields.filter) {
                    item = this.fields.filter[item_i];
                    last_value = item.value.length - 1;
                    if (item.value[last_value] == '' && item.value[last_value - 1] == '') {
                        item.value.splice(last_value - 1, 1);
                    }
                    else if (item.value[last_value] != '') {
                        item.value.push('');
                    }
                }
            },
            deep: true
        },
        'condition_list': function(new_val, old_val) {
            // Convert fields list to flat version
            this.info.condition_list_flat = {};
            let crm_section_code, crm_section, crm_field_code, crm_field_name;
            for (group_id in this.condition_list) {
                group = this.condition_list[group_id];
                if (group.items.length == 0) {
                    this.info.condition_list_flat[group_id] = {
                        title: group.title,
                        values: group.values,
                    };
                }
                else {
                    for (field_id in group.items) {
                        field = group.items[field_id];
                        this.info.condition_list_flat[group_id+'_'+field_id] = {
                            title: field.title,
                            values: field.values,
                        };
                    }
                }
            }
        }
    },
    template: `
<b-row>
    <b-col>
        <!--<b-alert :show="params.warning.length > 0" variant="warning">{{params.warning}}</b-alert>-->
        <main-errors :warnings="params.warnings"></main-errors>
        <b-row :class="{ \'block-disabled\': !params.enabled }">
            <b-col>
                <b-alert :show="params.enabled">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="neworder_buyer_active" v-model="fields.active" @change="blockSaveData(code)">
                        <label class="custom-control-label" for="neworder_buyer_active">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_FILTER_ACTIVE") }}</label>
                    </div>
                </b-alert>
                <b-card>
                    <h4 class="header-title mb-2">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_FILTER_TITLE") }}</h4>
                    <p class="sub-header">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_FILTER_SUBTITLE") }}</p>
                    <b-row class="mb-2">
                        <b-col class="col-md-12">
                            <b-row class="mb-2 block-disabled">
                                <b-col class="col-4">
                                    <select class="custom-select mb-1">
                                        <option selected>{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_FILTER_CATEG_TITLE") }}</option>
                                    </select> 
                                </b-col>
                                <b-col class="col-2">
                                    <select class="custom-select">
                                        <option selected>{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_FILTER_CATEG_OPERATION") }}</option>
                                    </select> 
                                </b-col>
                                <b-col class="col-4">
                                    <select class="custom-select">
                                        <option selected>{{categ_list[params.condition_category_id]}} ({{params.condition_category_id}})</option>
                                    </select>
                                </b-col>
                                <b-col class="col-2"></b-col>
                            </b-row>
                            <b-row class="mb-2" v-for="(item,index) in fields.filter">
                                <b-col class="col-4">
                                    <b-form-select v-model="item.field" class="mb-1">
                                        <option v-for="(group,group_id) in condition_list" v-if="group.items.length == 0" :value="group_id">{{group.title}}</option>
                                        <optgroup v-for="(group,group_id) in condition_list" v-if="group.items.length != 0" :label="group.title">
                                            <option v-for="(field,field_id) in group.items" :value="group_id+'_'+field_id">{{field.title}} ({{field_id}})</option>
                                        </optgroup>
                                    </b-form-select>
                                </b-col>
                                <b-col class="col-2">
                                    <b-form-select v-model="item.operation">
                                        <option value="equal">{{ $t("page.SP_CI_PROFILE_EDIT_FILTER_OPERATION_EQUAL") }}</option>
                                        <option value="not_equal">{{ $t("page.SP_CI_PROFILE_EDIT_FILTER_OPERATION_NOT_EQUAL") }}</option>
                                        <option value="more">{{ $t("page.SP_CI_PROFILE_EDIT_FILTER_OPERATION_MORE") }}</option>
                                        <option value="less">{{ $t("page.SP_CI_PROFILE_EDIT_FILTER_OPERATION_LESS") }}</option>
                                    </b-form-select>
                                </b-col>
                                <b-col class="col-4">
                                    <template v-if="fields.filter[index].field != undefined">
                                        <input v-if="info.condition_list_flat[fields.filter[index].field].values.length == 0" type="text" v-for="(item_v,item_i) in item.value" v-model="fields.filter[index].value[item_i]" class="form-control mb-1" />
                                        <b-form-select v-if="info.condition_list_flat[fields.filter[index].field].values.length != 0" v-for="(item_v,item_i) in item.value" v-model="fields.filter[index].value[item_i]" class="mb-1">
                                            <option value=""></option>
                                            <option v-for="(cond_val,cond_val_id) in info.condition_list_flat[fields.filter[index].field].values" :value="cond_val_id">{{cond_val}}</option>
                                        </b-form-select>
                                    </template>
                                </b-col>
                                <b-col class="col-2">
                                    <button class="btn btn-danger" @click="delCondition(index)">{{ $t("page.SP_CI_PROFILE_EDIT_FILTER_COND_DEL") }}</button>
                                </b-col>
                            </b-row>
                            <a href="#" @click="addCondition" class="btn btn-info waves-effect waves-light mt-2" data-animation="fadein" data-overlaycolor="#38414a"><i class="mdi mdi-plus-circle mr-1"></i> {{ $t("page.SP_CI_PROFILE_EDIT_FILTER_COND_ADD") }}</a>
                        </b-col>
                    </b-row>
                    <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_PROFILE_EDIT_SAVE") }}</button>
                </b-card>
                <b-row>
                    <b-col>
                        <b-card>
                            <h4 class="header-title mb-2">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_PARAMS_TITLE") }}</h4>
                            <p class="sub-header">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_PARAMS_SUBTITLE") }}</p>
                            <div class="form-group mb-3">
                                <label for="main_name">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_PARAMS_SITE") }} *</label>
                                <b-form-select v-model="fields.site_code">
                                    <option v-for="item in sites_list" :value="item.id">{{item.name}} ({{item.id}})</option>
                                </b-form-select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="main_name">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_PARAMS_BUYERDEF") }} *</label>
                                <v-select @search="findBuyers" v-model="fields.buyer_def" :reduce="item => item.code" :options="info.buyer_def_list">
                                    <div slot="no-options">{{ $t("page.SP_CI_PROFILE_EDIT_SELECT2_EMPTY") }}</div>
                                </v-select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="main_name">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_PARAMS_RESPDEF") }} *</label>
                                <v-select @search="findResps" v-model="fields.resp_def" :reduce="item => item.code" :options="info.resp_def_list">
                                    <div slot="no-options">{{ $t("page.SP_CI_PROFILE_EDIT_SELECT2_EMPTY") }}</div>
                                </v-select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="main_name">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_PARAMS_BUYERTYPE") }} *</label>
                                <b-form-select v-model="fields.buyer_type">
                                    <option v-for="(item_name, item_id) in person_type_list" :value="item_id">{{item_name}} ({{item_id}})</option>
                                </b-form-select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="main_name">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_PARAMS_PAYMETHOD") }} *</label>
                                <b-form-select v-model="fields.pay_method">
                                    <option v-for="item in payment_type_list" :value="item.id">{{item.name}} ({{item.id}})</option>
                                </b-form-select>
                                <b-alert show variant="warning" class="mt-2">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_PARAMS_PAYMETHOD_WARN") }}</b-alert>
                            </div>
                            <div class="form-group mb-3">
                                <label for="main_name">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_PARAMS_DELIVMETHOD") }} *</label>
                                <b-form-select v-model="fields.delivery_method">
                                    <option v-for="item in delivery_type_list" :value="item.id">{{item.name}} ({{item.id}})</option>
                                </b-form-select>
                                <b-alert show variant="warning" class="mt-2">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_PARAMS_DELIVMETHOD_WARN") }}</b-alert>
                            </div>
                            <!--<div class="form-group mb-3">
                                <b-form-checkbox
                                    id="neworder_params_titlerepl"
                                    v-model="fields.title_replace"
                                    name="title_replace"
                                >
                                    {{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_PARAMS_TITLEREPL") }}
                                </b-form-checkbox>
                            </div>-->
                            <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_PROFILE_EDIT_SAVE") }}</button>
                        </b-card>
                    </b-col>
                    <b-col>
                        <b-card>
                            <h4 class="header-title mb-2">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_BUYER_TITLE") }}</h4>
                            <p class="sub-header">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_BUYER_SUBTITLE") }}</p>
                            <div v-for="field in site_buyer_field_list" class="form-group mb-3">
                                <label for="main_name">{{ field.name }} *</label>
                                <b-form-select v-model="fields.buyer_comp_table[field.id].value">
                                    <option value="">-</option>
                                    <option v-for="(crm_field,crm_field_id) in crm_contact_field_list" :value="crm_field_id">{{crm_field.name}} ({{crm_field_id}})</option>
                                </b-form-select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="neworder_buyer_default_email">{{ $t("page.SP_CI_PROFILE_EDIT_NEWORDER_BUYER_DEFAULTEMAIL") }} *</label>
                                <input type="text" id="neworder_buyer_default_email" class="form-control" v-model="fields.buyer_default_email">
                            </div>
                            <button class="btn btn-success" @click="blockSaveData(code)">{{ $t("page.SP_CI_PROFILE_EDIT_SAVE") }}</button>
                        </b-card>
                    </b-col>
                </b-row>
            </b-col>
        </b-row> <!-- end card -->
    </b-col>
</b-row>
`,
});

// Dirction switcher
Vue.component('profile-param-dir-switch', {
    props: ['field_data', 'field_info'],
    methods: {
        getDirActive: function (check_dir) {
            let prop_dir = this.field_info.SYNC_DIR;
            let result = false;
            if (check_dir == 'stoc' && (prop_dir == 1 || prop_dir == 3)) {
                result = true;
            }
            if (check_dir == 'ctos' && (prop_dir == 2 || prop_dir == 3)) {
                result = true;
            }
            return result;
        },
        getDirSelected: function (check_dir) {
            let result = false;
            let selected_dir = this.field_data.direction;
            if (selected_dir == 'all' || selected_dir == check_dir) {
                result = true;
            }
            return result;
        },
        getDirHint: function (check_dir) {
            let hint = '';
            let prop_dir = this.field_info.SYNC_DIR;
            // Is possible
            if (check_dir == 'stoc') {
                if (this.getDirActive(check_dir, prop_dir)) {
                    hint += this.$t("page.SP_CI_PROFILE_EDIT_OTHER_STOC_Y_HINT");
                }
                else {
                    hint += this.$t("page.SP_CI_PROFILE_EDIT_OTHER_STOC_N_HINT");
                }
            }
            else if (check_dir == 'ctos') {
                if (this.getDirActive(check_dir, prop_dir)) {
                    hint += this.$t("page.SP_CI_PROFILE_EDIT_OTHER_CTOS_Y_HINT");
                }
                else {
                    hint += this.$t("page.SP_CI_PROFILE_EDIT_OTHER_CTOS_N_HINT");
                }
            }
            // Is enabled
            if (this.getDirActive(check_dir, prop_dir)) {
                let selected_dir = this.field_data.direction;
                if (selected_dir == 'all' || selected_dir == check_dir) {
                    hint += this.$t("page.SP_CI_PROFILE_EDIT_OTHER_ENABLED_HINT");
                } else {
                    hint += this.$t("page.SP_CI_PROFILE_EDIT_OTHER_DISABLED_HINT");
                }
            }
            return hint;
        },
        changePropDir: function (check_dir) {
            let cur_dir = this.field_data.direction;
            if (cur_dir == 'all') {
                this.field_data.direction = ((check_dir == 'stoc') ? 'ctos' : 'stoc');
            }
            else if (cur_dir == check_dir) {
                this.field_data.direction = '';
            }
            else if (cur_dir != '') {
                this.field_data.direction = 'all';
            }
            else {
                this.field_data.direction = ((check_dir == 'stoc') ? 'stoc' : 'ctos');
            }
        },
    },
    template: `
<div class="profile-param-dir-switch">
    <span v-if="getDirActive('stoc')" href="#" v-on:click="changePropDir('stoc')" :class="'params-change-direct to-crm enabled' + (getDirSelected('stoc')?' active':'')" v-b-tooltip.hover :title="getDirHint('stoc')"><i class="fa fa-arrow-alt-circle-right"></i></span>
    <span v-else :class="'params-change-direct to-crm'" v-b-tooltip.hover :title="getDirHint('stoc')"><i class="fa fa-arrow-alt-circle-right"></i></span>
    <span v-if="getDirActive('ctos')" href="#" v-on:click="changePropDir('ctos')" :class="'params-change-direct to-order enabled' + (getDirSelected('ctos')?' active':'')" v-b-tooltip.hover :title="getDirHint('ctos')"><i class="fa fa-arrow-alt-circle-left"></i></span>
    <span v-else :class="'params-change-direct to-order'" v-b-tooltip.hover :title="getDirHint('ctos')"><i class="fa fa-arrow-alt-circle-left"></i></span>
</div>
`,
});

// Info
Vue.component('profile-info', {
    props: [],
    template: `
<b-alert show variant="warning" v-html="$t('page.SP_CI_PROFILE_EDIT_INFO_SAVE_CHANGES')"></b-alert>
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
                stages: [],
                fields: [],
            },
            site: {
                user_groups: [],
                statuses: [],
                person_types: [],
                props: [],
                other_props: [],
                contact_fields: [],
                company_fields: [],
                conditions: [],
            },
        },
    },
    methods: {
        // Blocks update
        updateBlocks: function (calling_block) {
            this.startLoadingInfo();
            this.$emit('blocks_update_before', calling_block);
            this.ajaxReq('profile_info', 'post', {
                id: this.$profile_id,
            }, (response) => {
                this.info = response.data.info;
                this.ajaxReq('profile_get', 'post', {
                    id: this.$profile_id,
                }, (response) => {
                    this.$emit('blocks_update', response.data, calling_block);
                }, (response) => {
                }, (response) => {
                    // Callback success
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

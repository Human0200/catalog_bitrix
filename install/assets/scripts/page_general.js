
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
            this.ajaxReq('general_'+code+'_save', 'post', {
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
        });
    },
};


/**
 *
 * COMPONENTS
 *
 */

;


// Params of product list
Vue.component('general-main', {
    props: [],
    data: function () {
        return {
            code: 'main',
            state: {
                display: true,
                active: false,
            },
            fields: {
                contacts_sync_mode: '',
                contacts_phonemail_mode: '',
                contacts_link_mode: '',
                cancel_pays_by_cancel_order: '',
                link_responsibles: '',
                notif_errors: '',
            },
            info: {
            },
        }
    },
    template: `
<div class="card" v-bind:class="{ \'block-disabled\': state.active == false }" v-if="state.display">
    <div class="card-body">
        <h4 class="header-title">{{ $t("page.SP_CI_GENERAL_MAIN_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_GENERAL_MAIN_SUBTITLE") }}</p>
        <div class="form-group mb-3">
            <label>{{ $t("page.SP_CI_GENERAL_MAIN_PHONEMAIL_MODE") }}</label>
            <div class="radio radio-info mb-2">
                <input type="radio" v-model="fields.contacts_phonemail_mode" id="contacts_phonemail_mode_add" value="">
                <label for="contacts_phonemail_mode_add">{{ $t("page.SP_CI_GENERAL_MAIN_PHONEMAIL_MODE_ADD") }}</label>
            </div>
            <div class="radio radio-info mb-2">
                <input type="radio" v-model="fields.contacts_phonemail_mode" id="contacts_phonemail_mode_replace" value="replace">
                <label for="contacts_phonemail_mode_replace">{{ $t("page.SP_CI_GENERAL_MAIN_PHONEMAIL_MODE_REPLACE") }}</label>
            </div>
        </div>
        <div class="form-group mb-3">
            <label>{{ $t("page.SP_CI_GENERAL_MAIN_CONT_LINK") }}</label>
            <div class="radio radio-info mb-2">
                <input type="radio" v-model="fields.contacts_link_mode" id="contacts_cont_link_first" value="">
                <label for="contacts_cont_link_first">{{ $t("page.SP_CI_GENERAL_MAIN_CONT_LINK_FIRST") }}</label>
            </div>
            <div class="radio radio-info mb-2">
                <input type="radio" v-model="fields.contacts_link_mode" id="contacts_cont_link_uchange" value="user_change">
                <label for="contacts_cont_link_uchange">{{ $t("page.SP_CI_GENERAL_MAIN_CONT_LINK_UCHANGE") }}</label>
            </div>
        </div>
        <div class="form-group mb-3">
            <label>{{ $t("page.SP_CI_GENERAL_MAIN_OTHER") }}</label>
            <div class="checkbox checkbox-info mb-2">
                <input type="checkbox" id="general_cancel_pays_by_cancel_order" v-model="fields.cancel_pays_by_cancel_order">
                <label for="general_cancel_pays_by_cancel_order" v-b-tooltip.hover :title="$t('page.SP_CI_GENERAL_MAIN_CANCEL_PAYS_BY_CANCEL_ORDER_TOOLTIP')">{{ $t("page.SP_CI_GENERAL_MAIN_CANCEL_PAYS_BY_CANCEL_ORDER") }}</label>
            </div>
            <div class="checkbox checkbox-info mb-2">
                <input type="checkbox" id="general_main_link_responsibles" v-model="fields.link_responsibles">
                <label for="general_main_link_responsibles" v-b-tooltip.hover :title="$t('page.SP_CI_GENERAL_MAIN_LINK_RESPONSIBLES_TOOLTIP')">{{ $t("page.SP_CI_GENERAL_MAIN_LINK_RESPONSIBLES") }}</label>
            </div>
            <div class="checkbox checkbox-info">
                <input type="checkbox" id="general_main_notif_errors" v-model="fields.notif_errors">
                <label for="general_main_notif_errors" v-b-tooltip.hover :title="$t('page.SP_CI_GENERAL_MAIN_NOTIF_ERRORS_TOOLTIP')">{{ $t("page.SP_CI_GENERAL_MAIN_NOTIF_ERRORS") }}</label>
            </div>
        </div>
        <button class="btn btn-success" @click="blockSaveData(code)">
            {{ $t("page.SP_CI_GENERAL_SAVE") }}
        </button>
    </div> <!-- end card-body -->
</div> <!-- end card -->
`,
    mixins: [utilFuncs, componentsFuncs],
});

// Params of product list
Vue.component('general-products', {
    props: [],
    data: function () {
        return {
            code: 'products',
            state: {
                display: true,
                active: false,
            },
            fields: {
                products_no_discounts: '',
                products_discounts_perc: false,
                products_name_props: [''],
                products_name_props_delim: '',
                products_complects: '',
                products_delivery: '',
                products_deliv_prod_active: false,
                products_deliv_prod_ttlprod: false,
                products_deliv_prod_list: {},
                products_delivery_vat: '',
                products_delivery_vat_included: false,
                products_sync_type: '',
                products_root_section: '',
                products_group_by_orders: '',
                products_iblock: '',
                products_mixed_update_lock: '',
                products_sync_allow_delete: '',
            },
            info: {
                sections: [],
                iblocks: [],
                delivery_list: [],
                crm_vat_list: [],
            },
            selected: '',
        }
    },
    watch: {
        'info.delivery_list': function(new_val, old_val) {
            let delivery, delivery_i;
            if (!this.fields.products_deliv_prod_list) {
                this.fields.products_deliv_prod_list = {};
            }
            for (delivery_i in this.info.delivery_list) {
                delivery = this.info.delivery_list[delivery_i];
                if (this.fields.products_deliv_prod_list[delivery.id] === undefined) {
                    this.fields.products_deliv_prod_list[delivery.id] = '';
                }
            }
        },
        fields: {
            handler: function () {
                if (!this.fields.products_name_props) {
                    this.fields.products_name_props = [''];
                }
                else {
                    let last_value;
                    last_value = this.fields.products_name_props.length - 1;
                    if (this.fields.products_name_props[last_value] == '' && this.fields.products_name_props[last_value - 1] == '') {
                        this.fields.products_name_props.splice(last_value - 1, 1);
                    } else if (this.fields.products_name_props[last_value] != '') {
                        this.fields.products_name_props.push('');
                    }
                }
            },
            deep: true
        },
    },
    template: `
<div class="card" v-bind:class="{ \'block-disabled\': state.active == false }" v-if="state.display">
    <div class="card-body">
        <h4 class="header-title">{{ $t("page.SP_CI_GENERAL_PRODUCTS_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_GENERAL_PRODUCTS_SUBTITLE") }}</p>
        <div class="form-group mb-3">
            <div class="checkbox checkbox-info mb-2">
                <input type="checkbox" id="general_products_discounts_perc" v-model="fields.products_discounts_perc">
                <label for="general_products_discounts_perc" v-b-tooltip.hover :title="$t('page.SP_CI_GENERAL_PRODUCTS_DISCOUNTS_PERC_TOOLTIP')">{{ $t("page.SP_CI_GENERAL_PRODUCTS_DISCOUNTS_PERC") }}</label>
            </div>
        </div>
        <div class="form-group mb-2">
            <label>{{ $t("page.SP_CI_GENERAL_PRODUCTS_NAME_PROPS") }}</label>
            <input type="text" v-for="(item_v,item_i) in fields.products_name_props" v-model="fields.products_name_props[item_i]" class="form-control mb-2" v-b-tooltip.hover :title="$t('page.SP_CI_GENERAL_PRODUCTS_NAME_PROPS_HINT')" />
            <label v-if="fields.products_name_props.length > 1">{{ $t("page.SP_CI_GENERAL_PRODUCTS_NAME_PROPS_DELIM") }}</label>
            <input v-if="fields.products_name_props.length > 1" type="text" v-model="fields.products_name_props_delim" class="form-control" />
        </div>
        <div class="form-group mb-3">
            <label>{{ $t("page.SP_CI_GENERAL_PRODUCTS_COMPLECTS") }}</label>
            <div class="radio radio-info mb-2">
                <input type="radio" v-model="fields.products_complects" id="sync_products_complects_no" value="">
                <label for="sync_products_complects_no">{{ $t("page.SP_CI_GENERAL_PRODUCTS_COMPLECTS_NO") }}</label>
            </div>
            <div class="radio radio-info mb-2">
                <input type="radio" v-model="fields.products_complects" id="sync_products_complects_prod" value="prod">
                <label for="sync_products_complects_prod">{{ $t("page.SP_CI_GENERAL_PRODUCTS_COMPLECTS_PROD") }}</label>
            </div>
        </div>
        <b-card bg-variant="light">
            <div class="form-group mb-3">
                <label>{{ $t("page.SP_CI_GENERAL_PRODUCTS_DELIVERY") }}</label>
                <div class="radio radio-info mb-2">
                    <input type="radio" v-model="fields.products_delivery" id="sync_products_delivery_all" value="">
                    <label for="sync_products_delivery_all">{{ $t("page.SP_CI_GENERAL_PRODUCTS_DELIVERY_ALL") }}</label>
                </div>
                <div class="radio radio-info mb-2">
                    <input type="radio" v-model="fields.products_delivery" id="sync_products_delivery_notnull" value="notnull">
                    <label for="sync_products_delivery_notnull">{{ $t("page.SP_CI_GENERAL_PRODUCTS_DELIVERY_NOTNULL") }}</label>
                </div>
                <div class="radio radio-info mb-2">
                    <input type="radio" v-model="fields.products_delivery" id="sync_products_delivery_no" value="no">
                    <label for="sync_products_delivery_no">{{ $t("page.SP_CI_GENERAL_PRODUCTS_DELIVERY_NO") }}</label>
                </div>
            </div>
            <div class="form-group mb-3">
                <label>{{ $t("page.SP_CI_GENERAL_PRODUCTS_DELIVERY_VAT") }}</label>
                <div class="radio radio-info mb-2">
                    <input type="radio" v-model="fields.products_delivery_vat" id="sync_products_delivery_vat_no" value="">
                    <label for="sync_products_delivery_vat_no">{{ $t("page.SP_CI_GENERAL_PRODUCTS_DELIVERY_VAT_NO") }}</label>
                </div>
                <div v-for="item in info.crm_vat_list" class="radio radio-info mb-2">
                    <input type="radio" v-model="fields.products_delivery_vat" :id="'sync_products_delivery_' + item.id" :value="item.value">
                    <label :for="'sync_products_delivery_' + item.id">{{ item.name }}</label>
                </div>
                <div class="checkbox checkbox-info mt-3">
                    <input type="checkbox" id="sync_products_delivery_vat_included" v-model="fields.products_delivery_vat_included">
                    <label for="sync_products_delivery_vat_included">{{ $t("page.SP_CI_GENERAL_PRODUCTS_DELIVERY_VAT_INCLUDED") }}</label>
                </div>
            </div>
            <div class="form-group mb-3">
                <label>{{ $t("page.SP_CI_GENERAL_PRODUCTS_DELIV_PROD") }}</label>
                <div class="checkbox checkbox-info mb-2">
                    <input type="checkbox" id="sync_products_deliv_prod_active" v-model="fields.products_deliv_prod_active">
                    <label for="sync_products_deliv_prod_active" v-b-tooltip.hover :title="$t('page.SP_CI_GENERAL_PRODUCTS_DELIV_PROD_ACTIVE_TOOLTIP')">{{ $t("page.SP_CI_GENERAL_PRODUCTS_DELIV_PROD_ACTIVE") }}</label>
                </div>
                <div v-if="fields.products_deliv_prod_active" class="checkbox checkbox-info mb-2">
                    <input type="checkbox" id="sync_products_deliv_prod_ttlprod" v-model="fields.products_deliv_prod_ttlprod">
                    <label for="sync_products_deliv_prod_ttlprod" v-b-tooltip.hover :title="$t('page.SP_CI_GENERAL_PRODUCTS_DELIV_PROD_TTLPROD_TOOLTIP')">{{ $t("page.SP_CI_GENERAL_PRODUCTS_DELIV_PROD_TTLPROD") }}</label>
                </div>
                <table v-if="fields.products_deliv_prod_active" class="table mb-3 table-params table-params-props table-bordered">
                    <thead>
                        <tr>
                            <th class="param">{{ $t("page.SP_CI_GENERAL_PRODUCTS_DELIV_PROD_LIST_HEAD_DELIV") }}</th>
                            <th class="value">{{ $t("page.SP_CI_GENERAL_PRODUCTS_DELIV_PROD_LIST_HEAD_PROD") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="delivery in info.delivery_list">
                            <td class="param">{{delivery.name}} ({{delivery.id}})</td>
                            <td class="value">
                                <b-form-input v-model="fields.products_deliv_prod_list[delivery.id]" class="form-control"></b-form-input>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </b-card>
        <div class="form-group mb-3">
            <label>{{ $t("page.SP_CI_GENERAL_PRODUCTS_SYNC_TYPE") }}</label>
            <div class="radio radio-info mb-2">
                <input type="radio" v-model="fields.products_sync_type" id="sync_products_sync_type_no" value="">
                <label for="sync_products_sync_type_no">{{ $t("page.SP_CI_GENERAL_PRODUCTS_SYNC_TYPE_NO") }}</label>
            </div>
            <div class="radio radio-info mb-2">
                <input type="radio" v-model="fields.products_sync_type" id="sync_products_sync_type_find" value="find">
                <label for="sync_products_sync_type_find" v-b-tooltip.hover :title="$t('page.SP_CI_GENERAL_PRODUCTS_SYNC_TYPE_FIND_HINT')">{{ $t("page.SP_CI_GENERAL_PRODUCTS_SYNC_TYPE_FIND") }}</label>
            </div>
            <div class="radio radio-info mb-2">
                <input type="radio" v-model="fields.products_sync_type" id="sync_products_sync_type_create" value="create">
                <label for="sync_products_sync_type_create" v-b-tooltip.hover :title="$t('page.SP_CI_GENERAL_PRODUCTS_SYNC_TYPE_CREATE_HINT')">{{ $t("page.SP_CI_GENERAL_PRODUCTS_SYNC_TYPE_CREATE") }}</label>
            </div>
            <div class="radio radio-info mb-2">
                <input type="radio" v-model="fields.products_sync_type" id="sync_products_sync_type_mixed" value="mixed">
                <label for="sync_products_sync_type_mixed" v-b-tooltip.hover :title="$t('page.SP_CI_GENERAL_PRODUCTS_SYNC_TYPE_MIXED_HINT')">{{ $t("page.SP_CI_GENERAL_PRODUCTS_SYNC_TYPE_MIXED") }}</label>
            </div>
            <div class="radio radio-info mb-2">
                <input type="radio" v-model="fields.products_sync_type" id="sync_products_sync_type_find_new" value="find_new">
                <label for="sync_products_sync_type_find_new" v-b-tooltip.hover :title="$t('page.SP_CI_GENERAL_PRODUCTS_SYNC_TYPE_FIND_NEW_HINT')">{{ $t("page.SP_CI_GENERAL_PRODUCTS_SYNC_TYPE_FIND_NEW") }}</label>
            </div>
        </div>
        <div v-if="fields.products_sync_type == 'mixed'" class="form-group mb-3">
            <div class="checkbox checkbox-info">
                <input type="checkbox" id="general_products_mixed_update_lock" v-model="fields.products_mixed_update_lock">
                <label for="general_products_mixed_update_lock">{{ $t("page.SP_CI_GENERAL_PRODUCTS_MIXED_UPDATE_LOCK") }}</label>
            </div>
        </div>
        <div v-if="fields.products_sync_type != '' && fields.products_sync_type != 'find_new'" class="form-group mb-3">
            <label>{{ $t("page.SP_CI_GENERAL_PRODUCTS_ROOT_SECTION") }}</label>
            <b-form-select v-model="fields.products_root_section">
                <option value="">{{ $t("page.SP_CI_GENERAL_PRODUCTS_ROOT_SECTION_ROOT") }}</option>
                <option v-for="field in info.sections" :value="field.id">{{field.name}}</option>
            </b-form-select>
        </div>
        <div v-if="fields.products_sync_type == 'create'" class="form-group mb-3">
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="general_products_group_by_orders" v-model="fields.products_group_by_orders" value="Y">
                <label class="custom-control-label" for="general_products_group_by_orders">{{ $t("page.SP_CI_GENERAL_PRODUCTS_GROUP_BY_ORDERS") }}</label>
            </div>
        </div>
        <div v-if="fields.products_sync_type == 'create' || fields.products_sync_type == 'mixed'" class="form-group mb-3">
            <label>{{ $t("page.SP_CI_GENERAL_PRODUCTS_IBLOCK") }}</label>
            <b-form-select v-model="fields.products_iblock">
                <option v-for="field in info.iblocks" :value="field.id">{{field.name}} ({{field.id}})</option>
            </b-form-select>
            <b-alert class="mt-2" show>{{ $t("page.SP_CI_GENERAL_PRODUCTS_IBLOCK_HINT") }}</b-alert>
        </div>
        <div v-if="fields.products_sync_type == 'find_new'">
            <div class="checkbox checkbox-info mb-3">
                <input type="checkbox" id="sync_products_sync_allow_delete" v-model="fields.products_sync_allow_delete">
                <label for="sync_products_sync_allow_delete" v-b-tooltip.hover :title="$t('page.SP_CI_GENERAL_PRODUCTS_SYNC_ALLOW_DELETE_HINT')">{{ $t("page.SP_CI_GENERAL_PRODUCTS_SYNC_ALLOW_DELETE") }}</label>
            </div>
            <b-card bg-variant="light">
                <div v-html="$t('page.SP_CI_GENERAL_PRODUCTS_SYNC_TYPE_FIND_NEW_INFO')"></div>
                <b-alert show v-html="$t('page.SP_CI_GENERAL_PRODUCTS_SYNC_TYPE_FIND_NEW_AD')" variant="success"></b-alert>
            </b-card>
        </div>
        <button class="btn btn-success" @click="blockSaveData(code)">
            {{ $t("page.SP_CI_GENERAL_SAVE") }}
        </button>
    </div> <!-- end card-body -->
</div> <!-- end card -->
`,
    mixins: [utilFuncs, componentsFuncs],
});

// Params of products synchronization
Vue.component('general-prodsync', {
    props: [],
    data: function () {
        return {
            code: 'prodsync',
            state: {
                display: true,
                active: false,
            },
            fields: {
                products_comp_table: {},
            },
            info: {
                products_iblock: '',
                store_prod_fields: [],
                crm_prod_fields: [],
            },
        }
    },
    watch: {
        'fields.products_comp_table': function(new_val, old_val) {
            let crm_field, crm_field_i;
            if (!this.fields.products_comp_table) {
                this.fields.products_comp_table = {};
            }
            for (crm_field_i in this.info.crm_prod_fields) {
                crm_field = this.info.crm_prod_fields[crm_field_i];
                if (this.fields.products_comp_table[this.info.products_iblock] === undefined) {
                    this.fields.products_comp_table[this.info.products_iblock] = {};
                }
                if (this.fields.products_comp_table[this.info.products_iblock][crm_field.id] === undefined) {
                    this.fields.products_comp_table[this.info.products_iblock][crm_field.id] = {
                        direction: 'all',
                        value: '',
                    };
                }
            }
        },
    },
    template: `
<div class="card" v-bind:class="{ \'block-disabled\': state.active == false }" v-if="state.display">
    <div class="card-body">
        <h4 class="header-title">{{ $t("page.SP_CI_GENERAL_PRODSYNC_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_GENERAL_PRODSYNC_SUBTITLE") }}</p>
        <div class="form-group mb-3">
            <label>{{ $t("page.SP_CI_GENERAL_PRODSYNC_COMP_TABLE") }}</label>
            <table class="table mb-3 table-params table-params-props table-bordered">
                <thead>
                <tr>
                    <th class="param"><i class="icon icon-bitrix24"></i> {{ $t("page.SP_CI_GENERAL_PRODSYNC_COMP_TABLE_HEAD_B24") }}</th>
                    <th class="value"><i class="icon icon-bitrix"></i> {{ $t("page.SP_CI_GENERAL_PRODSYNC_COMP_TABLE_HEAD_STORE") }}</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="crm_field in info.crm_prod_fields">
                    <td class="param">{{crm_field.name}} ({{crm_field.id}})</td>
                    <td class="value">
                        <b-form-select v-model="fields.products_comp_table[info.products_iblock][crm_field.id].value">
                            <option value="">{{ $t('page.SP_CI_GENERAL_PRODSYNC_COMP_TABLE_NOT_SYNC') }}</option>
                            <optgroup v-for="(group,group_id) in info.store_prod_fields" :label="group.title">
                                <option v-for="(field,field_i) in group.items" :value="field.id">{{field.name}} ({{field.id}})</option>
                            </optgroup>
                        </b-form-select>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <button class="btn btn-success" @click="blockSaveData(code)">
            {{ $t("page.SP_CI_GENERAL_SAVE") }}
        </button>
    </div> <!-- end card-body -->
</div> <!-- end card -->
`,
    mixins: [utilFuncs, componentsFuncs],
});

// Params of products search
Vue.component('general-prodsearch', {
    props: [],
    data: function () {
        return {
            code: 'prodsearch',
            state: {
                display: true,
                active: false,
            },
            fields: {
                products_search_store_fields: [],
                products_search_crm_fields: [],
                products_search_crm_field: '',
            },
            info: {
                products_iblock: '',
                products_sync_type: '',
                store_prod_fields: [],
                crm_prod_fields: [],
                crm_prod_fields_new: [],
                store_iblocks: [],
                crm_iblocks: [],
            },
        }
    },
    template: `
<div class="card" v-bind:class="{ \'block-disabled\': state.active == false }" v-if="state.display">
    <div class="card-body">
        <h4 class="header-title">{{ $t("page.SP_CI_GENERAL_PRODSEARCH_TITLE") }}</h4>
        <p class="sub-header">{{ $t("page.SP_CI_GENERAL_PRODSEARCH_SUBTITLE") }}</p>
        <div class="form-group mb-2">
            <label>{{ $t("page.SP_CI_GENERAL_PRODSEARCH_STORE_FIELD") }}</label>
            <table class="table mb-3 table-params table-params-props table-bordered">
                <thead>
                <tr>
                    <th class="param">{{ $t("page.SP_CI_GENERAL_PRODSEARCH_STORE_FIELDS_HEAD_IBLOCK") }}</th>
                    <th class="value">{{ $t("page.SP_CI_GENERAL_PRODSEARCH_STORE_FIELDS_HEAD_FIELD") }}</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="iblock in info.store_iblocks">
                    <td class="param">{{iblock.name}} ({{iblock.id}})</td>
                    <td class="value">
                        <b-form-select v-model="fields.products_search_store_fields[iblock.id]">
                            <option value="">{{ $t('page.SP_CI_GENERAL_PRODSEARCH_FIELD_NOTSET') }}</option>
                            <optgroup v-for="(group,group_id) in info.store_prod_fields[iblock.id]" :label="group.title">
                                <option v-for="(field,field_i) in group.items" :value="field.id">{{field.name}} ({{field.id}})</option>
                            </optgroup>
                        </b-form-select>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <div v-if="info.products_sync_type != 'find_new'" class="form-group mb-3"> 
            <label>{{ $t("page.SP_CI_GENERAL_PRODSEARCH_CRM_FIELD") }}</label>
            <b-form-select v-model="fields.products_search_crm_field">
                <option value="">{{ $t('page.SP_CI_GENERAL_PRODSEARCH_FIELD_NOTSET') }}</option>
                <option v-for="(field,field_i) in info.crm_prod_fields" :value="field.id">{{field.name}} ({{field.id}})</option>
            </b-form-select>
        </div>
        <div v-else class="form-group mb-3">
            <label>{{ $t("page.SP_CI_GENERAL_PRODSEARCH_CRM_FIELD") }}</label>
            <table class="table mb-3 table-params table-params-props table-bordered">
                <thead>
                <tr>
                    <th class="param">{{ $t("page.SP_CI_GENERAL_PRODSEARCH_CRM_FIELDS_HEAD_IBLOCK") }}</th>
                    <th class="value">{{ $t("page.SP_CI_GENERAL_PRODSEARCH_CRM_FIELDS_HEAD_FIELD") }}</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="iblock in info.crm_iblocks">
                    <td class="param">{{iblock.name}} ({{iblock.id}})</td>
                    <td class="value">
                        <b-form-select v-model="fields.products_search_crm_fields[iblock.id]">
                            <option value="">{{ $t('page.SP_CI_GENERAL_PRODSEARCH_FIELD_NOTSET') }}</option>
                            <optgroup v-for="(group,group_id) in info.crm_prod_fields_new[iblock.id]" :label="group.title">
                                <option v-for="(field,field_i) in group.items" :value="field.id">{{field.name}} ({{field.id}})</option>
                            </optgroup>
                        </b-form-select>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <button class="btn btn-success" @click="blockSaveData(code)">
            {{ $t("page.SP_CI_GENERAL_SAVE") }}
        </button>
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
            // Blocks update
            this.$emit('blocks_update_before', calling_block);
            this.ajaxReq('general_get', 'get', {
                id: this.$profile_id,
            }, (response) => {
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


/**
 *
 * SETTINGS AND EXTERNAL VALUES
 *
 */

Vue.prototype.$order_id = order_id;


/**
 *
 * COMPONENTS
 *
 */

// Filter
Vue.component('products-filter', {
    mixins: [utilFuncs],
    props: ['filter','iblock_list','section_list'],
    template: `
<div class="row">
    <div class="col-12">
        <div class="card-box">
            <b-form-input v-model="filter.name" :placeholder="$t('page.SP_CI_PRODUCTS_EDIT_FILTER_FIELD_NAME')" class="mb-3" @change="$emit('block_update')"></b-form-input>
            <b-form-select v-model="filter.iblock" class="mb-3" @change="$emit('block_update')">
                <option value="">{{ $t("page.SP_CI_PRODUCTS_EDIT_FILTER_FIELD_IBLOCK_ALL") }}</option>
                <option v-for="(iblock, iblock_i) in iblock_list" :value="iblock.id">{{iblock.name}} ({{iblock.id}})</option>
            </b-form-select>
            <b-form-select v-if="filter.iblock" v-model="filter.section" @change="$emit('block_update')">
                <option value="">{{ $t("page.SP_CI_PRODUCTS_EDIT_FILTER_FIELD_SECTION_ALL") }}</option>
                <option v-for="(section_name, section_id) in section_list" :value="section_id">{{section_name}} ({{section_id}})</option>
            </b-form-select>
        </div> <!-- end card-box -->
    </div> <!-- end col -->
</div>
`,
});

// Settings of products list fields
Vue.component('products-list-fields', {
    mixins: [utilFuncs],
    props: ['list', 'selected', 'iblock_id', 'id'],
    methods: {
        save: function () {
            axios
                .post(this.getReqPath('products_edit_save_fields'), {
                    iblock: this.iblock_id,
                    fields: this.selected,
                })
                .then(response => {
                    if (response.data.status == 'ok') {
                        // Update list
                        this.$emit('update_list', 1);
                        // Close window
                        this.$bvModal.hide('products_list_settings' + this.id)
                    }
                    // this.stopLoadingInfo();
                })
                .catch(error => {
                    console.log(error);
                });
        }
    },
    template: `
<b-row>
    <b-col>
        <b-button v-b-modal="'products_list_settings' + id" size="sm" variant="light">{{$t("page.SP_CI_PRODUCTS_EDIT_FIELDS_LINK")}}</b-button>
    </b-col>
    <b-modal :id="'products_list_settings' + id" :title="$t('page.SP_CI_PRODUCTS_EDIT_FIELDS_TITLE')" scrollable centered>
        <b-form-group>
            <b-form-checkbox-group
                :id="'list_fields_' + id"
                :name="'list_fields_' + id"
                v-model="selected"
                stacked
            >
                <b-form-checkbox v-for="field in list" :value="field.key">{{field.name}}</b-form-checkbox>
            </b-form-checkbox-group>
        </b-form-group>
        <template #modal-footer>
            <b-button size="sm" variant="success" class="float-right" @click="save">{{$t("page.SP_CI_PRODUCTS_EDIT_FIELDS_SAVE")}}</b-button>
        </template>
    </b-modal>
</b-row>
`,
});

// Products list
Vue.component('products-list', {
    mixins: [utilFuncs],
    props: ['filter', 'list', 'count', 'changed', 'page', 'fields_list', 'fields_sel', 'fields_all'],
    watch: {
        changed: function () {
            this.$refs.products.refresh();
        },
    },
    methods: {
        updateList: function () {
            this.$emit('page_change', 1);
        },
        changePage: function (value) {
            this.$emit('page_change', value);
        }
    },
    template: `
<div class="row">
    <div class="col-12" v-if="count !== false">
        <b-alert show variant="info">{{ $t("page.SP_CI_PRODUCTS_EDIT_LIST_COUNT") }}: {{count}}</b-alert>
    </div>
    <div class="col-12">
        <b-card>
            <div class="mb-2">
                <products-list-fields :iblock_id="filter.iblock" :list="fields_all" :selected="fields_sel" @update_list="updateList"></products-list-fields>
            </div>
            <b-table hover :items="list" :fields="fields_list" id="products" ref="products" head-variant="light">
                <template #table-busy>
                    <div class="text-center text-danger my-2">
                        <b-spinner class="align-middle"></b-spinner>
                    </div>
                </template>
                <template v-slot:cell(NAME)="data">
                    <span v-html="data.value"></span>
                </template>
                <template v-slot:cell(PAGE_URL)="data">
                    <span v-html="data.value"></span>
                </template>
                <template v-slot:cell(PICTURE)="data">
                    <span v-html="data.value"></span>
                </template>
                <template v-slot:cell(action)="data">
                    <template v-if="data.item.SKU_COUNT == 0">
                        <b-button v-if="!data.value" @click="$emit('item_add', data.index)" variant="success">{{ $t("page.SP_CI_PRODUCTS_EDIT_LIST_ADD") }}</b-button>
                        <b-button v-if="data.value">{{ $t("page.SP_CI_PRODUCTS_EDIT_LIST_ADDED") }}</b-button>
                    </template>
                    <template v-else>
                        <b-button v-b-modal="'modal_sku_' + data.item.ID" size="sm" :title="$t('page.SP_CI_PRODUCTS_EDIT_LIST_SKU_LINK_HINT')">{{$t("page.SP_CI_PRODUCTS_EDIT_LIST_SKU_LINK")}} ({{data.item.SKU_COUNT}})</b-button>
                        <b-modal :id="'modal_sku_' + data.item.ID" :title="data.item.NAME" size="xl" hide-footer centered>
                            <products-sku-list :product_id="data.item.ID" :filter="filter"></products-sku-list>
                        </b-modal>
                    </template>
                </template>
            </b-table>
        </b-card>
        <b-pagination
            v-model="page"
            variant="info"
            :total-rows="count"
            :per-page="10"
            :disabled="!count"
            @change="changePage"
        ></b-pagination>
    </div>
</div>
`,
});

// Products list (sku)
Vue.component('products-sku-list', {
    mixins: [utilFuncs],
    props: ['filter', 'product_id'],
    data: function () {
        return {
            fields_sel: [],
            fields_all: [],
            fields_list: [],
            list: [],
            busy: false,
            iblock_id: false,
        }
    },
    methods: {
        loadList: function () {
            this.busy = true;
            axios
                .post(this.getReqPath('products_edit_sku_list'), {
                    iblock: this.filter.iblock,
                    product_id: this.product_id,
                })
                .then(response => {
                    let i;
                    if (response.data.status == 'ok') {
                        this.iblock_id = response.data.iblock_id;
                        this.list = response.data.list;
                        // Displayed fields of list
                        this.fields_sel = response.data.fields_sel;
                        this.fields_list = [
                            {
                                key: 'action',
                                label: this.$t("page.SP_CI_PRODUCTS_EDIT_LIST_COL_ACTION"),
                                sortable: false,
                            },
                        ];
                        for (i in response.data.fields_list) {
                            field = response.data.fields_list[i]
                            this.fields_list.push({
                                key: field.id,
                                label: field.name,
                                sortable: false,
                            })
                        }
                        // Fields for list settings window
                        this.fields_all = [];
                        for (i in response.data.fields_all) {
                            field = response.data.fields_all[i]
                            this.fields_all.push({
                                key: field.id,
                                name: field.name,
                            })
                        }
                    }
                    // Callback success
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                    this.busy = false;
                })
                .catch(error => {
                    console.log(error);
                    this.busy = false;
                });
        },
        addItem: function (index) {
            let item_id = this.list[index].ID;
            if (item_id) {
                this.busy = true;
                axios
                    .post(this.getReqPath('products_edit_add_item'), {
                        order_id: this.$order_id,
                        item_id: item_id,
                    })
                    .then(response => {
                        if (response.data.status == 'ok') {
                            this.list[index].action = true;
                            this.$root.$emit('bv::refresh::table', 'products_sku_' + this.product_id)
                        }
                        this.busy = false;
                    })
                    .catch(error => {
                        this.busy = false;
                        console.log(error);
                    });
            }
        },
    },
    mounted() {
        this.loadList();
    },
    template: `
<div>
    <div class="mb-2">
        <products-list-fields :list="fields_all" :selected="fields_sel" :iblock_id="iblock_id" id="product_id" @update_list="loadList"></products-list-fields>
    </div>
    <b-table responsive hover :items="list" :busy="busy" :fields="fields_list" :id="'products_sku_' + product_id" :ref="'products_sku_' + product_id" head-variant="light">
        <template #table-busy>
            <div class="text-center text-danger my-2">
                <b-spinner class="align-middle"></b-spinner>
            </div>
        </template>
        <template v-slot:cell(NAME)="data">
            <span v-html="data.value"></span>
        </template>
        <template v-slot:cell(PAGE_URL)="data">
            <span v-html="data.value"></span>
        </template>
        <template v-slot:cell(PICTURE)="data">
            <span v-html="data.value"></span>
        </template>
        <template v-slot:cell(action)="data">
            <b-button v-if="!data.value" @click="addItem(data.index)" variant="success">{{ $t("page.SP_CI_PRODUCTS_EDIT_LIST_ADD") }}</b-button>
            <b-button v-if="data.value">{{ $t("page.SP_CI_PRODUCTS_EDIT_LIST_ADDED") }}</b-button>
        </template>
    </b-table>
</div>
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
        filter: {
            name: '',
            iblock: '',
            section: '',
        },
        products_list: {},
        products_fields_all: [],
        products_fields_sel: [],
        products_fields_list: [],
        products_changed: 0,
        products_count: 0,
        products_page: 1,
        iblock_list: {},
        section_list: {},
    },
    methods: {
        // Filter update
        updateFilter: function (callback) {
            this.startLoadingInfo();
            axios
                .post(this.getReqPath('products_edit_filter_data'), {
                    iblock: this.filter.iblock,
                })
                .then(response => {
                    if (response.data.status == 'ok') {
                        this.iblock_list = response.data.iblocks;
                        this.section_list = response.data.sections;
                    }
                    // Callback success
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                    this.stopLoadingInfo();
                })
                .catch(error => {
                    console.log(error);
                });
        },
        // List update
        updateList: function (page, callback) {
            this.products_page = page;
            this.startLoadingInfo();
            axios
                .post(this.getReqPath('products_edit_items_list'), {
                    filter: this.filter,
                    page: page,
                })
                .then(response => {
                    if (response.data.status == 'ok') {
                        let i, field;
                        this.products_list = response.data.list;
                        this.products_count = response.data.count;
                        this.products_fields_sel = response.data.fields_sel;
                        // Displayed fields of list
                        this.products_fields_list = [
                            {
                                key: 'action',
                                label: this.$t("page.SP_CI_PRODUCTS_EDIT_LIST_COL_ACTION"),
                                sortable: false,
                            },
                        ];
                        for (i in response.data.fields_list) {
                            field = response.data.fields_list[i]
                            this.products_fields_list.push({
                                key: field.id,
                                label: field.name,
                                sortable: false,
                            })
                        }
                        // Fields for list settings window
                        this.products_fields_all = [];
                        for (i in response.data.fields_all) {
                            field = response.data.fields_all[i]
                            this.products_fields_all.push({
                                key: field.id,
                                name: field.name,
                            })
                        }
                    }
                    // Callback success
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                    this.stopLoadingInfo();
                })
                .catch(error => {
                    console.log(error);
                });
        },
        // Blocks update
        updateBlocks: function () {
            this.updateFilter(() => {
                this.updateList(1);
            });
        },
        addItem: function (index) {
            let item_id = this.products_list[index].ID;
            if (item_id) {
                this.startLoadingInfo();
                axios
                    .post(this.getReqPath('products_edit_add_item'), {
                        order_id: this.$order_id,
                        item_id: item_id,
                    })
                    .then(response => {
                        if (response.data.status == 'ok') {
                            this.products_list[index].action = true;
                            this.products_changed++;
                        }
                        this.stopLoadingInfo();
                    })
                    .catch(error => {
                        console.log(error);
                    });
            }
        },
    },
    mounted() {
        this.updateBlocks();
    },
});


/**
 *
 * SETTINGS AND EXTERNAL VALUES
 *
 */

Vue.prototype.$order_id = order_id;
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
            return '/bitrix/sprod_integr_ajax.php?action=' + action + '&sc=' + this.$secure_code;
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

// Filter
Vue.component('uf-orderuser', {
    mixins: [utilFuncs, componentsFuncs],
    props: ['user_id'],
    data: function () {
        return {
            btn_saving: false,
            users_list: [],
        }
    },
    methods: {
        findOrderuser (text, loader) {
            if (text.length > 0) {
                this.users_list = [];
            }
            if (text.length > 2) {
                this.users_list = this.findUser(text);
            }
        },
        findUser (text) {
            let list, i, item;
            list = [];
            this.startLoadingInfo();
            axios
                .post(this.getReqPath('otherfunc_find_user'), {
                    search: text,
                })
                .then(response => {
                    if (response.data.status == 'ok') {
                        for (i in response.data.list) {
                            item = response.data.list[i];
                            list.push({
                                "code": parseInt(item.code),
                                "label": item.label
                            });
                        }
                    }
                    this.stopLoadingInfo();
                })
                .catch(error => {
                    console.log(error);
                    this.stopLoadingInfo();
                });
            return list;
        },
        saveOrderuser () {
            this.startLoadingInfo();
            axios
                .post(this.getReqPath('custom_fields_orderuser_save'), {
                    order_id: this.$order_id,
                    user_id: this.user_id,
                })
                .then(response => {
                    this.stopLoadingInfo();
                })
                .catch(error => {
                    this.stopLoadingInfo();
                    console.log(error);
                });
        },
        startLoadingInfo() {
            this.btn_saving = true;
        },
        stopLoadingInfo() {
            this.btn_saving = false;
        },
    },
    mounted() {
        if (this.user_id > 0) {
            this.users_list = this.findUser(this.user_id);
        }
    },
    template: `
<b-row>
    <b-col>
        <b-card v-bind:class="{ \'block-disabled\': btn_saving }">
            <div class="form-group">
                <v-select @search="findOrderuser" v-model="user_id" :reduce="item => item.code" :options="users_list">
                    <div slot="no-options">{{ $t("page.SP_CI_CUSTOM_FIELDS_SELECT2_EMPTY") }}</div>
                </v-select>
            </div>
            <button class="btn btn-success" @click="saveOrderuser()">{{ $t("page.SP_CI_CUSTOM_FIELDS_CHANGE") }}</button>
        </b-card> <!-- end card-box -->
    </b-col> <!-- end col -->
</b-row>
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
    },
    methods: {
    },
    mounted() {
    },
});

/**
 * Page: Forgotten basket profiles list
 * Component: fbasket-profiles-list
 */

/**
 *
 * COMPONENTS
 *
 */

// Forgotten basket profiles list
Vue.component('fbasket-profiles-list', {
    mixins: [utilFuncs, mainFuncs],
    data: function () {
        return {
            list: [],
            list_loading: false
        }
    },
    template: `
<div class="row">
    <div class="col-lg-6" v-for="item in list">
        <div class="card-box">
            <span class="badge bg-soft-success text-success float-right" v-if="item.active == 'Y'">{{ $t("page.SP_CI_FBASKET_PROFILES_ACTIVE_Y") }}</span>
            <span class="badge bg-soft-danger text-danger float-right" v-if="item.active != 'Y'">{{ $t("page.SP_CI_FBASKET_PROFILES_ACTIVE_N") }}</span>
            <h4 class="header-title"><a href="#" class="text-dark">{{item.name}}</a></h4>
            <a :href="'sprod_integr_fbasket_profile_edit.php?id=' + item.id + '&lang=ru'" target="_top" class="btn btn-info waves-effect mt-2"><i class="mdi mdi-pencil"></i> {{ $t("page.SP_CI_FBASKET_PROFILES_BTN_EDIT") }}</a>
        </div> <!-- end card-box -->
    </div> <!-- end col -->
    <div class="col-lg-6">
        <button v-if="!list_loading" type="button" class="btn btn-info ml-3 mb-3" @click="addItem"><i class="mdi mdi-plus"></i> {{ $t("page.SP_CI_FBASKET_PROFILES_BTN_ADD") }}</button>
    </div> <!-- end col -->
</div>
`,
    methods: {
        updateList: function (callback) {
            this.$emit('load_start');
            this.list_loading = true;
            this.ajaxReq('fbasket_profiles_list', 'post', {}, (response) => {
                this.list_loading = false;
                this.list = response.data.list || [];
            }, (response) => {
                this.list_loading = false;
            }, (response) => {
                this.list_loading = false;
                if (typeof callback === 'function') {
                    callback(response);
                }
                this.$emit('load_stop');
            });
        },
        addItem: function (callback) {
            this.$emit('load_start');
            this.ajaxReq('fbasket_profiles_add', 'post', {}, (response) => {
                this.updateList();
            }, (response) => {
            }, (response) => {
                if (typeof callback === 'function') {
                    callback(response);
                }
                this.$emit('load_stop');
            });
        },
    },
    mounted() {
        this.updateList();
    },
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
    },
    methods: {
        startLoadingInfo: function() {
            this.loader_counter++;
        },
        stopLoadingInfo: function() {
            this.loader_counter--;
        },
    },
});

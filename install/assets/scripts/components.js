
/**
 *
 * MIXINS
 *
 */

var utilFuncs = {
    methods: {
        getReqPath: function (action) {
            return '/bitrix/admin/sprod_integr_ajax.php?action=' + action;
        },
    },
};

var mainFuncs = {
    data: function () {
        return {
            loader_counter: 0,
            errors: [],
            warnings: [],
        }
    },
    methods: {
        ajaxReq: function (action, type, params, success, failure, callback) {
            if (type == 'post') {
                axios
                    .post(this.getReqPath(action), params)
                    .then(response => {
                        if (response.data.status == 'ok') {
                            if (response.data.errors && response.data.errors.length) {
                                for (error_i in response.data.errors) {
                                    Vue.set(this.errors, this.errors.length, response.data.errors[error_i]);
                                }
                            }
                            // Callback success
                            if (typeof success === 'function') {
                                success(response);
                            }
                        } else {
                            // Callback failure
                            if (typeof failure === 'function') {
                                failure(response);
                            }
                            if (response.data.error) {
                                this.errors.push(response.data.error);
                            }
                        }
                        // Callback for all
                        if (typeof callback === 'function') {
                            callback(response);
                        }
                    })
                    .catch(error => {
                        console.log(error);
                    });
            }
            else {
                axios
                    .get(this.getReqPath(action))
                    .then(response => {
                        if (response.data.status == 'ok') {
                            if (response.data.errors && response.data.errors.length) {
                                for (error_i in response.data.errors) {
                                    Vue.set(this.errors, this.errors.length, response.data.errors[error_i]);
                                }
                            }
                            // Callback success
                            if (typeof success === 'function') {
                                success(response);
                            }
                        } else {
                            // Callback failure
                            if (typeof failure === 'function') {
                                failure(response);
                            }
                            if (response.data.error) {
                                this.errors.push(response.data.error);
                            }
                        }
                        // Callback for all
                        if (typeof callback === 'function') {
                            callback(response);
                        }
                    })
                    .catch(error => {
                        console.log(error);
                    });
            }
        },
        getReqPath: function (action) {
            return '/bitrix/admin/sprod_integr_ajax.php?action=' + action;
        },
        startLoadingInfo: function () {
            this.loader_counter++;
        },
        stopLoadingInfo: function () {
            this.loader_counter--;
            if (this.loader_counter < 0) {
                this.loader_counter = 0;
            }
        },
    },
    mounted() {
        // Check module state
        axios
            .get(this.getReqPath('main_check'))
            .then(response => {
                if (response.data.errors && response.data.errors.length) {
                    this.errors = response.data.errors;
                }
                if (response.data.warnings && response.data.warnings.length) {
                    this.warnings = response.data.warnings;
                }
            })
            .catch(error => {
                console.log(error);
            });
    },
};


/**
 *
 * VUE COMPONENTS
 *
 */

// Error
Vue.component('main-errors', {
    props: ['errors', 'warnings'],
    template: `
    <div class="main-errors">
        <b-alert show variant="danger" v-for="(item,item_i) in errors"><span v-html="item.message + ' [' + item.code + ']'"></span> <i v-if="item.hint" class="fa fa-question-circle help-link-icon" :id="'err_hint_' + item_i"></i></b-alert>
        <b-popover v-for="(item,item_i) in errors" :target="'err_hint_' + item_i" triggers="hover focus"><p v-html="item.hint"></p></b-popover>
        <b-alert show variant="warning" v-for="(item,item_i) in warnings"><span v-html="item.message"></span> <i v-if="item.hint" class="fa fa-question-circle help-link-icon" :id="'warn_hint_' + item_i"></i></b-alert>
        <b-popover v-for="(item,item_i) in warnings" :target="'warn_hint_' + item_i" triggers="hover focus"><p v-html="item.hint"></p></b-popover>
    </div>
`,
});

// Loader
Vue.component('loader', {
    props: ['counter'],
    template: `
    <div class="loader float-right" v-if="counter">
        <div class="spinner-border text-info m-2" role="status"></div>
    </div>
`,
});

Vue.component('v-select', VueSelect.VueSelect);

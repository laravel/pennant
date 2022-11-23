import Vue from 'vue';
import Base from './base';
import axios from 'axios';
import Routes from './routes';
import VueRouter from 'vue-router';
import moment from 'moment-timezone';

let token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

Vue.use(VueRouter);

window.Popper = require('popper.js').default;

moment.tz.setDefault(Telescope.timezone);

window.LaravelPackage.basePath = '/' + window.LaravelPackage.path;

let routerBasePath = window.LaravelPackage.basePath + '/';

if (window.LaravelPackage.path === '' || window.LaravelPackage.path === '/') {
    routerBasePath = '/';
    window.LaravelPackage.basePath = '';
}

const router = new VueRouter({
    routes: Routes,
    mode: 'history',
    base: routerBasePath,
});

Vue.component('alert', require('./components/Alert.vue').default);

Vue.mixin(Base);

new Vue({
    el: '#laravel-package',

    router,

    data() {
        return {
            autoLoadsNewEntries: localStorage.autoLoadsNewEntries === '1',
        };
    },
});

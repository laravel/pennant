export default [
    { path: '/', redirect: '/dashboard' },

    {
        path: '/dashboard',
        name: 'dashboard',
        component: require('./screens/dashboard').default,
    }
];

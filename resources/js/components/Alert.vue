<template>
    <div id="alertMessage">
        <p>{{ message }}</p>
    </div>
</template>

<script>
    import $ from 'jquery';

    export default {
        mounted() {
            // ...
        },
    }
</script>

<style>
    #alertMessage {
        background: rgba(0, 0, 0, 0.5);
    }
</style>

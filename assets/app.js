import { createApp } from 'vue';
import FloatingVue from 'floating-vue';
import App from './components/App.vue';
import './styles/app.css';
import 'floating-vue/dist/style.css';

createApp(App)
    .use(FloatingVue)
    .mount('#app');

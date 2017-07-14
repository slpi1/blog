var vm = new Vue({
    el: '#password',
    
    data: {
        userName: '',
        domain: '',
        hash: '',
        password:'',
        
        copy:false
    },
    mounted(){
        new Clipboard('.btn');
    },
    methods: {
        getPassword: function () {
            var char = [1,4,7,10,13,16,19,22,25,28,31],
                g = md5(this.userName + "@" + this.domain + ":" + this.hash),
                t = '';
            
            char.forEach(function(i){
                t += ( i > 15 ) ? g[i].toUpperCase() : g[i];
            })
            
            this.password = t;
            this.copy = true
        }
    }
})
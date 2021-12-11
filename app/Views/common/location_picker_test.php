<?=view('home/header')?>





<button onclick="popup()">Address select</button>

<script>
function popup(){
    App.loadWindow('/Location/pickerModal',{coordsStart:[44.936811304504644,34.0396306145191],addressStart:'Россия, Республика Крым, Симферополь, улица Тав-Даир, 47'}).progress(function(status,data){
       console.log(status,data); 
    });
}
$(popup);
</script>
<?=view('home/footer')?>
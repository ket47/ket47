<html>
    <head>
        <title></title>
        <script src="/js/jquery-3.5.1.min.js"></script>
        <link rel="stylesheet" href="//code.jquery.com/ui/1.13.0/themes/base/jquery-ui.css">
        <script src="https://code.jquery.com/ui/1.13.0/jquery-ui.js"></script>
        <script>
            $(document).ajaxComplete(function (event, xhr, settings) {
                if(xhr.status>299){
                    App.xhr.onresponse(xhr);
                }
            });
            App={
                xhr:{
                    onresponse:function(xhr){
                        if( xhr.responseText==='' ){
                            return;
                        }
                        if( !xhr.responseJSON ){
                            try{
                                xhr.responseJSON=JSON.parse(xhr.responseText);
                            }catch(e){
                                return;
                            }
                        }
                        if(xhr.responseJSON.messages){
                            let txt='';
                            let error;
                            try{
                                error=JSON.parse(xhr.responseJSON.messages.error);
                                for( let i in error){
                                    txt+=error[i]+'\n';
                                }
                                console.log(error);
                            }
                            catch(e){
                                txt=xhr.responseJSON.messages.error;
                            }
                            alert(txt);
                            return;
                        }
                        alert('Server error: '+xhr.status+'\n'+xhr.responseJSON.message);                        
                    }
                }
            };

        </script>
        <link rel="stylesheet" href="https://ka-f.fontawesome.com/releases/v5.15.4/css/free.min.css?token=1825be3012">
        <link rel="stylesheet" href="https://ka-f.fontawesome.com/releases/v5.15.4/css/free-v4-shims.min.css?token=1825be3012">
        <link rel="stylesheet" href="https://ka-f.fontawesome.com/releases/v5.15.4/css/free-v4-font-face.min.css?token=1825be3012">
        <!--<script src="https://kit.fontawesome.com/1825be3012.js" crossorigin="anonymous"></script>-->
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Raleway:wght@300&display=swap');

            html,body{
                margin: 0px;
                padding: 0px;
                font-family: 'Raleway', sans-serif;
                background-color:#fcfcff;
            }
            a{
                text-decoration: none;
                color:#39f;
            }
            a:hover{
                text-decoration: underline;
            }
            .dash_menu{
                padding: 10px;
                height: calc(100vh-20px);
                background-color: #333;
                color:white;
            }
            .dash_menu a{
                color:#cef;
            }
            input,textarea,.form_value{
                padding: 5px;
            }
            input[type=text],input[type=email],input[type=tel],input[type=number],textarea{
                width:calc( 90% );;
            }
            .segment,segment{
                box-shadow: 3px 5px 8px #ccc;
                border:solid 1px #ccc;
                border-radius: 5px;
                background-color:#fff;
                padding: 10px;
                margin-top: 4px;
                margin-bottom: 4px;
            }
            .primary{
                background-color: #6cf;
            }
            .secondary{
                background-color: #ccc;
            }
            .negative{
                color:white;
                background-color: #f66;
            }
            .positive{
                background-color: #6d9;
            }
            h2,h3{
                -border-bottom: 1px #6cf solid;
            }
            
            
            
            
            .grid_header>div{
                border-bottom: 2px #6cf solid;
                font-weight: bold;
            }
            button{
                padding: 5px;
                border: #09f 1px solid;
            }
            .filter #item_name_search{
                width:calc( 100% - 10px );
                padding: 5px;
                border: 1px solid #ddd;
                background-color: #ffa;
            }
            .image_list{
                display: grid;
                grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr 1fr;
                grid-gap:5px;
            }
            .image_list>div{
                border:5px #fff solid;
                border-radius: 8px;
                overflow: hidden;
                height:90px;
                background: no-repeat center white;
                text-align: center;
            }
            .image_list>div.disabled{
                border:5px #999 solid;
            }
            .image_list>div.deleted{
                border:5px #f99 solid;
            }
            .vcenter{
                display: flex;
                justify-content: center;
                align-items: center;
            }
        </style>
    </head>
    <body>
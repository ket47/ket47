<html>
    <head>
        <title></title>
        <script src="/js/jquery-3.5.1.min.js"></script>
        <script>
            $(document).ajaxComplete(function (event, xhr, settings) {
                if(xhr.status>299){
                    alert('Server error: '+xhr.status+'\n'+xhr.responseText);
                }
            });

        </script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Raleway:wght@300&display=swap');

            html,body{
                margin: 0px;
                padding: 0px;
                font-family: 'Raleway', sans-serif;
                background-color:#fcfcff;
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
                margin: 5px;
            }
            input[type=text],input[type=email],input[type=tel],input[type=number],textarea{
                width:calc( 100% - 100px );;
            }
            .segment{
                box-shadow: 3px 5px 8px #ccc;
                border:solid 1px #ccc;
                border-radius: 5px;
                background-color:#fff;
                padding: 10px;
                margin-top: 8px;
                margin-bottom: 8px;
            }
            .secondary{
                background-color: #eee;
                border-radius: 5px;
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
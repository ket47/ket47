<html>
    <head>
        <title></title>
        <style>
            html,body{
                margin: 0px;
                padding: 0px;
            }
            .dash_menu{
                padding: 5px;
            }
        </style>
    </head>
    <body>
        <div style="display: grid;grid-template-columns:1fr 7fr">
            <div class="dash_menu">
                <a href="javascript:scr.location.reload()">RELOAD</a><br><br>
                <a href="Admin/Permission/" target="scr">PERMISSIONS</a><br>
                <hr>
                <h3>Users</h3>
                <a href="Home/user_data" target="scr">user data</a><br>
                <a href="Home/user_manager" target="scr">user manager</a><br>
                <a href="Home/user_register_form" target="scr">register</a><br>
                <a href="Home/user_login_form" target="scr">login</a><br>
                <a href="User/signOut" target="scr">logout</a><br>
                <a href="Home/user_password_reset" target="scr">pass reset</a><br>
                <a href="Home/user_phone_verification" target="scr">phone verification</a><br>




            </div>
            <iframe name="scr" style="width:100%;height: 100vh;border: none;"></iframe>
        </div>
    </body>
</html>


<?=view('home/header')?>
    <form method="post" action="/User/signIn">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr">
            <div></div>
            <div class="segment" style="display:grid;grid-template-columns:1fr 2fr;">
                <div style="grid-column:1 / 2 span"><h3>User login</h3></div>
                <div>Phone</div>
                <div><input name="user_phone"></div>
                
                <div>Password</div>
                <div><input name="user_pass" type="password"></div>
                
                <div></div>
                <div><button>SignIn</button> | <a href="/Home/user_register_form">Register</a></div>
            </div>
            <div></div>
        </div>
    </form>
<?=view('home/footer')?>
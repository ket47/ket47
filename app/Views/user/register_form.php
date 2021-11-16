<?= view('home/header') ?>
<form method="get" action="/User/signUp/">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr">
        <div></div>
        <div class="segment">
            <h3>New User registration</h3>
            <div style="display: grid;grid-template-columns:auto auto">
                <div>Name</div> <div><input name="user_name" value="John"></div>
                <div>Phone</div> <div><input name="user_phone" value="+79787288233"></div>
                <div>Pass</div> <div><input name="user_pass" value="123456"></div>
                <div>Pass confirm</div> <div><input name="user_pass_confirm" value="123456"></div>
                <div style="grid-column:1 / span 2;text-align: center"><button>SignUp</button> | <a href="/Home/user_login_form">Login</a></div>
            </div>
        </div>
        <div></div>
    </div>
</form>
<?= view('home/footer')?>
<?= view('home/header') ?>
<form method="get" action="/User/passwordReset/">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr">
        <div></div>
        <div class="segment">
            <h3>Reset forgotten password</h3>
            <div style="display: grid;grid-template-columns:auto auto">
                <div>Name</div> <div><input name="user_name" value=""></div>
                <div>Phone</div> <div><input name="user_phone" value=""></div>
                <div>Email</div> <div><input name="user_email" value=""></div>
                <div style="grid-column:1 / span 2;text-align: center"><button>Reset Password</button> | <a href="/Home/user_login_form">Login</a></div>
            </div>
        </div>
        <div></div>
    </div>
</form>
<?=
view('home/footer')?>
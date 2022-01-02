<?=view('home/header')?>
    <div style="display: grid;grid-template-columns:1fr 6fr">
        <div class="dash_menu">
            <a href="javascript:scr.location.reload()">RELOAD</a><br><br>
            <hr>
            <h3>Admin</h3>
            <a href="Admin/Permission" target="scr">Permissions</a><br>
            <a href="Admin/GroupManager" target="scr">Group Manager</a><br>
            <a href="Admin/TaskManager" target="scr">Task Manager</a><br>
            <a href="Admin/PrefManager" target="scr">Preferences</a><br>
            <h3>Orders</h3>
            <a href="Home/order_manager" target="scr">Orders</a><br>
            <h3>Products</h3>
            <a href="Home/product_manager" target="scr">Product</a><br>
            <a href="Home/product_importer" target="scr">Product Importer</a><br>
            <h3>Stores</h3>
            <a href="Home/store_manager" target="scr">Stores</a><br>
            <h3>Users</h3>
            <a href="Home/courier_manager" target="scr">Couriers</a><br>
            <a href="Home/user_manager" target="scr">Users</a><br>
            <a href="Home/user_register_form" target="scr">Sign Up</a><br>
            <a href="Home/user_login_form" target="scr">Sign In</a><br>
            <a href="User/signOut" target="scr">Sign Out</a><br>
            <a href="Home/user_password_reset" target="scr">Password Reset</a><br>
            <a href="Home/user_phone_verification" target="scr">Phone Verification</a><br>
        </div>
        <iframe name="scr" style="width:100%;height: 100vh;border: none;"></iframe>
    </div>
<?=view('home/footer')?>
<?php 
    //Authorization - Access Control
    //Check wether the user is logged in or not
    if(!isset($_SESSION['User_ID'])) { //if user session is not set
        //User is not logged in
        $_SESSION['no-login-message'] = "<div id='error'>Please login to access</div>";
        //Redirect to login page with message
        header("Location: ../log_signup/userlogin.php?");

    }

?>
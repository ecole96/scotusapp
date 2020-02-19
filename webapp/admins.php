<?php
    // admin information is currently stored as an environmental variable with the format "name1:email1,name2:email2"
    // we split it here and turn it into an array for our own use
    // if use_name_keys parameter is true, then we turn it into a key array (name=>email)
    // otherwise, just a typical indexed array (this is because we don't need names in the mailto link in ContactLink()
    function getAdmins($use_name_keys) {
        $admins = array();
        $admins_split = explode(",",getenv("ADMINS"));
        foreach($admins_split as $admin) {
            $admin_split = explode(":",$admin);
            $name = $admin_split[0];
            $email = $admin_split[1];
            $use_name_keys ? $admins[$name] = $email : array_push($admins,$email);
        }
        return $admins;
    }

    // function to generate "Contact" mailto link in the corner of most pages of the webapp
    function contactLink() {
        $html = "";
        $admins = getAdmins(false);
        if(!empty($admins)) {
            $mailto = "mailto:" . $admins[0];
            if(sizeof($admins) > 1) {
                $mailto .= "?cc=" . join(";",array_slice($admins,1));
            }
            $html = "<a style='color:black;' href='$mailto'>Contact</a>";
        }
        return $html;
    }
?>


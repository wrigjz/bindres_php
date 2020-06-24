<?php
###################################################################################################
## Jon Wright, IBMS, Academia Sinica, Taipei, 11529, Taiwan
## These files are licensed under the GLP ver 3, essentially you have the right
## to copy, modify and distribute this script but all modifications must be offered
## back to the original authors
###################################################################################################
# This php ver. 7 script takes a given file and uploads it and checks that it appears to be a valid PDB file
# IT creates a working and a results directory and then queues a Critires job

# Call the mkdirFunc and get the target, results directories and random number back
list($rand_target, $target_dir, $result_dir) = mkdirFunc();

# Set the output to go to the scratch/working/$rand_target directory
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$input_file = $target_dir . "input.pdb";
$uploadOk = 1;
$PDBFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

// Check if pdb file is a reasonable size or not
if (isset($_POST["submit"])) {
    $check = filesize($_FILES["fileToUpload"]["tmp_name"]);
    if ($check !== false) {
        echo "File is a pdb file - " . $check["mime"] . ".";
        $uploadOk = 1;
    } else {
        echo "File is not a pdb file. ";
        $uploadOk = 0;
    }
}

// Check file size
if ($_FILES["fileToUpload"]["size"] > 5000000) {
    echo "Sorry, your file is too large.";
    $uploadOk = 0;
}
// Allow certain file formats
if ($PDBFileType != "pdb") {
    echo "Sorry, only pdb files are allowed.";
    $uploadOk = 0;
}

// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
    echo "Sorry, your file was not uploaded.";
} else { # everything is ok, try to upload file
    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $input_file)) {
        $errfile = $target_dir . "error.txt"; # write something to the error.txt file
        $errfile_handle = fopen($errfile, "w");
        fwrite($errfile_handle, "Preparing and checking the input files\n");
        fclose($errfile_handle);
        echo "The file " . basename($_FILES["fileToUpload"]["name"]) . " has been uploaded.<br>";
        # Check the pdb file/chain structure before queueing
        echo 'cd ' . $target_dir . '; /var/www/html/critires/scripts/get_check_chain.sh';
        exec('cd ' . $target_dir . '; /var/www/html/critires/scripts/get_check_chain.sh', $out, $ret_var);
        if ($ret_var == 0) { # All okay so queue the pdb file
            echo "We will now queue the Critires job<br>";
            echo '/usr/local/bin/qsub -S /bin/bash /var/www/html/critires/scripts/submit.sub -N C_' . $rand_target . ' -v "working=' . $target_dir . '" > ' . $result_dir . 'jobid.txt';
            exec('/usr/local/bin/qsub -S /bin/bash /var/www/html/critires/scripts/submit.sub -N C_' . $rand_target . ' -v "working=' . $target_dir . '" > ' . $result_dir . 'jobid.txt');
            exec('ln -s ' . $target_dir . 'error.txt ' . $result_dir . 'error_link.txt');
        } else { # Something wrong with the pdb file so give an error
            echo 'rsync -av ' . $target_dir . ' ' . $result_dir;
            exec('rsync -av ' . $target_dir . ' ' . $result_dir);
            exec('echo 999999.limlab >| ' . $result_dir . 'jobid.txt');
        }
        echo "<meta http-equiv=\"refresh\" content=\"5; URL=http://critires.limlab.dnsalias.org/results/$rand_target\" />";
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
}

# This function makes a unique random number directory in /scratch and results
function mkdirFunc() {
    mkdirloop:
        $rand_target = rand(1, 1000000);
        $target_dir = "/scratch/working/" . $rand_target . "/";
        $result_dir = "/var/www/html/critires/results/" . $rand_target . "/";
        $dir_exists = (is_dir($target_dir));
        if ($dir_exists == false) {
            mkdir($target_dir, 0700);
            mkdir($result_dir, 0700);
            copy("index.php", "$result_dir/index.php");
        } else {
            gotomkdirloop;
        }
        return array($rand_target, $target_dir, $result_dir);
    }
?>

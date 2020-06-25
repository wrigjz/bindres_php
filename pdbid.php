<?php
###################################################################################################
## Jon Wright, IBMS, Academia Sinica, Taipei, 11529, Taiwan
## These files are licensed under the GLP ver 3, essentially you have the right
## to copy, modify and distribute this script but all modifications must be offered
## back to the original authors
###################################################################################################
# This php ver. 7 script askes for a PDB ID and chain ID, it checks that they are 4 and 1 letter
# and then attemps to extract the chain from an archive version of the PDB entry
# It creates a working and a results directory and then queues a Critires job
# Then it submits a critires job to the server

# Retrieve the PDB and chain ids and check do a few simple checks on the inputs
$pdbid = strtolower($_POST["PDBID"]);
$chain = strtoupper($_POST["CHAIN"]);
if (strlen($pdbid) != 4) {
    echo "Sorry but PDB IDs are 4 characters";
    exit;
}
if (strlen($chain) != 1) {
    echo "Sorry but PDB chain IDs  are 1 characters";
    exit;
}

# Call the mkdirFunc and get the target, results directories and random number back
list($rand_target, $target_dir, $result_dir) = mkdirFunc();
echo "You entered $pdbid $chain $target_dir<br>";
$output = $pdbid . " " . $chain;

# Make the list.txt file and save it to the target directory
$myfile = $target_dir . "list.txt";
$listfile = fopen($myfile, "w");
fwrite($listfile, $output);
fclose($listfile);
$errfile = $target_dir . "error.txt"; # write something to the error.txt file
$errfile_handle = fopen($errfile, "w");
fwrite($errfile_handle, "Preparing and checking the input files\n");
fclose($errfile_handle);

# Find the right PDB file and extract the chain we want
echo 'cd ' . $target_dir . '; /var/www/html/critires/scripts/get_check_chain.sh';
exec('cd ' . $target_dir . '; /var/www/html/critires/scripts/get_check_chain.sh', $out, $ret_var);

# Now submit the job to the qeuue system
if ($ret_var == 0) {
    echo "We will now queue the Critires job<br>";
    echo '/usr/local/bin/qsub -S /bin/bash /var/www/html/critires/scripts/submit.sub -N C_' . $rand_target . ' -v "random=' . $rand_target . '" > ' . $result_dir . 'jobid.txt';
    exec('/usr/local/bin/qsub -S /bin/bash /var/www/html/critires/scripts/submit.sub -N C_' . $rand_target . ' -v "random=' . $rand_target . '" > ' . $result_dir . 'jobid.txt');
    symlink($target_dir . 'error.txt', $result_dir . 'error_link.txt');
} else {
    echo 'rsync -av ' . $target_dir . ' ' . $result_dir;
    exec('rsync -av ' . $target_dir . ' ' . $result_dir);
    exec('echo 999999.limlab >| ' . $result_dir . 'jobid.txt');
}
echo "<meta http-equiv=\"refresh\" content=\"5; URL=http://critires.limlab.dnsalias.org/results/$rand_target\" />";

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
            symlink("/var/www/html/critires/scripts/index.php", "$result_dir/index.php");
        } else {
            gotomkdirloop;
        }
        return array($rand_target, $target_dir, $result_dir);
    }
?>

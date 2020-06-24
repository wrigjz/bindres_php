<?php
###################################################################################################
## Jon Wright, IBMS, Academia Sinica, Taipei, 11529, Taiwan
## These files are licensed under the GLP ver 3, essentially you have the right
## to copy, modify and distribute this script but all modifications must be offered
## back to the original authors
###################################################################################################
# The is the master index file for the Critires Webserver - it is based on php ver. 7,
# If a job is queued or running it tells yu anf gives the position and refreshes each minute
# If it is finished then it creates the gnuplot file and asembles the lines
# for jsmol
# Open the random.txt file to get the job number from the file saved when it was queued
# array to hold the uppercase to lowercase resname
$threeletter = array('ALA' => 'Ala', 'CYS' => 'Cys', 'CYX' => 'Cys', 'ASP' => 'Asp', 'GLU' => 'Glu', 'PHE' => 'Phe', 'GLY' => 'Gly', 'HIS' => 'His', 'HID' => 'His', 'HIE' => 'His', 'HIP' => 'His', 'ILE' => 'Ile', 'LYS' => 'Lys', 'LEU' => 'Leu', 'MET' => 'Met', 'ASN' => 'Asn', 'PRO' => 'Pro', 'GLN' => 'Gln', 'ARG' => 'Arg', 'SER' => 'Ser', 'THR' => 'Thr', 'VAL' => 'Val', 'TRP' => 'Trp', 'TYR' => 'Tyr');

# The main driver code is here, we get the jobid and then check the queue system to see if it is
# Queded or Running or exiting, if it is neither then we check for a results file and if there is one we assume it is finished
# If it is not in the queue system and the results file is empty we assume it has failed

# Check for the jobid file,
$jobfile = fopen("jobid.txt", "r") or die("Unable to open file!");
$jobid = fgets($jobfile);
fclose($jobfile);

# Get the status and give the status as the header
$my_temp = shell_exec("/usr/local/bin/qstat | grep $jobid");
$job_status = preg_split('/\s+/', $my_temp);
$found = isset($job_status[4]); # if the job is in the queue this will be set
if ($found == true) {
    if ($job_status[4] == "Q") {
        queuedup($jobid);
    } elseif ($job_status[4] == "R" || $job_status[4] == "E") {
        running($jobid);
    } else { # Job was not in the queue system, ideally we should never get to this line
        failed($jobid);
    }
} elseif (filesize("results.txt") != 0) { # Check for a results.txt file, if it exists and is not zero then we have finished
    finished($jobid);
} else { # IF we are here then the job is not in the queue and the results.txt file is empty so we failed!
    failed($jobid);
}

# The function for if a job is missing or failed
function failed($jobid) {
    echo "<head>";
    echo "<title>::: Failed at the Critital Residue Interface prediction server :::</title>";
    echo "<meta charset=\"utf-8\">";
    echo "</head>";
    echo "<body BGCOLOR=\"#FFFFFF\">";
    echo "<center> <img src=\"../../images/as-en_07.gif\" alt=\"Academia Sinica Logo\">";
    echo "<h2>Welcome to CritiRes, the Critital Residue Interface prediction server.";
    echo "</center>";
    echo "<H2>Job $jobid is Missing for some reason.</H2>";
    if (filesize("error.txt") != 0 || filesize("critires.err") != 0) {
        echo "To try to get an idea what is wrong<br>";
    }
    if (filesize("error.txt") != 0) {
        echo "You can try looking at the <a href=\"error.txt\">error.txt</a> file,<br>";
    }
    if (filesize("critires.err") != 0) {
        echo "You can try looking at the <a href=\"critires.err\">critires.err</a> file<br> ";
    }
}

# The function for if we find a job is queued
function queuedup($jobid) {
    echo "<head>";
    echo "<title>::: Queued  at the Critital Residue Interface prediction server :::</title>";
    echo "<meta charset=\"utf-8\">";
    echo "</head>";
    echo "<body BGCOLOR=\"#FFFFFF\">";
    echo "<center> <img src=\"../../images/as-en_07.gif\" alt=\"Academia Sinica Logo\">";
    echo "<h2>Welcome to CritiRes, the Critital Residue Interface prediction server.";
    echo "</center>";
    echo "<H2>Your job is $jobid and is currently in the queue for prediction.</H2>";
    echo "This page will be updated every minute";
    # Get the whole queue listing
    $queue_status = shell_exec('/usr/local/bin/qstat -q');
    echo "<pre>$queue_status</pre>";
    # Find my job and print it out
    echo "<pre>Q order  Q number                  Q Name<br></pre>";
    $my_status = shell_exec("/usr/local/bin/qstat | nl -v -2 | grep apache");
    echo "<pre>$my_status</pre>";
    echo "<meta http-equiv=\"refresh\" content=\"60\"/>";
}

# The function for if we find a job is running
# In this case we parse the error_link.txt file and try to give feedback on how the 
# processs is  going
function running($jobid) {
    echo "<head>";
    echo "<title>::: Running  at the Critital Residue Interface prediction server :::</title>";
    echo "<meta charset=\"utf-8\">";
    echo "</head>";
    echo "<body BGCOLOR=\"#FFFFFF\">";
    echo "<center> <img src=\"../../images/as-en_07.gif\" alt=\"Academia Sinica Logo\">";
    echo "<h2>Welcome to CritiRes, the Critital Residue Interface prediction server.";
    echo "</center>";
    echo "<H2>Your job is $jobid and is currently running.</H2>";
    echo "This page will be updated every minute";
    # Find my job and print it out
    echo "<pre>Q order  Q number                  Q Name<br></pre>";
    $my_status = shell_exec("/usr/local/bin/qstat | nl -v -2 | grep $jobid");
    echo "<pre>$my_status</pre>";
    echo "Prepared input file: ";
    exec('grep "Preparing and checking the input files" error_link.txt', $out, $ret_val);
    if ($ret_val == 0) {
        echo "&#9745<br>";
    } else {
        echo "&#9744<br>";
    }
    echo "Started Amber Energy Decomp: ";
    exec('grep "About to start the Amber Energy Calculation" error_link.txt', $out, $ret_val);
    if ($ret_val == 0) {
        echo "&#9745<br>";
    } else {
        echo "&#9744<br>";
    }
    echo "Started Conservation analysis: ";
    exec('grep "Starting the Consurf calculations" error_link.txt', $out, $ret_val);
    if ($ret_val == 0) {
        echo "&#9745<br>";
    } else {
        echo "&#9744<br>";
    }
    echo "Searching the UniRef90 for homologs: ";
    exec('grep "Jackhmmering the Uniref90 DB" error_link.txt', $out, $ret_val);
    if ($ret_val == 0) {
        echo "&#9745<br>";
    } else {
        echo "&#9744<br>";
    }
    echo "Selecting sequences for alignment: ";
    exec('grep "Running select_seqs to rejecting some sequences" error_link.txt', $out, $ret_val);
    if ($ret_val == 0) {
        echo "&#9745<br>";
    } else {
        echo "&#9744<br>";
    }
    echo "Assembling data: ";
    exec('grep "Grading done, consurf finished" error_link.txt', $out, $ret_val);
    if ($ret_val == 0) {
        echo "&#9745<br>";
    } else {
        echo "&#9744<br>";
    }
    echo "Making prediction: ";
    exec('grep "About to start finally printing out the results" error_link.txt', $out, $ret_val);
    if ($ret_val == 0) {
        echo "&#9745<br>";
    } else {
        echo "&#9744<br>";
    }
    echo "<meta http-equiv=\"refresh\" content=\"60\"/>";
}

# The function for if we find a job is finished
function finished($jobid) {
    echo "<head>";
    echo "<title>::: Finished  at the Critital Residue Interface prediction server :::</title>";
    echo "<meta charset=\"utf-8\">";
    echo "</head>";
    echo "<body BGCOLOR=\"#FFFFFF\">";
    echo "<center> <img src=\"../../images/as-en_07.gif\" alt=\"Academia Sinica Logo\">";
    echo "<h2>Welcome to CritiRes, the Critital Residue Interface prediction server.";
    echo "</center>";
    $output = shell_exec('cat /var/www/html/critires/scripts/jscript.scr'); # Setup jsmol environment
    echo $output; # Feed it to the browser
    # Now get the number of residues in the PDB file for the gnuplot xaxis
    $firstres = shell_exec('grep CA input.pdb |head -1| awk \'{print $6}\'');
    $finalres = shell_exec('grep CA input.pdb |tail -1| awk \'{print $6}\'');
    $firstres = str_replace("\n", "", $firstres);
    $finalres = str_replace("\n", "", $finalres);
    $gnufile = fopen("results.gnu", "w");
    # Now read the results file for the jsmol and gnuplot parts
    $results_temp = shell_exec('cat results.txt | awk \'{print $2}\'');
    $results = preg_split('/\s+/', $results_temp);
    $results_count = count($results);
    # Now create the gnuplot data file
    $found1 = 0; # Flag for finding the first results residues for the jsmol select statement
    for ($x = $firstres;$x <= $finalres;$x++) { # Loop around all PDB residues
        $output = $x . " 0.5\n"; # Default is 0.5
        for ($y = 0;$y < $results_count;$y++) { # If it is a CR then we set it to 1.0 instead
            if ($results[$y] == $x) {
                $output = $x . " 1.0\n";
                if ($found1 == 0) {
                    $saved = $results[$y]; # And start the line for the jsmol selection statement
                    $found1 = 1;
                } else {
                    $saved = $saved . ", " . $results[$y]; # Add to jsmol selection statement
                    
                }
            }
        }
        fwrite($gnufile, $output); # Write the gnuplot data file
        
    }
    copy("/var/www/html/critires/scripts/gnuplot.scr", "gnuplot.scr"); # Copy gnuplot script and modify it to our system
    shell_exec("sed -i 's/lower/$firstres/' gnuplot.scr"); # Use sed to set lower residue number
    shell_exec("sed -i 's/upper/$finalres/' gnuplot.scr"); # Use sed to set upper residue number
    shell_exec('gnuplot gnuplot.scr');
    # Now reate the webpage itself
    echo "Your job has finished and the results are available <a href=\"results.txt\">results.txt</a>.<br>";
    echo "They are listed here too for chain ";
    $file = fopen("results.txt", "r"); # Open and read the results file again
    $found1 = 0;
    while (!feof($file)) {
        $temp = rtrim(fgets($file)); # remove the terminal whitespace
        if (strlen($temp) != 0) {
            $temp1 = preg_replace('/\s+/', ' ', $temp); # replace double whitespace with single
            list($resname, $resnumber, $chain, $type) = explode(" ", $temp1);
            $temp2 = $threeletter[$resname];
            if ($found == 0) {
                echo "$chain: $temp2" . "$resnumber";
                $found = 1;
            } else {
                echo ", " . "$temp2" . "$resnumber";
            }
        }
    }
    echo ".";
    fclose($file);
    echo "<p>Input pdb file is <a href=\"input.pdb\">here</a>, the conservation grades are <a href=\"frequency.txt\">here</a><br>";
    echo "<table border=\"1\"> <tr> <td>";
    echo "<p>The image shows the backbone of your protein structure, the predicted CritiRes binding results are depicted in red. </p>";
    echo "<div style=\"width:650px;height:500px\" > <script type=\"text/javascript\"> myJmol1 = Jmol.getApplet(\"myJmol1\", myInfo1);";
    echo "Jmol.script(myJmol1, \"load input.pdb; spacefill off; wireframe off; backbone 0.6; color backbone none; select $saved; color red; wireframe 0.5\")";
    echo "</script>";
    echo "</td>";
    echo "<td>If the image to the left is missing, then you probably need to turn on Javascript for your browser
         and restart your browser. We use the
         <a href=\"http://sourceforge.net/projects/jsmol\">JSmol</a> Javascript version of <a href=\"http://jmol.sourceforge.net/\">Jmol</a> to produde these images.<hr>";
    echo "To get information on a residue hover the mouse over that residue for ~1 second,<br> To rotate use left-click,<br> To translate use ctrl & right-click and <br> To zoom use the mouse wheel.</p> <hr>";
    echo "<p>Right-clicking on the image will bring up the Jmol menu and also allows the console to be displayed for additional selections to be made.</p> <hr />";
    echo "</td> </tr> </table>";
    echo "<h3>Plot of  binding residues vs. residue numner, residues predicted to be involved in protein-protein interaction
 are scored as 1, those not predicted to be involved are scored as 0.5.</h3>";
    echo "<center> <img src=\"gnuplot.png\" alt=\"Residues plot\" height=\"400\"></center>";
    echo "<hr style=\"border-style: solid; color: black;\">";
    echo "<a href=\"https://critires.limlab.dnsalias.org\">CritiRes</a> is hosted at <a href=\"http://www.ibms.sinica.edu.tw\">The Institute of Biomedical Sciences</a>, <a href=\"http://www.sinica.edu.tw\">Academia Sinica</a>, Taipei 11529, Taiwan.";
    echo "<hr style=\"border-style: solid; color: black;\">";
}

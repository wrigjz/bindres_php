#!/bin/bash
###################################################################################################
## Jon Wright, IBMS, Academia Sinica, Taipei, 11529, Taiwan
## These files are licensed under the GLP ver 3, essentially you have the right
## to copy, modify and distribute this script but all modifications must be offered
## back to the original authors
###################################################################################################
# Simple script to perform a bindres job

export hbplus=/home/programs/hbplus-3.06.linux/hbplus
export freesasa=/home/programs/freesasa-2.03/linux/bin/freesasa
export speedfill=/home/programs/speedfill/speedfill.linux
export pymol=/home/programs/anaconda/linux-5.3.6/bin/pymol
export scripts=/home/programs/bindres_scripts
export consurf_scripts=/home/programs/consurf_scripts
source /home/programs/anaconda/linux-5.3.6/init.sh

touch error.txt
echo "About to start BindRes Calculation:" >> error.txt
grep '^ATOM  ' input.pdb | tail -1 | cut -c22-22 >| original_chain.txt

# Run Amber
echo "About to start the Amber Energy Calculation:" >> error.txt
/var/www/html/bindres/scripts/run_amber.sh
error=$?

# Extract Amber Energies
echo "About to extract the Amber Energies:" >> error.txt
python3 $scripts/extract_amber_energies.py
error1=$?
if [ $error -ne 0 ] || [ $error1 -ne 0 ] ; then
     echo "The Calculation or the extraaction of the Amber Energies PFailed" >> error.txt
     echo $error $error1 >> error.txt
     exit 1
fi
echo "Finished the Amber Energy Calculation:" >> error.txt

# At this point we need to run consurf if we do not already have a consurf.grades file, 
# use the two lines below to run it using the ATOM records to get the sequence
# This is the prefered option
echo "About to start the Consurf calculations:" >> error.txt
/var/www/html/bindres/scripts/consurf_home.sh post_mini.pdb
error=$?
PYTHONPATH=. python3 $scripts/get_consurf_home.py initial.grades consurf.txt
error1=$?
if [ $error -ne 0 ] || [ $error1 -ne 0 ] ; then
     echo "The Consurf process or extraction of the grades failed" >> error.txt
     echo $error $error1 >> error.txt
     exit 1
fi
echo "Finished the Consurf calculations:" >> error.txt

# Sort out the SASA values
echo "About to start the FreeSASA calculations:" >> error.txt
$freesasa --config-file $scripts/protor.config --format=seq post_minix.pdb >| post_mini.sasa
error=$?
python3 $scripts/sasa_to_perc.py post_mini.sasa | awk '{print $3", "$4", "$8}' >| post_mini.relsasa
error1=$?
if [ $error -ne 0 ] || [ $error1 -ne 0 ] ; then
     echo "The calculation or extraction of the SASA values failed" >> error.txt
     echo $error $error1 >> error.txt
     exit 1
fi
echo "Finished the FreeSASA calculations:" >> error.txt

# Prepare a non-H atom version for hbplus
echo "About to prepare the hbplus pdb files:" >> error.txt
python3 $scripts/renum_rm_h.py post_minix.pdb post_mini_noh.pdb No
error=$?
sed -i -e 's/CYX/CYS/' -e 's/HID/HIS/' -e 's/HIE/HIS/' -e 's/HIP/HIS/' post_mini_noh.pdb
error1=$?
if [ $error -ne 0 ] || [ $error1 -ne 0 ] ; then
     echo "Creating the non-H atom version for HBPlus failed" >> error.txt
     echo $error $error1 >> error.txt
     exit 1
fi
echo "Finished the preperation work for the hbplus pdb files:" >> error.txt

# Run HBPLUS - needed for the vdw matrix
echo "About to start the HBPLus calculations:" >> error.txt
$hbplus post_mini_noh.pdb -h 2.9 -d 4 -N -c
error=$?
if [ $error -ne 0 ] ; then
     echo "Running HBPlus failed" >> error.txt
     echo $error >> error.txt
     exit 1
fi
echo "Finished the HBPLus calculations:" >> error.txt

## This is for speedfill
echo "About to run speedfill" >> error.txt
$speedfill -f post_mini_noh.pdb -d -ntop 10 -min 1.2 -max 1.4 -log
error=$?
echo "About to run a script to collect the residues near speedfiull spheres" >> error.txt
python3  $scripts/speedfill_residues.py >| speedfill_residues.txt
error1=$?
if [ $error -ne 0 ] || [ $error1 -ne 0 ]; then
     echo "Something went wrong running speedfill or collectings its results" >> error.txt
     echo $error $error1 >> error.txt
     exit 1
fi
echo "Finished running speedfill:" >> error.txt

# Pull all the data togeather
echo "About to assemble all the data:" >> error.txt
$scripts/set_numbers.sh
error=$?
PYTHONPATH=. python3 $scripts/assemble_binding_data.py >| assemble.txt
error1=$?
if [ $error -ne 0 ] || [ $error1 -ne 0 ]; then
     echo "Either setting up the numbering or Running assembly script failed" >> error.txt
     echo $error $error1 >> error.txt
     exit 1
fi
echo "Finished assembling all the data:" >> error.txt

# Get the stable/unstabla and results in the PDB numbering scheme
echo "About to find the stable/unstable/bridge residues:" >> error.txt
python3 $scripts/find_binding_stable_unstable.py >| results_ambnum.txt
error=$?
if [ $error -ne 0 ] ; then
     echo "Initial results with amber numbers failed" >> error.txt
     echo $error >> error.txt
     exit 1
fi
echo "Finished finding the stable/unstable/bridge residues:" >> error.txt

echo "About to start finally printing out the results:" >> error.txt
PYTHONPATH=. python3 $scripts/print_results.py | grep Stable   | sort -g -k 2 >| results.txt
error=$?
PYTHONPATH=. python3 $scripts/print_results.py | grep Unstable | sort -g -k 2 >> results.txt
error1=$?
PYTHONPATH=. python3 $scripts/print_results.py | grep Bridge   | sort -g -k 2 >> results.txt
error2=$?
if [ $error -ne 0 ] || [ $error1 -ne 0 ] || [ $error2 -ne 0 ]; then
     echo "Final reults pringint failed " >> error.txt
     echo $error $error1 $error2 >> error.txt
     exit 1
fi
echo "Finished finally printing out the results:" >> error.txt

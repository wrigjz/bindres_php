#!/bin/bash
###################################################################################################
## Jon Wright, IBMS, Academia Sinica, Taipei, 11529, Taiwan
## These files are licensed under the GLP ver 3, essentially you have the right
## to copy, modify and distribute this script but all modifications must be offered
## back to the original authors
###################################################################################################
#
# A simple script to replciate Consurf for a provided single chain pdb file
# with a chain id of X, this is a post amber minimized pdb file

# Usage consurf_home file.pdb

echo "Starting the Consurf calculations" >> error.txt
if [ "$#" -ne 1 ]; then
    echo "Please give just one file, either a pdbfile or fasta file ending in .pdb or .fasta" >> error.txt
    exit 1
fi

# Check if we are on the cluster
if [ -z "$PBS_NODEFILE" ] ; then
    echo "Consurf non cluster run" >> error.txt
    threads="1"
else
    echo "Consurf cluster run" >> error.txt
    threads=`wc -l < $PBS_NODEFILE`
fi

# setup anaconda environment
source /home/programs/anaconda/linux-5.3.6/init.sh
dbdir=/scratch/consurf_db
blastdir=/home/programs/ncbi-blast/ncbi-blast-2.9.0_linux
hmmerdir=/home/programs/hmmer-3.1b2/linux
cdhitdir=/home/programs/cd-hit-v4.6.8-2017-0621
mafftdir=/home/programs/mafft-7.294/
rate4sitedir=/home/programs/rate4site-3.0.0/src/rate4site/
prottestdir=/home/programs/prottest-3.4.2
scripts=/home/programs/consurf_scripts

# Remove output from previous runs
/bin/rm -rf uniref90_list.txt prealignment.fasta postalignment.aln accepted.fasta uniref.tmp 
/bin/rm -rf consurf_home.grades frequency.txt cons.fasta
/bin/rm -rf homologs.fasta r4s_pdb.py initial.grades r4s.res prottest.out cdhit.log r4s.out

# Work out if we are doing a pdb file or a fasta file
extension="${1#*.}"
if [ $extension == "pdb" ]; then
    # generate the fasta file from the given pdb file
    echo "Creating Fasta file"
    python3 $scripts/mk_fasta.py $1  >| cons.fasta
elif [ $extension == "fasta" ]; then
    # Copy the given fasta sequence to cons.fasta and give it the title PDB_ATOM
    echo '>PDB_ATOM' >| cons.fasta
    grep -v '^>' $1 >> cons.fasta
else 
    echo "You need to give either .pdb or .fasta file" >> error.txt
    exit 1
fi

error=$?
if [ $error -ne 0 ] ; then
   echo "The creation of the fasta file for the consurf calculation failed" >> error.txt
   echo $error >> error.txt
   exit 1
fi

# Jackhmmer the blast database looking for homologs
echo "Jackhmmering the Uniref90 DB" >> error.txt
$hmmerdir/binaries/jackhmmer -E 0.0001 --domE 0.0001 --incE 0.0001 -N 1 \
        -o cons_hmmer.out -A uniref90_list.txt cons.fasta $dbdir/uniref90.fasta
error=$?

# Remove the PDB_ATOM / given fasta entry - probably not needed but good to do anyway
grep -v PDB_ATOM uniref90_list.txt >| uniref.tmp

# Retrieve the sequences that Jackhmmer found
echo "Reformating the sequences from the Uniref90 DB"
error1=$?

$hmmerdir/binaries/esl-reformat fasta uniref.tmp >| homologs.fasta
if [ $error -ne 0 ] || [ $error1 -ne 0 ]; then
   echo "Using jackhmmer to search the uniref90 failed" >> error.txt
   echo $error $error1 >> error.txt
   exit 1
fi
echo "Done with the Jackhmmer search of the uniref90 database" >> error.txt

# Run cd-hit to cluster everything to remove duplicates at the 95% level
echo "Clustering using cdhit and selecting the sequences" >> error.txt
$cdhitdir/cd-hit -i ./homologs.fasta -o ./cdhit.out -c 0.95 >| cdhit.log
error=$?
if [ $error -ne 0 ] ; then
   echo "Using cdhit reduce the jackhmmer results failed" >> error.txt
   echo $error >> error.txt
   exit 1
fi
echo "Finished running cdhit to cluster the uniref90 search output" >> error.txt

echo "Running select_seqs to rejecting some sequences" >> error.txt
python3 $scripts/select_seqs.py cons.fasta cdhit.out
error=$?
if [ $error -ne 0 ] ; then
   echo "Using the select_seq script failed" >> error.txt
   echo $error >> error.txt
   exit 1
fi
echo "Done runing select_seq" >> error.txt

# Use mapsci to produce an alignment
echo "Aligning the final sequences" >> error.txt
$mafftdir/bin/mafft-linsi --quiet --localpair --maxiterate 1000 \
  --thread $threads --namelength 30 ./accepted.fasta >| ./postalignment.aln
error=$?
if [ $error -ne 0 ] ; then
   echo "Aligning the sequences with mafft-linsi failed" >> error.txt
   echo $error >> error.txt
   exit 1
fi
echo "Aligment of accepted sequences finished" >> error.txt

# Calculate the residue frequencies for homologs aligned to the inital given sequence
echo "Putting the frequency file together" >> error.txt
python3 $scripts/get_frequency.py postalignment.aln >| frequency.txt
error=$?
if [ $error -ne 0 ] ; then
   echo "Creating the frequency file failed" >> error.txt
   echo $error >> error.txt
   exit 1
fi
echo "Frequency file finished" >> error.txt

# Get the best protein matrix
echo "Running Prottest" >> error.txt
java -jar $prottestdir/prottest-3.4.2.jar -i postalignment.aln -JTT -LG -MtREV -Dayhoff -WAG \
        -CpREV -S 1 -threads 2 >| prottest.out
error=$?
if [ $error -ne 0 ] ; then
   echo "Running Prottest/java failed" >> error.txt
   echo $error >> error.txt
   exit 1
fi
echo "Prottest finished" >> error.txt

echo "Finding the best model method" >> error.txt
best_model=`grep 'Best model according to BIC:' prottest.out | awk  '{print $6}'`
error=$?
if [ $error -ne 0 ] ; then
   echo "Grepping best model failed" >> error.txt
   echo $error >> error.txt
   exit 1
fi
if [ "$best_model" == "JTT" ] ; then
    rate_model="-Mj"
elif [ "$best_model" == "LG" ] ; then
    rate_model="-Ml"
elif [ "$best_model" == "MtREV" ] ; then
    rate_model="-Mr"
elif [ "$best_model" == "Dayhoff" ] ; then
    rate_model="-Md"
elif [ "$best_model" == "WAG" ] ; then
    rate_model="-Mw"
elif [ "$best_model" == "CpREV" ] ; then
    rate_model="-MC"
else 
    rate_model="-Mj"
fi
echo "Finished finding the best model method" >> error.txt

# Run the rate4site to get the consurf scores - sometimes this fails and if so we then run
# the older version which seems to do better but has less options and is far slower
echo "Running rate4site and grading the scores" >> error.txt
$rate4sitedir/rate4site_doublerep -ib -a 'PDB_ATOM' -s ./postalignment.aln -zn $rate_model -bn \
       -l ./r4s.log -o ./r4s.res  -x r4s.txt >| r4s.out
error=$?
# Check if rate4site ran okay, if not then we run the older version which lacks "LG" so if protest
# recommended that we need to change it to "JTT"
if [ $error -ne 0 ] ; then
    echo "R4S 3.0 failed so we'll try the older version" >> error.txt
    if [ "$best_model" == "LG" ] ; then
        rate_model="-Mj"
    fi
    $rate4sitedir/rate4site.old_slow -ib -a 'PDB_ATOM' -s ./postalignment.aln -zn $rate_model -bn \
       -l ./r4s.log -o ./r4s.res  -x r4s.txt >| r4s.out
    error=$?
fi
if [ $error -ne 0 ] ; then
   echo "Both R4S failed so we" >> error.txt
   echo $error >> error.txt
   exit 1
fi
echo "R4S finished time to grade now" >> error.txt

# Turn those scores into grades
PYTHONPATH=. python3 $scripts/r4s_to_grades.py r4s.res initial.grades
error=$?
paste initial.grades frequency.txt >| consurf_home.grades
error1=$?
if [ $error -ne 0 ] || [ $error1 -ne 0 ]; then
   echo "Final consurf grades calculations failed" >> error.txt
   echo $error $error1 >> error.txt
   exit 1
fi
echo "Grading done, consurf finished" >> error.txt

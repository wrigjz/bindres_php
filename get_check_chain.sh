#!/bin/bash
###################################################################################################
## Jon Wright, IBMS, Academia Sinica, Taipei, 11529, Taiwan
## These files are licensed under the GLP ver 3, essentially you have the right
## to copy, modify and distribute this script but all modifications must be offered
## back to the original authors
###################################################################################################
# Simple script to manage extracting the chain from a pdb file
# and check pdb files for being single chain and also not missing backbones

export scripts=/home/programs/critires_scripts
source /home/programs/anaconda/linux-5.3.6/init.sh

touch error.txt
# Start off by seeing if we need to create the input file itself
if [ -f list.txt ] ; then
   python3 /var/www/html/critires/scripts/get_chain_from_pdb_archive.py list.txt
fi
# Check we had an input.pdb created
if [ ! -s input.pdb ] ; then
    echo "It seems that that PDB ID and chain are not in our local PDB archive" >> error.txt
    echo "Please upload a single chain PDB file instead of entering the PDB ID/CHAIN" >> error.txt
    exit 1
fi

# Run checking for missing backbone atoms in the PDB file
python3 $scripts/check_bb.py input.pdb missing.txt
if [ -s missing.txt ] ; then
    echo "Input file has missing backbone atoms, or too many chains please fix this." >> error.txt
    echo "Each residue needs a N, C, CA and O atom, there also needs to be only one chain too." >> error.txt
    cat missing.txt >> error.txt
    exit 1
fi

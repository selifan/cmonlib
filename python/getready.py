#############################################################################
# Download pdfcompressor.zip, unpack & rename all *-min.pdf to *.pdf
# v.0.2.1 written 2022-06-24, edited 2022-07-06
#############################################################################
import os, glob, sys, shutil, urllib3

url = "https://clientcab.allianzlife.ru/fo/tmp/pdfcompressor.zip"
destFile = "./pdfcompressor.zip"
if not os.path.isfile(destFile):
    # 1) download pdfcompressor.zip
    # r = requests.get(url)
    #with open(destFile, "wb") as fout:
    #    fout.write(r.content)
    print("Downloading {} ...".format(url))
    #print("Download File Status : {}".format(r.status_code))
    #if(r.status_code == 200):
    #    shutil.unpack_archive(destFile, "./")
    #    print("Extracted from {}  OK".format(destFile))
    #else:
    #    print("Download File Failed")
    #    exit(1)

    http = urllib3.PoolManager()
    instream = http.request('GET', url, preload_content=False)
    with open(destFile, 'wb') as fout:
        for chunk in instream.stream(1024):
            fout.write(chunk)
    instream.release_conn()

    if not os.path.isfile(destFile):
        print("Download file failed")
        exit(1)

    print("File {} downloaded, begin unzip and rename...".format(destFile))
# 2) unzip file
shutil.unpack_archive(destFile)
# Rename *-min.pdf to *.pdf
Done = 0
Failed = 0
autoDelete = True
repBlock = "-min"
newBlock = ""
if(len(sys.argv)>1):
    repBlock = sys.argv[1] # to be replaced

if(len(sys.argv)>2):
    newBlock = sys.argv[2] # new value
# print("mask: ./*"+repBlock+"*.pdf")
for fname in glob.glob("./*"+repBlock+"*.pdf"):
    # newName = fname.replace("-min.pdf", ".pdf")
    newName = fname.replace(repBlock, newBlock)
    try:
        if (autoDelete & os.path.isfile(newName)): 
            os.unlink(newName)
            print(newName + " - existing file deleted !")
        os.rename(fname, newName)
        result = True
        Done += 1
    except:
        result = False
        Failed += 1
    
    if(result):
        print("{:32s} -> {} OK".format(fname,newName))
    else:
        print(fname+" rename failed!")

if ((Done+Failed)>0):
    print("Finished, renamed: {}, failed: {}".format(Done, Failed))
else:
    print("No files to rename")

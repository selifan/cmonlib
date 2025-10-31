###################################################################################################
# created 2025-04-17, modified 2025-04-17 by A.S.
# 
###################################################################################################
import os,sys
import time
import datetime
import subprocess
logoFile = "logofile.png"
myfile = "test.xlsx"

# basename = "webdev_latest"
outfile = "mytest.png"
# myHash = hash("proVerka Sviazi na distancii 100 km")
# print(myHash)
# exit(0)

# rarname = basename+".rar"
# bakname = basename + ".bak"
cumulMode = False
encString = bytearray([28,50,100,94,50,230,218,107,39,204,184,29,9,48,3,97,68,15,131,88,70,19])
def getmode():
    global cumulMode
    for iarg in sys.argv:
        if iarg == "append" or iarg == "cumul":
            cumulMode = True
        if iarg == "rollback":
            return iarg
        if iarg == "test":
            return iarg
    return ""

def read_chunks(filename, max_bytes=1024):
    with open(filename, mode="rb") as file:
        while True:
            chunk = file.read(max_bytes)
            if chunk == b"":
                break
            yield chunk



# srcHan = open(logoFile, 'rb')
# outHan = open(outfile,'wb')

# srcdata = srcHan.read()
# byteData = bytearray(srcdata)
# outHan.write(srcdata)
# outHan.close()

outHan2 = open("funnyfile.zzz",'wb')
for chunk in read_chunks(myfile):
    realLen = int(len(chunk))
    newBytes = bytearray(chunk)
    for iNo in range(realLen):
        nOff = iNo % len(encString)
        newBytes[iNo] = newBytes[iNo] ^ encString[nOff]
    outHan2.write(newBytes)    

outHan2.close()
print("zzz written")

# newSize = 200000
# print("Finished, logo size {}, new Size: {}".format(len(srcdata), newSize))

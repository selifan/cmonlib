###################################################################################################
# created 2025-04-17, modified 2025-04-17 by A.S.
# 
###################################################################################################
import os,sys
import time
import datetime
import subprocess

encString = bytearray([28,50,100,94,50,230,218,107,39,204,184,29,9,48,3,97,68,15,131,88,70,19])

logoFile = "logofile.png"
encodedFile ="funnyfile.zzz"
myfile = "test.xlsx"

# basename = "webdev_latest"
outfile = "mytest.png"

# rarname = basename+".rar"
# bakname = basename + ".bak"
cumulMode = False

def read_chunks(filename, max_bytes=1024):
    with open(filename, mode="rb") as file:
        while True:
            chunk = file.read(max_bytes)
            if chunk == b"":
                break
            yield chunk

decodeTo = "decoded.xlsx"
outHan2 = open(decodeTo,'wb')
for chunk in read_chunks(encodedFile):
    realLen = int(len(chunk))
    newBytes = bytearray(chunk)
    for iNo in range(realLen):
        nOff = iNo % len(encString)
        newBytes[iNo] = newBytes[iNo] ^ encString[nOff]

    outHan2.write(newBytes)    

outHan2.close()

print("Decodong done {} to {}".format(encodedFile, decodeTo))

##############################################################################################
# gitdiff.py
# Tool for "partial semi-manual merge" from one git branch to another
# It finds all changed/added files and dirs (that not in local commit), 
# copies them to transfer folder;
# Found deletions will be listed in "toDelete.log" file
# Second action, "apply" applies all collected changes to the current branch:
# Configuration must be set in gitdiff.cfg file in form parameter = value
# python gitdiff.py apply <from_branch_name>
# By Alexander Selifonov, version 0.52.001 2024-11-13, license MIT
##############################################################################################
import sys,os,shutil,fnmatch, subprocess
from pathlib import Path
# print(sys.argv)
action = "getdiff"
tmpBranch = '_tmp_branch_'
fromBranch = ""
debugMode = False
delLogBase = "toDelete.log"

transFolder = "/git-exchange/" # "transfer folder" - where to copy changed/new files
verbose = 0
errCnt = 0
ignoreList = []
modified = []
deleted = []
newFiles = []
newDirs = []
branchName = ""
branchFolder = ""
strgList = []
# standard apply/merge method: just copy updated files into destination current branch
applyType = "copy"
# TODO: create "git" applyType, so all selected changes will be really merged through a temporary branch

argCount = len(sys.argv)

if argCount>1:
    action = sys.argv[1]
if (action == "applydiff" or action=="apply") and argCount>2:
    fromBranch = sys.argv[2]
# print("count: {}".format(len(sys.argv)))
# exit(0)

# execute shell cmd, return response as string array
def cmdShell(cmdString):
    # params = cmdString.split(" ")
    # result = subprocess.run(params, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    response = subprocess.getoutput(cmdString)
    arRet = response.splitlines()
    # print(cmdString + " result: ")
    return arRet

# call "git status" and get current branch
def gitStatus():
    global strgList,branchName
    strgList = cmdShell('git status')
    for oneLine in strgList:
        if(oneLine[0:9] == "On branch"):
            branchName = oneLine[10:]
    if branchName == '':
        print("Not a git project. Exiting")
        exit(2)

    print("Active Branch is "+branchName)
# read config parameters from gitdiff.cfg
def loadConfig():
    global ignoreList, transFolder, verbose, branchFolder, applyType
    myPath = os.getcwd()
    # os.path.realpath(os.path.dirname(__file__)) # -running script folder
    # os.getcwd() - current dir, not a dir of running py!
    cfgFile = myPath + "/gitdiff.cfg"
    if os.path.isfile(cfgFile):
        cfgLines = Path(cfgFile).read_text().splitlines()
        # parse parameters
        for sLine in cfgLines:
            sLine = sLine.strip()
            if sLine[0] == '#':
                continue # Comment line, ignore it
            subLines = sLine.split("=")
            if(len(subLines) < 2):
                continue # not a "param: value" line, ignore
            key = subLines[0].strip()
            svalue= subLines[1].strip()
            if key == "transfer_folder":
                transFolder = svalue
                continue
            if key == "verbose":
                verbose = int(svalue)
                continue
            if key == "ignore":
                ignoreList += svalue.split(",")
                continue
            if key == 'applytype':
                applyType = svalue
                continue

    #### end of loadConfig()

# Copy file to transfer folder, so U can grab it into alter git branch
def copyOneFile(fname: str):
    global transFolder, branchFolder,errCnt

    if(isIgnoredFile(fname)):
        return 0
    slash = fname.rfind("/")
    if(slash>0):
        pathPart = fname[0:slash]
        namePart = fname[slash+1:]
    else:
        pathPart = ""
        namePart = fname
    if(pathPart !=""):
        destPath = branchFolder + "/" + pathPart
        destFullName = destPath + "/" + namePart
        if(not os.path.isdir(destPath)):
            os.makedirs(destPath)
            print(destPath + " created")
    else:
        destFullName = branchFolder + "/" + namePart

    try:
        if filesAreEqual(fname, destFullName):
            if verbose>0:
                print(fname + " skipped (saved earlier)")
            return 1
        result = shutil.copyfile(fname, destFullName)
        shutil.copystat(fname, destFullName)
        # print("-- copy from {} to {}: result={} ".format(fname, destFullName,result))
        if verbose>0:
           print(fname + " copied")
        return 1;
    except:
        print("Error copying file "+fname)
        errCnt += 1
        return 0

# returns True if two files seams to be equal (by size and modif.date/time)
def filesAreEqual(fname1, fname2):
    if os.path.isfile(fname1) and os.path.isfile(fname2):
        fsize1 = os.path.getsize(fname1)
        fsize2 = os.path.getsize(fname2)
        if fsize1 != fsize2:
            return False
        stat = os.stat(fname1)
        mtime1 = stat.st_mtime
        stat = os.stat(fname2)
        mtime2 = stat.st_mtime
        if mtime1 != mtime2:
            return False
        return True
    return False

# Add deleted file path-name to a log file, that reminds U delete it in alter git branch
def registerDeleted(fname: str):
    global transFolder, branchName, newFiles, verbose, delLogBase
    #if(isIgnoredFile(fname)):
    #    return 0;
    logFile = transFolder + branchName + "/" + delLogBase
    if os.path.isfile(logFile):
        delLines = Path(logFile).read_text().splitlines()
    else:
        delLines = []
    if fname in delLines:
        if verbose>0:
            print("Delete already registered: "+fname)
        return 0 # file already in list

    delLines += [fname]
    delContent = '\n'.join(delLines)
    fileHan = open(logFile,'w')
    fileHan.write(delContent)
    fileHan.close()
    if(verbose>0):
        print("Registered to delete: "+fname)
    return 1

# Check the file against Ignore List
def isIgnoredFile(fname: str):
    lastSlash = fname.rfind("/")
    if lastSlash<0:
        baseName = fname
    else:
        baseName = fname[(lastSlash+1):]
    if baseName == "":
        print("Skip just folder name: ", fname)
        return True

    for ignoreItem in ignoreList:
        lastSlash = ignoreItem.rfind("/")
        if lastSlash >=0:
            ignorePath = ignoreItem[0:(lastSlash+1)]
            maskPart = ignoreItem[(lastSlash+1):]
        else:
            ignorePath = ""
            maskPart = ignoreItem
        # print("{}: ignore Path:{}, fileMask:{}".format(ignoreItem, ignorePath, maskPart))
        pathMatch = (ignorePath=="" or fname.find(ignorePath) >=0)
        fileMatch = maskPart == "" or fnmatch.fnmatch(baseName, maskPart)
        if pathMatch and fileMatch:
            if verbose>1 or True:
                print("File ignored: " + fname+ " by rule: "+ignoreItem)
            return True
    return False
# parse commands (shown after git add .):
#	new file:   app/defvars/newfile.xml (done)
#	renamed:    app/old_name.xml -> app/defvars/new_name.xml

# function for acquiring new/modified/deleted files and copy them to transfer folder
def acquireDiffs():
    global strgList, deleted,newFiles,newDirs,modified,branchName,branchFolder, errCnt, verbose
    mode = 0

    for oneLine in strgList:
        if(mode == 1 ):
            if oneLine =="" or oneLine[0] !="\t":
                continue

            mdFile = oneLine.strip()
            # print("untracked: " + mdFile)
            if os.path.isfile(mdFile):
                newFiles += [mdFile]
            else:
                # new untracked folder - add all files inside!
                # print("getting new dir "+ mdFile)
                newDirs += [mdFile]

            continue

        shortLine = oneLine.strip()
        if shortLine[0:9] == "modified:" or oneLine[0:9] == "new file:":
            mdFile = shortLine[10:].strip()
            modified += [mdFile]
            continue
        if(shortLine[0:8] == "deleted:"):
            mdFile = shortLine[9:].strip()
            deleted += [mdFile]
            continue
        if shortLine[0:8] == "renamed:":
            mdFile = shortLine[8:].strip()
            strParts = mdFile.split("->")
            oldName = strParts[0].strip()
            newName = strParts[1].strip()
            # print("renamed is {} to {}!".format(oldName, newName))
            deleted +=[oldName]
            newFiles += [newName]
            continue
        if(shortLine == "Untracked files:"):
            mode = 1

    if(branchName ==""):
        print("ERROR: Not in Git project")
        exit(1)

    modifCnt = 0
    delCnt = 0
    skipCnt = 0
    # 2) Start copy files to transFolder
    branchFolder = transFolder + branchName
    if(not os.path.isdir(branchFolder)):
        os.makedirs(branchFolder)
        if verbose > 1:
            print(branchFolder + " created")

    for fname in modified:
        modifCnt += copyOneFile(fname)

    for fname in newFiles:
        modifCnt += copyOneFile(fname)
    for oneDir in newDirs:
        destDir = branchFolder+"/"+oneDir
        destination = shutil.copytree(oneDir, destDir, dirs_exist_ok=True)
        print ("dir copied: ", oneDir, " to ", destDir)

    for fname in deleted:
        delCnt += registerDeleted(fname)

    print("Done. New/Modified files: {}, deleted: {}, copy errors:{}".format(modifCnt, delCnt, errCnt))

# Delete all files listed in passed file-list
def unlinkFiles(logName):
    global verbose, errCnt
    print("TODO: delete files in "+logName)
    delLines = Path(logName).read_text().splitlines()
    for sLine in delLines:
        sLine = sLine.strip()
        if os.path.isfile(sLine):
            try:
                os.remove(sLine)
                if verbose>0:
                    print("File {} deleted".format(sLine))
            except:
                print("Error deleting {}".format(sLine))
                errCnt += 1

def applyDiffs():
    global fromBranch,branchName,transFolder, delLogBase

    # print("TODO: applydiff from branch {}".format(fromBranch))
    if fromBranch == "":
        print("applydiff must be with source_branch: applydiff mybranch")
        exit(2)

    if fromBranch == branchName:
        print("Error: attempt to apply the same branch "+fromBranch)
        exit(4)
    branchHomedir = transFolder + fromBranch
    if not os.path.isdir(branchHomedir):
        print("Error: No collected data from {}".format(fromBranch))
        exit(5)

    logName = branchHomedir + "/" + delLogBase
    # print("handle delete log file "+logName)
    # 1) delete files marked for deleting
    if os.path.isfile(logName):
        unlinkFiles(logName)
        if not debugMode:
            os.remove(logName)

    # 2) copy all collected "modified" files by one copytree() call
    sResult = shutil.copytree(branchHomedir, "./", dirs_exist_ok=True)
    if not debugMode:
        shutil.rmtree(branchHomedir)
    print("Apply from branch {} done. Errors: {}".format(fromBranch, errCnt))

# apply diffs by git merge through temporary branch
def applyDiffsGit():
    global fromBranch,branchName,transFolder, delLogBase, tmpBranch, homeBranch
    print("start apply(git mode)")
    if homeBranch == '':
        homeBranch = branchName

    # 1) make temporary branch
    cmdStrg = 'git checkout -b ' + tmpBranch
    # cmdStrg = 'git status'
    responseLines = cmdShell(cmdStrg)
    success = False
    for oneLine in responseLines:
        print("create tmp: " + oneLine)
        print("--" + oneLine[0:24] +"--")
        if(oneLine[0:24] == "Switched to a new branch"):
            success = True
            print("Tmp branch created")
        
    # if not success:
    #     print("Create tmp branch failed")
    #     exit(100)

    # 2) copy all changes to temp branch
    applyDiffs()
    # 3) - commit changes
    cmdShell("git add .")
    cmdShell('git commit -m "temp changes"')
    # 4) return to my current branch
    cmdStrg = 'git checkout '+ homeBranch
    print("Back to " + homeBranch)
    responseLines = cmdShell(cmdStrg)
    for oneLine in responseLines:
        # if(oneLine[0:18] == "Switched to branch"):
        #    success = True
        print("back to home branch log: " + oneLine)

    # 5) make git merge from tmp branch to current branch
    cmdStrg = 'git merge ' + tmpBranch
    responseLines = cmdShell(cmdStrg)
    for oneLine in responseLines:
        # if(oneLine[0:18] == "merged"):
        #    success = True
        print("merge log: " + oneLine)

    # 6) delete temp branch
    cmdStrg = 'git branch -D ' + tmpBranch
    responseLines = cmdShell(cmdStrg)
    for oneLine in responseLines:
        # if(oneLine[0:14] == "Deleted branch"):
        #    success = True
        print("delete tmp: " + oneLine)

# performing action ---------------------------------------------------------
loadConfig()
gitStatus()
homeBranch = branchName
myPath = os.getcwd()
destPath = os.path.realpath(transFolder)
# print("current branch: " + branchName)
# exit(0)
if destPath.find(myPath)>=0:
    print("Config Error: Transfer folder is inside Current project (drives to recursion)!")
    exit(3)

# exit(0)

if action == "getdiff":
    acquireDiffs()
elif action == "applydiff" or action == "apply":
    # TODO: applydiff - apply collected diffs to
    if applyType == 'git':
        applyDiffsGit()
    else:
        applyDiffs()
else:
    print("Error: unsupported operation {}".format(action))
    exit(3)


# this script uploads SCOTUSApp DB backups to the team Google Drive in the form of a .zip file (with a .sql inside)
from pydrive.auth import GoogleAuth
from pydrive.drive import GoogleDrive
import sys
import os

# authenticate Google Drive account
# returns Google Drive to work with
def authenticate(configpath,credpath):
    gauth = GoogleAuth()
    gauth.DEFAULT_SETTINGS['client_config_file'] = configpath
    gauth.LoadCredentialsFile(credpath)
    if gauth.credentials is None:
        gauth.LocalWebserverAuth() # perform web browser authentication (this shouldn't occur as long as we have a credentials file)
    elif gauth.access_token_expired: # refresh access token using refresh token
        gauth.Refresh()
    else:
        gauth.Authorize()
    gauth.SaveCredentialsFile(credpath) # update any credentials changes
    drive = GoogleDrive(gauth)
    return drive

# uploads backup to our backups folder on the drive
# returns retcode depending on upload status - adhering to exit code convention, 0 is success and 1 is failure.
def upload(filepath,drive):
    if os.path.exists(filepath):
        filename = os.path.basename(filepath)
        db_file = drive.CreateFile({"title":filename,"parents":  [{"id": os.environ['DRIVE_FOLDER_ID']}]})
        db_file.SetContentFile(filepath)
        db_file.Upload()
        retcode = not int(db_file.uploaded) # convert upload status to exit code
    else:
        retcode = 1
    return retcode

# call script with 3 command line arguments - db backup path, client config path (client_secrets.json), and credentials path (for storing auth data). Must be in that order.
# script exits with a return code that depends on success / failure (0 = success, 1 = failure)
def main():
    if len(sys.argv) < 4:
        print("Needs command line arguments (db backup path, client config path, and credentials path - in that order)")
        retcode = 1
    else:
        try:
            filepath = sys.argv[1]
            configpath = sys.argv[2]
            credpath = sys.argv[3]
            drive = authenticate(configpath,credpath)
            retcode = upload(filepath,drive)
        except Exception as e:
            print(e)
            retcode = 1
    exit(retcode)
main()
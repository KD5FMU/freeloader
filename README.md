
<img width="1182" height="943" alt="image" src="https://github.com/user-attachments/assets/aee6bfb8-2012-4eb3-8d19-e6a77398a9a4" />






This is an file manager utilty called freeloader because it frees the user from having to use SSH or SFTP to manage files on your Allstar Node.
The utility requires a secure login to use. 
Here is how to install it:
first lets get to s specific directory after you after started an SSH session into your node or from the terminal CLI if you like. 

```
cd /etc/asterisk/local
```

if the directory does not exist, lets create it

```
sudo mkdir /etc/asterisk/local
```

then switch to the directory

```
cd /etc/asterisk/local
```

Then lets download the installer script

```
sudo wget https://raw.githubusercontent.com/n5ad/freeloader/refs/heads/main/freeloader.sh
```
Then lets run the script using
```
sudo bash freeloader.sh
```
you will be prompted to create a password that you will need to enter in order to use the utility. Once the utility is installed, you can find it at node[your node number].local/freeloader

## Allowed directories

Only directories on the server whitelist can be browsed, uploaded to, edited, or deleted. Defaults (must match in both `freeloader_common.php` and `freeloader-helper.sh`):

- `/my_uploads`
- `/etc/asterisk`
- `/etc/asterisk/local`
- `/etc/allmon3`
- `/var/lib/asterisk`
- `/var/www/html/supermon`
- `/usr/share/allmon3`

`/usr/local/bin` and `/var/www/html/freeloader` are intentionally **not** allowed, so the privileged helper and the Freeloader app itself cannot be overwritten through the UI.

To change the list: edit **both** files, then reinstall/copy the helper with the installer (or copy `freeloader-helper.sh` to `/usr/local/bin/freeloader-helper` as root). It is the operators responsibility [ no different than using the CLI ]not to damage the node.

Not every file is editable in the UI (pattern-based).




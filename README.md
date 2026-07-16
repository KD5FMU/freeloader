<img width="1671" height="940" alt="image" src="https://github.com/user-attachments/assets/268e39d6-9227-4292-ba25-4abbe8a088d9" />






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



